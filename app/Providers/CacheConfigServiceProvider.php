<?php

namespace App\Providers;

use App\Services\Cache\CacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;

class CacheConfigServiceProvider extends ServiceProvider
{
    private const RECOVERY_COMMANDS = ['config:clear', 'config:show', 'package:discover'];

    public function boot(): void
    {
        try {
            CacheService::configuredStore();
        } catch (\Throwable $e) {
            $message = 'Invalid CDTM_CACHE_STORE, profile caching is disabled: '.$e->getMessage();

            if ($this->app->runningInConsole() && ! in_array((new ArgvInput())->getFirstArgument(), self::RECOVERY_COMMANDS, true)) {
                throw new RuntimeException($message, 0, $e);
            }

            Log::error($message);
        }
    }
}
