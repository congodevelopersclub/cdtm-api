<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

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
