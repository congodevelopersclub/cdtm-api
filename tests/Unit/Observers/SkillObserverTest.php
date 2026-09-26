<?php

namespace Tests\Unit\Observers;

use App\Models\Skill;
use App\Services\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_skill_invalidates_the_profiles_cache(): void
    {
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        Skill::factory()->create();

        $result = $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after']);

        $this->assertSame('after', $result['loaded_at']);
    }

    public function test_updating_a_skill_invalidates_the_profiles_cache(): void
    {
        $skill = Skill::factory()->create();
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        $skill->update(['name' => 'Renamed Skill']);

        $result = $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after']);

        $this->assertSame('after', $result['loaded_at']);
    }

    public function test_deleting_a_skill_invalidates_the_profiles_cache(): void
    {
        $skill = Skill::factory()->create();
        $cache = CacheService::make();
        $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'before']);

        $skill->delete();

        $result = $cache->remember('profiles', 'probe', fn () => ['loaded_at' => 'after']);

        $this->assertSame('after', $result['loaded_at']);
    }
}
