<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Cache\CacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CategoryObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Category $category): void
    {
        CacheService::make()->invalidate('profiles');
    }

    public function deleted(Category $category): void
    {
        CacheService::make()->invalidate('profiles');
    }
}
