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
            ->assertJsonPath('data.category_id', $category->id);
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
}
