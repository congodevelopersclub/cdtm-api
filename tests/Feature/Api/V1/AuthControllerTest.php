<?php

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SIGN_UP_ROUTE = '/api/v1/auth/linkedin/signup';
    private const SHOW_ROUTE_PREFIX = '/api/v1/auth';

    private function makeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $data = array_merge([
            'id'    => 'linkedin-123',
            'name'  => 'Jane Doe',
            'email' => 'jane@example.com',
        ], $overrides);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($data['id']);
        $socialiteUser->shouldReceive('getName')->andReturn($data['name']);
        $socialiteUser->shouldReceive('getEmail')->andReturn($data['email']);

        return $socialiteUser;
    }

    // ---------------------------------------------------------------
    // POST signUp
    // ---------------------------------------------------------------

    #[Test]
    public function sign_up_returns_421_when_linkedin_authentication_fails(): void
    {
        $this->mock(AuthService::class, function ($mock) {
            $mock->shouldReceive('linkedIdAuthenticate')
                ->once()
                ->andThrow(new \Exception('LinkedIn OAuth error'));
        });

        $response = $this->getJson(self::SIGN_UP_ROUTE);

        $response->assertStatus(421);
        $response->assertJson([
            'message' => 'Could not authenticate with LinkedIn.',
        ]);
    }

    #[Test]
    public function sign_up_returns_422_when_linkedin_does_not_return_an_email(): void
    {
        $linkedInUser = $this->makeSocialiteUser(['email' => null]);

        $this->mock(AuthService::class, function ($mock) use ($linkedInUser) {
            $mock->shouldReceive('linkedIdAuthenticate')
                ->once()
                ->andReturn($linkedInUser);

            $mock->shouldNotReceive('signUpOrLogin');
        });

        $response = $this->getJson(self::SIGN_UP_ROUTE);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'LinkedIn did not return an email address.',
        ]);
    }

    #[Test]
    public function sign_up_returns_201_and_a_token_for_a_brand_new_user(): void
    {
        $linkedInUser = $this->makeSocialiteUser();
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->mock(AuthService::class, function ($mock) use ($linkedInUser, $user) {
            $mock->shouldReceive('linkedIdAuthenticate')
                ->once()
                ->andReturn($linkedInUser);

            $mock->shouldReceive('signUpOrLogin')
                ->once()
                ->with($linkedInUser)
                ->andReturn([
                    'user'        => $user,
                    'token'       => 'fake-plain-text-token',
                    'is_new_user' => true,
                ]);
        });

        $response = $this->getJson(self::SIGN_UP_ROUTE);

        $response->assertStatus(201);
        $response->assertJsonStructure(['user', 'token']);
        $response->assertJson([
            'token' => 'fake-plain-text-token',
        ]);
    }

    #[Test]
    public function sign_up_returns_200_and_a_token_for_a_returning_user(): void
    {
        $linkedInUser = $this->makeSocialiteUser();
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->mock(AuthService::class, function ($mock) use ($linkedInUser, $user) {
            $mock->shouldReceive('linkedIdAuthenticate')
                ->once()
                ->andReturn($linkedInUser);

            $mock->shouldReceive('signUpOrLogin')
                ->once()
                ->with($linkedInUser)
                ->andReturn([
                    'user'        => $user,
                    'token'       => 'fake-plain-text-token',
                    'is_new_user' => false,
                ]);
        });

        $response = $this->getJson(self::SIGN_UP_ROUTE);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    }

    #[Test]
    public function sign_up_returns_500_when_sign_up_or_login_throws(): void
    {
        $linkedInUser = $this->makeSocialiteUser();

        $this->mock(AuthService::class, function ($mock) use ($linkedInUser) {
            $mock->shouldReceive('linkedIdAuthenticate')
                ->once()
                ->andReturn($linkedInUser);

            $mock->shouldReceive('signUpOrLogin')
                ->once()
                ->andThrow(new \Exception('DB exploded'));
        });

        $response = $this->getJson(self::SIGN_UP_ROUTE);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Something went wrong when signing in or refreshing the user.',
        ]);
    }

    #[Test]
    public function sign_up_never_calls_sign_up_or_login_if_authentication_throws(): void
    {
        $this->mock(AuthService::class, function ($mock) {
            $mock->shouldReceive('linkedIdAuthenticate')
                ->once()
                ->andThrow(new \Exception('boom'));

            $mock->shouldNotReceive('signUpOrLogin');
        });

        $this->getJson(self::SIGN_UP_ROUTE);
    }

    // // ---------------------------------------------------------------
    // // GET show
    // // ---------------------------------------------------------------

    #[Test]
    public function show_returns_the_user_with_their_profile_loaded(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id] + (
            []
        ));

        $response = $this->getJson(self::SHOW_ROUTE_PREFIX . '/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'profile',
            ],
        ]);
        $response->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function show_returns_404_for_a_nonexistent_user(): void
    {
        $response = $this->getJson(self::SHOW_ROUTE_PREFIX . '/999999');

        $response->assertStatus(404);
    }

    #[Test]
    public function show_does_not_expose_the_users_password_hash(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(self::SHOW_ROUTE_PREFIX . '/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonMissingPath('data.password');
    }

}
