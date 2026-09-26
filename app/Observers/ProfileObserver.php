<?php

namespace App\Observers;

use App\Models\Profile;
use App\Services\Cache\CacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Profile $profile): void
    {
        CacheService::make()->invalidate('profiles');
    }

    public function deleted(Profile $profile): void
    {
        CacheService::make()->invalidate('profiles');
    }
}
