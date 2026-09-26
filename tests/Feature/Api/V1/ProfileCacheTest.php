<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ProfileAccountStatus;
use App\Models\{Category, Profile, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_repeated_index_requests_do_not_requery_the_database(): void
    {
        Profile::factory()->count(2)->create();

        $this->getJson('/api/v1/profiles')->assertOk();

        \DB::enableQueryLog();
        $second = $this->getJson('/api/v1/profiles');
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $second->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(0, $queryCount);
    }

    public function test_repeated_show_requests_do_not_requery_the_relations(): void
    {
        $profile = Profile::factory()->create();

        $this->getJson("/api/v1/profiles/{$profile->id}")->assertOk();

        \DB::enableQueryLog();
        $second = $this->getJson("/api/v1/profiles/{$profile->id}");
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $second->assertOk()->assertJsonPath('data.id', $profile->id);
        // The route-model-binding lookup still runs on a hit.
        $this->assertSame(1, $queryCount);
    }

    public function test_index_returns_distinct_data_across_multiple_pages(): void
    {
        Profile::factory()->count(25)->create();

        $page1 = $this->getJson('/api/v1/profiles?page=1')->assertOk();
        $page2 = $this->getJson('/api/v1/profiles?page=2')->assertOk();

        $page1->assertJsonCount(20, 'data');
        $page2->assertJsonCount(5, 'data');

        $page1Ids = collect($page1->json('data'))->pluck('id');
        $page2Ids = collect($page2->json('data'))->pluck('id');

        $this->assertEmpty($page1Ids->intersect($page2Ids));
    }

    public function test_index_links_are_full_urls_for_the_current_request_on_a_cache_hit(): void
    {
        Profile::factory()->count(25)->create();

        $this->getJson('http://a.test/api/v1/profiles?page=1')->assertOk();

        $this->getJson('http://a.test/api/v1/profiles?page=1')
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('path', 'http://a.test/api/v1/profiles')
            ->assertJsonPath('next_page_url', 'http://a.test/api/v1/profiles?page=2');
    }

    public function test_index_links_follow_the_requesting_host_not_the_host_that_populated_the_cache(): void
    {
        Profile::factory()->count(25)->create();

        $this->getJson('http://a.test/api/v1/profiles?page=1')->assertOk();

        $second = $this->getJson('http://b.test/api/v1/profiles?page=1')->assertOk();

        $second->assertJsonPath('next_page_url', 'http://b.test/api/v1/profiles?page=2')
            ->assertJsonPath('first_page_url', 'http://b.test/api/v1/profiles?page=1');
        $this->assertStringNotContainsString('a.test', (string) $second->getContent());
    }

    public function test_updating_a_profile_is_immediately_visible_in_a_subsequent_show_request(): void
    {
        $profile = Profile::factory()->create(['name' => 'Old Name']);

        $this->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Old Name');

        $this->patchJson("/api/v1/profiles/{$profile->id}", ['name' => 'New Name'])->assertOk();

        $this->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_writes_are_immediately_visible_when_the_cache_store_is_the_database(): void
    {
        config(['cdtm-cache.store' => 'database']);
        $profile = Profile::factory()->create(['name' => 'Old Name']);

        $this->getJson("/api/v1/profiles/{$profile->id}")->assertJsonPath('data.name', 'Old Name');
        $this->getJson('/api/v1/profiles')->assertJsonPath('data.0.name', 'Old Name');

        $this->patchJson("/api/v1/profiles/{$profile->id}", ['name' => 'New Name'])->assertOk();

        $this->getJson("/api/v1/profiles/{$profile->id}")->assertJsonPath('data.name', 'New Name');
        $this->getJson('/api/v1/profiles')->assertJsonPath('data.0.name', 'New Name');

        $this->putJson("/api/v1/profiles/{$profile->id}/validate", [
            'account_status' => ProfileAccountStatus::VALIDATED->value,
        ])->assertOk();

        $this->getJson("/api/v1/profiles/{$profile->id}")
            ->assertJsonPath('data.account_status', ProfileAccountStatus::VALIDATED->value);
    }

    public function test_profile_reads_and_writes_still_work_with_a_misconfigured_store(): void
    {
        config(['cdtm-cache.store' => 'redsi']);
        $profile = Profile::factory()->create(['name' => 'Old Name']);

        $this->getJson('/api/v1/profiles')->assertOk();
        $this->patchJson("/api/v1/profiles/{$profile->id}", ['name' => 'New Name'])->assertOk();

        $this->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_validating_a_profile_is_immediately_visible_in_a_subsequent_index_request(): void
    {
        $profile = Profile::factory()->create(['account_status' => ProfileAccountStatus::PENDING_VALIDATION]);

        $this->getJson('/api/v1/profiles')->assertOk();

        $this->putJson("/api/v1/profiles/{$profile->id}/validate", [
            'account_status' => ProfileAccountStatus::VALIDATED->value,
        ])->assertOk();

        $response = $this->getJson('/api/v1/profiles')->assertOk();

        $updated = collect($response->json('data'))->firstWhere('id', $profile->id);
        $this->assertSame(ProfileAccountStatus::VALIDATED->value, $updated['account_status']);
    }

    public function test_renaming_or_deleting_a_category_is_immediately_visible_in_the_profile(): void
    {
        $category = Category::factory()->create(['name' => 'Old Cat']);
        $profile = Profile::factory()->create(['category_id' => $category->id]);

        $this->getJson("/api/v1/profiles/{$profile->id}")->assertJsonPath('data.category.name', 'Old Cat');

        $this->putJson("/api/v1/categories/{$category->id}", ['name' => 'New Cat'])->assertOk();
        $this->getJson("/api/v1/profiles/{$profile->id}")->assertJsonPath('data.category.name', 'New Cat');

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();
        $this->getJson("/api/v1/profiles/{$profile->id}")->assertJsonPath('data.category', null);
    }

    public function test_a_malformed_page_value_falls_back_to_page_one(): void
    {
        Profile::factory()->count(25)->create();

        foreach (['2abc', '1e3', '99999999999999999999'] as $page) {
            $this->getJson("/api/v1/profiles?page={$page}")->assertOk()->assertJsonPath('current_page', 1);
        }
    }
}
