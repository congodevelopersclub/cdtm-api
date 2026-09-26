<?php

namespace Tests\Unit\Observers;

use Illuminate\Support\Facades\DB;
use App\Models\Profile;
use App\Services\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_profile_invalidates_the_profiles_cache(): void
    {
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        Profile::factory()->create();

        $result = $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after']);

        $this->assertSame('after', $result['loaded_at']);
    }

    public function test_updating_a_profile_invalidates_the_profiles_cache(): void
    {
        $profile = Profile::factory()->create();
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        $profile->update(['name' => 'Changed Name']);

        $result = $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after']);

        $this->assertSame('after', $result['loaded_at']);
    }

    public function test_deleting_a_profile_invalidates_the_profiles_cache(): void
    {
        $profile = Profile::factory()->create();
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        $profile->delete();

        $result = $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after']);

        $this->assertSame('after', $result['loaded_at']);
    }

    public function test_the_cache_is_invalidated_only_once_the_transaction_commits(): void
    {
        $profile = Profile::factory()->create();
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        DB::transaction(function () use ($profile, $cache) {
            $profile->update(['bio' => 'changed']);

            $this->assertSame('before', $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'inside'])['loaded_at']);
        });

        $this->assertSame('after', $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after'])['loaded_at']);
    }
}
