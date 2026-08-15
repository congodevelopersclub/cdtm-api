<?php

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_profiles(): void
    {
        Profile::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/profiles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'headline', 'skills', 'projects']
                ]
            ]);
    }

    public function test_can_show_profile(): void
    {
        $profile = Profile::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/profiles/{$profile->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $profile->id);
    }

    public function test_store_returns_not_implemented(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/profiles')->assertStatus(501);
    }

    public function test_destroy_returns_not_implemented(): void
    {
        $profile = Profile::factory()->create();
        $this->actingAs($this->user)->deleteJson("/api/v1/profiles/{$profile->id}")->assertStatus(501);
    }

    public function test_can_update_profile(): void
    {
        $profile = Profile::factory()->create();

        $response = $this->actingAs($this->user)->patchJson("/api/v1/profiles/{$profile->id}", [
            'name' => 'John Updated',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_validate_profile(): void
    {
        $profile = Profile::factory()->create();

        $response = $this->actingAs($this->user)->putJson("/api/v1/profiles/{$profile->id}/validate", [
            'account_status' => \App\Enums\ProfileAccountStatus::VALIDATED->value,
        ]);

        $response->assertStatus(200);
    }
}