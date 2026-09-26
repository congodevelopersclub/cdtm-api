<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheService;
use App\Models\Category;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $profileService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileService = new ProfileService();
    }

    public function test_list_returns_paginated_data_without_url_fields(): void
    {
        Profile::factory()->count(3)->create();

        $result = $this->profileService->list(1);

        $this->assertCount(3, $result['data']);
        $this->assertSame(3, $result['total']);
        $this->assertSame(20, $result['per_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertArrayNotHasKey('next_page_url', $result);
        $this->assertArrayNotHasKey('path', $result);
    }

    public function test_list_serves_the_second_call_from_cache_without_requerying(): void
    {
        Profile::factory()->count(2)->create();

        $this->profileService->list(1);

        \DB::enableQueryLog();
        $second = $this->profileService->list(1);
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertCount(2, $second['data']);
        $this->assertSame(0, $queryCount);
    }

    public function test_list_keys_each_page_separately(): void
    {
        Profile::factory()->count(25)->create();

        $page1 = $this->profileService->list(1);
        $page2 = $this->profileService->list(2);

        $this->assertCount(20, $page1['data']);
        $this->assertCount(5, $page2['data']);
        $this->assertNotSame($page1['data'], $page2['data']);
    }

    public function test_show_returns_the_profile_with_relations_as_an_array(): void
    {
        $category = Category::factory()->create();
        $profile = Profile::factory()->create(['category_id' => $category->id]);

        $result = $this->profileService->show($profile);

        $this->assertSame($profile->id, $result['id']);
        $this->assertSame($category->id, $result['category']['id']);
    }

    public function test_show_serves_the_second_call_from_cache_without_requerying(): void
    {
        $profile = Profile::factory()->create();

        $this->profileService->show($profile);

        \DB::enableQueryLog();
        $second = $this->profileService->show($profile);
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertSame($profile->id, $second['id']);
        $this->assertSame(0, $queryCount);
    }

    public function test_updating_only_projects_is_reflected_immediately_despite_resending_the_same_name(): void
    {
        $profile = Profile::factory()->create(['name' => 'Unchanged Name']);
        $this->profileService->show($profile); // prime the cache

        $this->profileService->updateProfile($profile, [
            'name' => 'Unchanged Name',
            'projects' => [['title' => 'Brand New Project', 'description' => 'Freshly created']],
        ]);

        $result = $this->profileService->show($profile->fresh());

        $this->assertCount(1, $result['projects']);
        $this->assertSame('Brand New Project', $result['projects'][0]['title']);
    }

    public function test_show_does_not_cache_a_model_that_was_bound_before_a_write(): void
    {
        $profile = Profile::factory()->create(['bio' => 'old']);
        $bound = Profile::findOrFail($profile->id);

        Profile::whereKey($profile->id)->update(['bio' => 'new']);
        CacheService::make()->invalidate('profiles');

        $this->assertSame('new', $this->profileService->show($bound)['bio']);
    }

    public function test_a_malformed_cached_index_entry_is_treated_as_a_miss(): void
    {
        Profile::factory()->count(2)->create();
        $gen = (int) Cache::store('array')->get('profiles:gen-counter');
        Cache::store('array')->put("profiles:index:page=1:gen={$gen}", ['unexpected' => true], 60);

        $this->assertSame(2, $this->profileService->list(1)['total']);
    }

    public function test_a_cached_show_entry_for_another_profile_is_treated_as_a_miss(): void
    {
        $profile = Profile::factory()->create();
        $gen = (int) Cache::store('array')->get('profiles:gen-counter');
        Cache::store('array')->put("profiles:show:{$profile->id}:gen={$gen}", ['id' => 'someone-else'], 60);

        $this->assertSame($profile->id, $this->profileService->show($profile)['id']);
    }
}
