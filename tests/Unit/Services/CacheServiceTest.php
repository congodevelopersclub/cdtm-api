<?php

namespace Tests\Unit\Services;

use App\Services\Cache\CacheService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    private function realArrayStoreService(int $ttl = 60, float $jitter = 0.2): CacheService
    {
        return new CacheService(Cache::store('array'), $ttl, $jitter, lockTtl: 10, lockWait: 3);
    }

    public function test_remember_returns_the_loaded_value_on_a_miss_and_caches_it(): void
    {
        $cache = $this->realArrayStoreService();

        $result = $cache->remember('profiles', 'index:page=1', fn () => ['fresh' => true]);

        $this->assertSame(['fresh' => true], $result);
    }

    public function test_remember_returns_the_cached_value_without_calling_the_loader_again(): void
    {
        $cache = $this->realArrayStoreService();
        $calls = 0;

        $loader = function () use (&$calls) {
            $calls++;

            return ['call' => $calls];
        };

        $first = $cache->remember('profiles', 'index:page=1', $loader);
        $second = $cache->remember('profiles', 'index:page=1', $loader);

        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    public function test_invalidate_makes_the_next_remember_call_reload(): void
    {
        $cache = $this->realArrayStoreService();
        $calls = 0;

        $loader = function () use (&$calls) {
            $calls++;

            return ['call' => $calls];
        };

        $before = $cache->remember('profiles', 'index:page=1', $loader);
        $cache->invalidate('profiles');
        $after = $cache->remember('profiles', 'index:page=1', $loader);

        $this->assertNotSame($before, $after);
        $this->assertSame(2, $calls);
    }

    public function test_invalidating_one_namespace_does_not_affect_another(): void
    {
        $cache = $this->realArrayStoreService();

        $cache->remember('profiles', 'index:page=1', fn () => ['ns' => 'profiles']);
        $cache->remember('skills', 'index', fn () => ['ns' => 'skills']);

        $cache->invalidate('profiles');

        $stillCached = $cache->remember('skills', 'index', fn () => $this->fail('skills namespace should not have been invalidated'));

        $this->assertSame(['ns' => 'skills'], $stillCached);
    }

    public function test_remember_falls_back_to_the_loader_when_the_store_is_unreachable(): void
    {
        $repo = \Mockery::mock(Repository::class);
        $repo->shouldReceive('get')->andThrow(new \RuntimeException('connection refused'));

        $cache = new CacheService($repo, ttl: 60, jitter: 0.2, lockTtl: 10, lockWait: 3);

        $result = $cache->remember('profiles', 'index:page=1', fn () => ['from' => 'database']);

        $this->assertSame(['from' => 'database'], $result);
    }

    public function test_remember_falls_back_to_the_loader_when_the_lock_cannot_be_acquired_in_time(): void
    {
        $lock = \Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException());

        $store = \Mockery::mock(Store::class, LockProvider::class);
        $store->shouldReceive('lock')->once()->andReturn($lock);

        $repo = \Mockery::mock(Repository::class);
        // One get() for the generation counter, one for the value.
        $repo->shouldReceive('get')->twice()->andReturnNull();
        $repo->shouldReceive('getStore')->once()->andReturn($store);

        $cache = new CacheService($repo, ttl: 60, jitter: 0.2, lockTtl: 10, lockWait: 1);

        $result = $cache->remember('profiles', 'index:page=1', fn () => ['from' => 'database']);

        $this->assertSame(['from' => 'database'], $result);
    }

    public function test_invalidate_never_throws_when_the_store_is_unreachable(): void
    {
        $repo = \Mockery::mock(Repository::class);
        $repo->shouldReceive('increment')->andThrow(new \RuntimeException('connection refused'));

        $cache = new CacheService($repo, ttl: 60, jitter: 0.2, lockTtl: 10, lockWait: 3);

        $cache->invalidate('profiles');

        $this->addToAssertionCount(1);
    }

    public function test_jittered_ttl_stays_within_the_configured_spread(): void
    {
        $repo = \Mockery::mock(Repository::class);
        // One get() for the generation counter, one for the value.
        $repo->shouldReceive('get')->twice()->andReturnNull();
        $repo->shouldReceive('getStore')->once()->andReturn(\Mockery::mock(Store::class));
        $repo->shouldReceive('put')->once()->with(
            \Mockery::type('string'),
            ['x' => 1],
            \Mockery::on(fn (int $ttl) => $ttl >= 60 && $ttl <= 72)
        )->andReturn(true);

        $cache = new CacheService($repo, ttl: 60, jitter: 0.2, lockTtl: 10, lockWait: 3);

        $cache->remember('profiles', 'index:page=1', fn () => ['x' => 1]);

        $this->addToAssertionCount(1);
    }

    public function test_a_failing_put_does_not_run_the_loader_twice(): void
    {
        $repo = \Mockery::mock(Repository::class);
        $repo->shouldReceive('get')->andReturnNull();
        $repo->shouldReceive('getStore')->andReturn(\Mockery::mock(Store::class));
        $repo->shouldReceive('put')->andThrow(new \RuntimeException('OOM command not allowed'));
        $cache = new CacheService($repo, ttl: 60, jitter: 0.2, lockTtl: 10, lockWait: 3);
        $calls = 0;

        $result = $cache->remember('profiles', 'index:page=1', function () use (&$calls) {
            $calls++;

            return ['x' => 1];
        });

        $this->assertSame(['x' => 1], $result);
        $this->assertSame(1, $calls);
    }

    public function test_a_throwing_loader_runs_once_and_its_exception_propagates(): void
    {
        $repo = \Mockery::mock(Repository::class);
        $repo->shouldReceive('get')->andReturnNull();
        $repo->shouldReceive('getStore')->andReturn(\Mockery::mock(Store::class));
        $cache = new CacheService($repo, ttl: 60, jitter: 0.2, lockTtl: 10, lockWait: 3);
        $calls = 0;

        try {
            $cache->remember('profiles', 'index:page=1', function () use (&$calls) {
                $calls++;

                throw new \RuntimeException('database is down');
            });
            $this->fail('Expected the loader exception to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('database is down', $e->getMessage());
        }

        $this->assertSame(1, $calls);
    }
}
