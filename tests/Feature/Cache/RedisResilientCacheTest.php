<?php

namespace Tests\Feature\Cache;

use App\Services\Cache\CacheService;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Redis;
use Tests\TestCase;

class RedisResilientCacheTest extends TestCase
{
    private const KEY_PREFIX = 'cdtm-redis-resilient-test';

    protected function setUp(): void
    {
        parent::setUp();

        // Tests load .env.testing, which has no Redis settings, so read .env directly.
        $real = $this->realEnvValues();

        // Locks use the 'default' connection, values use 'cache': both must point here.
        foreach (['default', 'cache'] as $connection) {
            config([
                "database.redis.{$connection}.host" => $real['REDIS_HOST'] ?? '127.0.0.1',
                "database.redis.{$connection}.port" => $real['REDIS_PORT'] ?? 6379,
                "database.redis.{$connection}.password" => $real['REDIS_PASSWORD'] ?? null,
                "database.redis.{$connection}.timeout" => (float) ($real['REDIS_TIMEOUT'] ?? 1.0),
                "database.redis.{$connection}.read_timeout" => (float) ($real['REDIS_READ_TIMEOUT'] ?? 2.0),
            ]);
        }
        config(['cdtm-cache.store' => 'redis']);
        $this->closeRealRedisConnections();

        if (! $this->realRedisIsReachable()) {
            $this->markTestSkipped('No reachable Redis server on REDIS_HOST/REDIS_PORT — skipping.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function realEnvValues(): array
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $values;
    }

    protected function tearDown(): void
    {
        // Not realRedisConnection(): it would reconnect, and throw, when Redis is down.
        $this->redisProbe?->close();
        $this->redisProbe = null;

        parent::tearDown();
    }

    public function test_it_round_trips_a_value_against_the_real_redis_server(): void
    {
        $cache = CacheService::make();

        $first = $cache->remember('redis-probe', 'roundtrip', fn () => ['ok' => true]);
        $second = $cache->remember('redis-probe', 'roundtrip', fn () => ['ok' => false]);

        $this->assertSame(['ok' => true], $first);
        $this->assertSame(['ok' => true], $second);
    }

    public function test_concurrent_misses_produce_exactly_one_database_load(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available — cannot fork for a real concurrency proof.');
        }

        $namespace = 'redis-concurrency';
        $key = 'shared-key';
        $loadCounterKey = self::KEY_PREFIX.':load-count';

        $redis = $this->realRedisConnection();
        $redis->del($loadCounterKey);
        // A previous run may have left a live entry under this key.
        CacheService::make()->invalidate($namespace);

        // Children inherit open sockets; sharing one corrupts the Redis protocol stream.
        $this->closeRealRedisConnections();

        $childCount = 8;
        $pids = [];

        for ($i = 0; $i < $childCount; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork failed.');
            }

            if ($pid === 0) {
                $this->closeRealRedisConnections();

                $cache = CacheService::make();
                $cache->remember($namespace, $key, function () use ($loadCounterKey) {
                    // Counted outside CacheService, so the proof doesn't rely on it.
                    $this->realRedisConnection()->incr($loadCounterKey);
                    usleep(50_000); // widen the race window

                    return ['loaded' => true];
                });

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $redis = $this->realRedisConnection();
        $loadCount = (int) $redis->get($loadCounterKey);
        $redis->del($loadCounterKey);

        $this->assertSame(1, $loadCount, 'Expected exactly one process to load the value; single-flight did not collapse the concurrent misses.');
    }

    public function test_a_refused_connection_falls_back_to_the_loader_without_hanging(): void
    {
        // Port 9 ("discard") is not Redis and refuses the connection immediately.
        config(['database.redis.cache.port' => 9]);
        $this->closeRealRedisConnections();

        $cache = CacheService::make();

        $start = microtime(true);
        $result = $cache->remember('redis-outage', 'probe', fn () => ['from' => 'database']);
        $elapsed = microtime(true) - $start;

        $this->assertSame(['from' => 'database'], $result);
        $this->assertLessThan(5.0, $elapsed, 'Falling back to the loader took too long — the connection may be hanging instead of failing fast.');
    }

    private function realRedisIsReachable(): bool
    {
        try {
            $this->realRedisConnection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // forgetInstance() alone leaves the socket open until a later GC cycle.
    private function closeRealRedisConnections(): void
    {
        $this->redisProbe?->close();
        $this->redisProbe = null;

        if ($this->app->resolved('redis')) {
            foreach (['default', 'cache'] as $connection) {
                try {
                    RedisFacade::connection($connection)->disconnect();
                } catch (\Throwable) {
                    // Never connected.
                }
            }
        }

        $this->app->forgetInstance('redis');
        $this->app->forgetInstance('cache');
    }

    private ?Redis $redisProbe = null;

    private function realRedisConnection(): Redis
    {
        if ($this->redisProbe instanceof Redis) {
            return $this->redisProbe;
        }

        $real = $this->realEnvValues();

        $redis = new Redis();
        $redis->connect(
            $real['REDIS_HOST'] ?? '127.0.0.1',
            (int) ($real['REDIS_PORT'] ?? 6379),
            1.0
        );

        $password = $real['REDIS_PASSWORD'] ?? null;
        if ($password && $password !== 'null') {
            $redis->auth($password);
        }

        return $this->redisProbe = $redis;
    }
}
