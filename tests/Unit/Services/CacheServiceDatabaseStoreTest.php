<?php

namespace Tests\Unit\Services;

use App\Services\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceDatabaseStoreTest extends TestCase
{
    use RefreshDatabase;

    private function databaseStoreService(): CacheService
    {
        return new CacheService(Cache::store('database'), 60, 0.2, lockTtl: 10, lockWait: 3);
    }

    public function test_the_first_invalidate_makes_the_next_remember_call_reload(): void
    {
        $cache = $this->databaseStoreService();
        $cache->remember('profiles', 'show:1', fn () => ['v' => 'old']);

        $cache->invalidate('profiles');

        $this->assertSame(['v' => 'new'], $cache->remember('profiles', 'show:1', fn () => ['v' => 'new']));
    }

    public function test_every_later_invalidate_also_makes_the_next_remember_call_reload(): void
    {
        $cache = $this->databaseStoreService();

        foreach (['a', 'b', 'c'] as $version) {
            $cache->invalidate('profiles');

            $this->assertSame(['v' => $version], $cache->remember('profiles', 'show:1', fn () => ['v' => $version]));
        }

        $this->assertSame(3, (int) Cache::store('database')->get('profiles:gen-counter'));
    }
}
