<?php

namespace Tests\Unit\Services;

use App\Providers\CacheConfigServiceProvider;
use App\Services\Cache\CacheService;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CacheConfigValidationTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function badStores(): array
    {
        return [
            'typo' => ['redsi'],
            'wrong case' => ['Redis'],
            'empty' => [''],
            'whitespace' => ['   '],
            'null, which would otherwise resolve to the default store' => [null],
        ];
    }

    #[DataProvider('badStores')]
    public function test_a_bad_store_is_rejected(mixed $store): void
    {
        config(['cdtm-cache.store' => $store]);

        $this->expectException(\Throwable::class);

        CacheService::configuredStore();
    }

    public function test_a_defined_store_whose_driver_cannot_be_built_is_rejected(): void
    {
        config(['cache.stores.broken' => ['driver' => 'no-such-driver'], 'cdtm-cache.store' => 'broken']);

        $this->expectException(\Throwable::class);

        CacheService::configuredStore();
    }

    public function test_with_a_bad_store_the_service_runs_with_caching_off(): void
    {
        config(['cdtm-cache.store' => 'redsi']);
        $cache = CacheService::make();
        $loads = 0;
        $loader = function () use (&$loads) {
            $loads++;

            return ['v' => $loads];
        };

        $cache->remember('profiles', 'show:1', $loader);
        $second = $cache->remember('profiles', 'show:1', $loader);
        $cache->invalidate('profiles');

        $this->assertSame(['v' => 2], $second);
        $this->assertSame(2, $loads);
    }

    public function test_a_console_command_fails_at_boot_on_a_bad_store(): void
    {
        config(['cdtm-cache.store' => 'redsi']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid CDTM_CACHE_STORE');

        (new CacheConfigServiceProvider($this->app))->boot();
    }

    public function test_a_recovery_command_only_logs_a_bad_store(): void
    {
        config(['cdtm-cache.store' => 'redsi']);
        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = ['artisan', 'config:clear'];
        Log::shouldReceive('error')->once()->with(\Mockery::pattern('/Invalid CDTM_CACHE_STORE/'));

        try {
            (new CacheConfigServiceProvider($this->app))->boot();
        } finally {
            $_SERVER['argv'] = $argv;
        }
    }

    public function test_a_valid_store_boots_silently(): void
    {
        Log::shouldReceive('error')->never();

        (new CacheConfigServiceProvider($this->app))->boot();

        $this->addToAssertionCount(1);
    }

    public function test_a_recovery_command_after_a_global_option_only_logs_a_bad_store(): void
    {
        config(['cdtm-cache.store' => 'redsi']);
        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = ['artisan', '-q', 'config:clear'];
        Log::shouldReceive('error')->once();

        try {
            (new CacheConfigServiceProvider($this->app))->boot();
        } finally {
            $_SERVER['argv'] = $argv;
        }
    }
}
