<?php

namespace App\Observers;

use App\Models\Skill;
use App\Services\Cache\CacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SkillObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Skill $skill): void
    {
        CacheService::make()->invalidate('profiles');
    }

    public function deleted(Skill $skill): void
    {
        CacheService::make()->invalidate('profiles');
    }
}
