<?php

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_list_profiles(): void
    {
        Profile::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/profiles');

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

        $response = $this->getJson("/api/v1/profiles/{$profile->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $profile->id);
    }
}