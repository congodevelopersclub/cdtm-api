<?php

namespace App\Services\Cache;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class CacheService
{
    private const GENERATION_TTL = 315_360_000; // ten years

    public function __construct(
        private readonly Repository $store,
        private readonly int $ttl,
        private readonly float $jitter,
        private readonly int $lockTtl,
        private readonly int $lockWait,
    ) {
    }

    public static function make(): self
    {
        try {
            $store = self::configuredStore();
        } catch (\Throwable) {
            $store = Cache::build(['driver' => 'null']);
        }

        return new self(
            $store,
            (int) config('cdtm-cache.ttl'),
            (float) config('cdtm-cache.jitter'),
            (int) config('cdtm-cache.lock_ttl'),
            (int) config('cdtm-cache.lock_wait'),
        );
    }

    public static function configuredStore(): Repository
    {
        $name = config('cdtm-cache.store');

        // A null name would silently resolve to the default store.
        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('CDTM_CACHE_STORE is empty.');
        }

        return Cache::store($name);
    }

    /**
     * @param callable(): array<mixed> $loader
     * @return array<mixed>
     */
    public function remember(string $namespace, string $key, callable $loader): array
    {
        $fullKey = $this->fullKey($namespace, $key);

        try {
            $cached = $this->store->get($fullKey);
        } catch (\Throwable) {
            return $loader();
        }

        return is_array($cached) ? $cached : $this->loadWithSingleFlight($fullKey, $loader);
    }

    public function invalidate(string $namespace): void
    {
        $key = $this->generationKey($namespace);

        try {
            // DatabaseStore::increment() doesn't create a missing key; add() is only atomic with a TTL.
            if ($this->store->increment($key) === false && ! $this->store->add($key, 1, self::GENERATION_TTL)) {
                $this->store->increment($key);
            }
        } catch (\Throwable) {
            // Degraded: reads may be stale until the TTL expires.
        }
    }

    /**
     * @param callable(): array<mixed> $loader
     * @return array<mixed>
     */
    private function loadWithSingleFlight(string $fullKey, callable $loader): array
    {
        $store = $this->store->getStore();

        if (! $store instanceof LockProvider) {
            return $this->loadAndPut($fullKey, $loader);
        }

        try {
            $lock = $store->lock("lock:{$fullKey}", $this->lockTtl);
            $lock->block($this->lockWait);
        } catch (\Throwable) {
            return $loader();
        }

        try {
            return $this->cachedArray($fullKey) ?? $this->loadAndPut($fullKey, $loader);
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Expires after lock_ttl.
            }
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function cachedArray(string $fullKey): ?array
    {
        try {
            $cached = $this->store->get($fullKey);
        } catch (\Throwable) {
            return null;
        }

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param callable(): array<mixed> $loader
     * @return array<mixed>
     */
    private function loadAndPut(string $fullKey, callable $loader): array
    {
        $value = $loader();

        try {
            $this->store->put($fullKey, $value, $this->jitteredTtl());
        } catch (\Throwable) {
            // Served uncached.
        }

        return $value;
    }

    private function jitteredTtl(): int
    {
        $spread = (int) round($this->ttl * $this->jitter);

        return $spread > 0 ? $this->ttl + random_int(0, $spread) : $this->ttl;
    }

    private function fullKey(string $namespace, string $key): string
    {
        return "{$namespace}:{$key}:gen={$this->generation($namespace)}";
    }

    private function generationKey(string $namespace): string
    {
        return "{$namespace}:gen-counter";
    }

    private function generation(string $namespace): int
    {
        try {
            return (int) ($this->store->get($this->generationKey($namespace)) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
