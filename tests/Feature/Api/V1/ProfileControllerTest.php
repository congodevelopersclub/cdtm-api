<?php

namespace Tests\Feature\Api\V1;

use App\Enums\{ ProfileAccountStatus, UserRole };
use App\Models\{ Category, Profile, User };
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_can_list_profiles(): void
    {
        Profile::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/profiles');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email'],
                ],
            ]);
    }

    public function test_can_show_profile_with_relations(): void
    {
        $category = Category::factory()->create();
        $profile = Profile::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson("/api/v1/profiles/{$profile->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $profile->id)
            ->assertJsonPath('data.category.id', $category->id);
    }

    public function test_show_returns_404_for_missing_profile(): void
    {
        $response = $this->getJson('/api/v1/profiles/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_can_update_profile(): void
    {
        $category = Category::factory()->create();
        $profile = Profile::factory()->create(['name' => 'Old Name']);

        $response = $this->patchJson("/api/v1/profiles/{$profile->id}", [
            'name' => 'New Name',
            'bio' => 'Updated bio',
            'category_id' => $category->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.bio', 'Updated bio')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', $category->name)
            ->assertJsonPath('data.category.slug', $category->slug);
    }

    public function test_update_profile_requires_name(): void
    {
        $profile = Profile::factory()->create();

        $response = $this->patchJson("/api/v1/profiles/{$profile->id}", [
            'bio' => 'Only bio',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_validate_profile(): void
    {
        $profile = Profile::factory()->create([
            'account_status' => ProfileAccountStatus::PENDING_VALIDATION,
        ]);

        $response = $this->putJson("/api/v1/profiles/{$profile->id}/validate", [
            'account_status' => ProfileAccountStatus::VALIDATED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.account_status', ProfileAccountStatus::VALIDATED->value);
    }

    public function test_validate_profile_requires_account_status(): void
    {
        $profile = Profile::factory()->create();

        $response = $this->putJson("/api/v1/profiles/{$profile->id}/validate", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_status']);
    }

    public function test_store_returns_not_implemented(): void
    {
        $response = $this->postJson('/api/v1/profiles', []);

        $response->assertStatus(501)
            ->assertJsonPath('code', 'NOT_IMPLEMENTED');
    }

    public function test_destroy_returns_not_implemented(): void
    {
        $profile = Profile::factory()->create();

        $response = $this->deleteJson("/api/v1/profiles/{$profile->id}");

        $response->assertStatus(501)
            ->assertJsonPath('code', 'NOT_IMPLEMENTED');
    }

    public function test_update_profile_updates_allowed_fields(): void
    {
        $user = User::factory()->has(Profile::factory())->create(['role' => UserRole::Admin]);
        $profile = $user->profile;

        $response = $this->actingAs($user)->patchJson("/api/v1/profiles/{$profile->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
    }

    public function test_owner_can_disable_their_profile(): void
    {
        $owner = User::factory()->create();
        $profile = Profile::factory()->validated()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/profiles/{$profile->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.account_status', ProfileAccountStatus::DISABLED->value)
            ->assertJsonMissingPath('data.account_status_before_disabled');

        $profile->refresh();
        $this->assertSame(ProfileAccountStatus::DISABLED, $profile->account_status);
        $this->assertSame(ProfileAccountStatus::VALIDATED, $profile->account_status_before_disabled);
    }

    public function test_user_cannot_disable_someone_else_profile(): void
    {
        $profile = Profile::factory()->validated()->create();

        $this->putJson("/api/v1/profiles/{$profile->id}/disable")
            ->assertForbidden();

        $this->assertSame(ProfileAccountStatus::VALIDATED, $profile->refresh()->account_status);
    }

    public function test_guest_cannot_disable_a_profile(): void
    {
        $profile = Profile::factory()->create();

        $this->app['auth']->forgetGuards();

        $this->putJson("/api/v1/profiles/{$profile->id}/disable")
            ->assertUnauthorized();
    }

    public function test_disabling_twice_keeps_the_original_status(): void
    {
        $owner = User::factory()->create();
        $profile = Profile::factory()->rejected()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/profiles/{$profile->id}/disable")->assertOk();
        $this->putJson("/api/v1/profiles/{$profile->id}/disable")->assertOk();

        $this->assertSame(ProfileAccountStatus::REJECTED, $profile->refresh()->account_status_before_disabled);
    }

    public function test_owner_can_enable_their_profile_and_get_back_the_previous_status(): void
    {
        $owner = User::factory()->create();
        $profile = Profile::factory()->disabled(ProfileAccountStatus::VALIDATED)->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/profiles/{$profile->id}/enable")
            ->assertOk()
            ->assertJsonPath('data.account_status', ProfileAccountStatus::VALIDATED->value);

        $this->assertNull($profile->refresh()->account_status_before_disabled);
    }

    public function test_enabling_a_profile_that_is_not_disabled_changes_nothing(): void
    {
        $owner = User::factory()->create();
        $profile = Profile::factory()->pendingValidation()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/profiles/{$profile->id}/enable")
            ->assertOk()
            ->assertJsonPath('data.account_status', ProfileAccountStatus::PENDING_VALIDATION->value);
    }

    public function test_user_cannot_enable_someone_else_profile(): void
    {
        $profile = Profile::factory()->disabled()->create();

        $this->putJson("/api/v1/profiles/{$profile->id}/enable")
            ->assertForbidden();

        $this->assertSame(ProfileAccountStatus::DISABLED, $profile->refresh()->account_status);
    }

    public function test_disabled_profiles_are_not_listed(): void
    {
        $visible = Profile::factory()->validated()->create();
        Profile::factory()->disabled()->create();

        $this->getJson('/api/v1/profiles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_disabled_profiles_do_not_appear_in_search_results(): void
    {
        $visible = Profile::factory()->validated()->create(['location' => 'Brazzaville']);
        Profile::factory()->disabled()->create(['location' => 'Brazzaville']);

        $this->getJson('/api/v1/profiles/search?location=Brazzaville')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_disabled_profile_page_is_hidden_from_other_users(): void
    {
        $profile = Profile::factory()->disabled()->create();

        $this->getJson("/api/v1/profiles/{$profile->id}")
            ->assertNotFound();
    }

    public function test_owner_can_still_see_their_disabled_profile(): void
    {
        $owner = User::factory()->create();
        $profile = Profile::factory()->disabled()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.account_status', ProfileAccountStatus::DISABLED->value);
    }

    public function test_validate_endpoint_cannot_be_used_to_disable_a_profile(): void
    {
        $profile = Profile::factory()->validated()->create();

        $this->putJson("/api/v1/profiles/{$profile->id}/validate", [
            'account_status' => ProfileAccountStatus::DISABLED->value,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_status']);
    }
}
