<?php

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/users';

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_show_returns_the_user_with_profile_loaded(): void
    {
        $user = User::factory()->has(Profile::factory())->create();

        $response = $this->getJson(self::ENDPOINT . '/' . $user->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'profile' => [
                        'id' => $user->profile->id,
                    ],
                ],
            ]);
    }

    public function test_show_response_does_not_lazy_load_profile(): void
    {
        $user = User::factory()->has(Profile::factory())->create();

        \DB::enableQueryLog();

        $this->getJson(self::ENDPOINT . '/' . $user->id)->assertStatus(200);

        $queries = collect(\DB::getQueryLog())->pluck('query')->implode(' | ');

        $this->assertSame(2, \DB::getQueryLog() ? count(\DB::getQueryLog()) : 0, $queries);

        \DB::disableQueryLog();
    }

    public function test_show_returns_user_without_a_profile(): void
    {
        $user = User::factory()->create(); // no profile attached

        $response = $this->getJson(self::ENDPOINT . '/' . $user->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'profile' => null,
                ],
            ]);
    }

    public function test_show_returns_404_for_a_nonexistent_user_id(): void
    {
        $response = $this->getJson(self::ENDPOINT . '/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User not found.',
                'code' => 'MODEL_NOT_FOUND',
            ]);
    }
}