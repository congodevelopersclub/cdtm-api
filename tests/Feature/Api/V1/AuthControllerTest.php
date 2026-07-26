<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\InvalidOAuthCodeException;
use App\Models\Profile;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT_URI = '/api/v1/auth';
    private const SIGNUP_URI = '/api/v1/auth/linkedin/signup';
    private const EXCHANGE_URI = '/api/v1/auth/exchange-code';
    private const SHOW_URI = '/api/v1/users';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'https://frontend.example.com']);
    }

    private function fakeSocialiteUser(?string $email, string $id = 'li-1'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getName')->andReturn('Jane Doe');
        $user->shouldReceive('getAvatar')->andReturn('https://example.com/a.jpg');

        return $user;
    }

    // --- redirect() -----------------------------------------------------

    public function test_redirect_sends_the_user_to_linkedin(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(
            redirect()->away('https://linkedin.com/oauth/v2/authorization?response_type=code&client_id=123')
        );

        Socialite::shouldReceive('driver')->with('linkedin-openid')->once()->andReturn($driver);

        $response = $this->get(self::REDIRECT_URI);

        $response->assertRedirect('https://linkedin.com/oauth/v2/authorization?response_type=code&client_id=123');
    }

    // --- signUp() ---------------------------------------------------------

    public function test_signup_redirects_to_frontend_with_code_on_success(): void
    {
        $linkedInUser = $this->fakeSocialiteUser('jane@example.com');

        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('linkedIdAuthenticate')->once()->andReturn($linkedInUser);
        $mockAuthService->shouldReceive('signUpOrLogin')
            ->once()
            ->with($linkedInUser)
            ->andReturn(['one_time_code' => 'abc123']);

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->get(self::SIGNUP_URI);

        $response->assertRedirect('https://frontend.example.com/auth/callback?code=abc123');
    }

    public function test_signup_redirects_with_error_when_linkedin_authentication_throws(): void
    {
        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('linkedIdAuthenticate')->once()->andThrow(new \Exception('boom'));
        $mockAuthService->shouldNotReceive('signUpOrLogin');

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->get(self::SIGNUP_URI);

        $response->assertRedirect(
            'https://frontend.example.com/auth/callback?error=' . urlencode('AUTH_FAILED')
        );
    }

    public function test_signup_redirects_with_error_when_linkedin_email_is_missing(): void
    {
        $linkedInUser = $this->fakeSocialiteUser(null);

        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('linkedIdAuthenticate')->once()->andReturn($linkedInUser);
        $mockAuthService->shouldNotReceive('signUpOrLogin');

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->get(self::SIGNUP_URI);

        $response->assertRedirect(
            'https://frontend.example.com/auth/callback?error=' . urlencode('AUTH_FAILED')
        );
    }

    public function test_signup_redirects_with_error_when_linkedin_email_is_empty_string(): void
    {
        $linkedInUser = $this->fakeSocialiteUser('');

        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('linkedIdAuthenticate')->once()->andReturn($linkedInUser);
        $mockAuthService->shouldNotReceive('signUpOrLogin');

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->get(self::SIGNUP_URI);

        $response->assertRedirect(
            'https://frontend.example.com/auth/callback?error=' . urlencode('AUTH_FAILED')
        );
    }

    public function test_signup_redirects_with_error_when_sign_up_or_login_throws(): void
    {
        $linkedInUser = $this->fakeSocialiteUser('jane@example.com');

        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('linkedIdAuthenticate')->once()->andReturn($linkedInUser);
        $mockAuthService->shouldReceive('signUpOrLogin')->once()->andThrow(new \Exception('db down'));

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->get(self::SIGNUP_URI);

        $response->assertRedirect(
            'https://frontend.example.com/auth/callback?error=' . urlencode('AUTH_FAILED')
        );
    }

    // --- exchangeCode() -----------------------------------------------------

    public function test_exchange_code_requires_a_code(): void
    {
        $response = $this->postJson(self::EXCHANGE_URI, []);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_exchange_code_returns_token_and_user_on_success(): void
    {
        $user = User::factory()->has(Profile::factory())->create();

        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('exchangeOneTimeCode')
            ->once()
            ->with('abc123')
            ->andReturn(['token' => 'plain-text-token', 'user' => $user->load('profile')]);

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->postJson(self::EXCHANGE_URI, ['code' => 'abc123']);

        $response->assertStatus(200)
            ->assertJson([
                'token' => 'plain-text-token',
                'user' => ['id' => $user->id, 'email' => $user->email],
            ]);
    }

    public function test_exchange_code_returns_401_for_invalid_or_expired_code(): void
    {
        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('exchangeOneTimeCode')
            ->once()
            ->andThrow(new InvalidOAuthCodeException());

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->postJson(self::EXCHANGE_URI, ['code' => 'expired']);

        $response->assertStatus(401);
    }

    public function test_exchange_code_returns_404_when_user_no_longer_exists(): void
    {
        $mockAuthService = Mockery::mock(AuthService::class);
        $mockAuthService->shouldReceive('exchangeOneTimeCode')
            ->once()
            ->andThrow(new ModelNotFoundException('User not found'));

        $this->app->instance(AuthService::class, $mockAuthService);

        $response = $this->postJson(self::EXCHANGE_URI, ['code' => 'ghost']);

        $response->assertStatus(404);
    }

    // --- show() ---------------------------------------------------------

    public function test_show_returns_the_user_with_profile_loaded(): void
    {
        $user = User::factory()->has(Profile::factory())->create();

        $response = $this->getJson(self::SHOW_URI . '/' . $user->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'profile' => ['id' => $user->profile->id],
                ],
            ]);
    }

    public function test_show_returns_404_for_a_nonexistent_user(): void
    {
        $response = $this->getJson(self::SHOW_URI . '/999999');

        $response->assertStatus(404);
    }
}
