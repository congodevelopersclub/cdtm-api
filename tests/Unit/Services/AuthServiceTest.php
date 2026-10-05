<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidOAuthCodeException;
use App\Mail\WelcomeUserMail;
use App\Models\Profile;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AuthService::class);
    }

    private function fakeSocialiteUser(
        string $id,
        string $email,
        string $name = 'Jane Doe',
        string $avatar = 'https://example.com/avatar.jpg'
    ): SocialiteUser {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);
        $user->shouldReceive('getAvatar')->andReturn($avatar);

        return $user;
    }

    // --- linkedIdAuthenticate ---------------------------------------------

    public function test_linked_id_authenticate_returns_the_socialite_user(): void
    {
        $fakeUser = $this->fakeSocialiteUser('li-123', 'jane@example.com');

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($fakeUser);

        Socialite::shouldReceive('driver')->with('linkedin-openid')->once()->andReturn($driver);

        $this->assertSame($fakeUser, $this->service->linkedIdAuthenticate());
    }

    // --- signUpOrLogin ------------------------------------------------------

    public function test_creates_a_new_user_and_profile_when_no_match_exists(): void
    {
        Mail::fake();

        $linkedInUser = $this->fakeSocialiteUser('li-999', 'newperson@example.com');

        $result = $this->service->signUpOrLogin($linkedInUser);

        $this->assertArrayHasKey('one_time_code', $result);
        $this->assertNotEmpty($result['one_time_code']);

        $this->assertDatabaseHas('users', [
            'email' => 'newperson@example.com',
            'linkedin_id' => 'li-999',
        ]);

        $user = User::where('email', 'newperson@example.com')->firstOrFail();

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'account_status' => 'PENDING_VALIDATION',
        ]);

        $cached = Cache::get("oauth_code:{$result['one_time_code']}");
        $this->assertNotNull($cached);
        $this->assertEquals($user->id, $cached['user_id']);
        $this->assertNotEmpty($cached['token']);

        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user) {
            return $mail->user->id === $user->id && $mail->hasTo($user->email);
        });
    }

    public function test_links_linkedin_id_to_an_existing_user_matched_by_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'matched@example.com',
            'linkedin_id' => null,
        ]);

        $linkedInUser = $this->fakeSocialiteUser('li-555', 'matched@example.com');

        $this->service->signUpOrLogin($linkedInUser);

        $this->assertSame('li-555', $existing->refresh()->linkedin_id);
        $this->assertSame(1, User::where('email', 'matched@example.com')->count());
    }

    public function test_does_not_overwrite_linkedin_id_when_already_set(): void
    {
        $existing = User::factory()->create([
            'email' => 'already@example.com',
            'linkedin_id' => 'li-original',
        ]);

        // Same linkedin_id, different email on the socialite payload just to
        // prove the existing record (matched by linkedin_id) is reused as-is.
        $linkedInUser = $this->fakeSocialiteUser('li-original', 'different@example.com');

        $this->service->signUpOrLogin($linkedInUser);

        $this->assertSame('li-original', $existing->refresh()->linkedin_id);
        $this->assertSame(1, User::count());
    }

    public function test_issues_a_fresh_token_and_revokes_previous_ones(): void
    {
        $existing = User::factory()->create([
            'email' => 'tokened@example.com',
            'linkedin_id' => 'li-token',
        ]);
        $existing->createToken('old-token');
        $this->assertSame(1, $existing->tokens()->count());

        $linkedInUser = $this->fakeSocialiteUser('li-token', 'tokened@example.com');

        $this->service->signUpOrLogin($linkedInUser);

        $this->assertSame(1, $existing->refresh()->tokens()->count());
    }

    // --- exchangeOneTimeCode -------------------------------------------------

    public function test_exchanges_a_valid_code_for_the_user_and_token(): void
    {
        $user = User::factory()->has(Profile::factory())->create();
        $token = $user->createToken('api')->plainTextToken;

        Cache::put('oauth_code:abc123', [
            'user_id' => $user->id,
            'token' => $token,
        ], now()->addMinutes(2));

        $result = $this->service->exchangeOneTimeCode('abc123');

        $this->assertSame($token, $result['token']);
        $this->assertTrue($result['user']->is($user));
        $this->assertTrue($result['user']->relationLoaded('profile'));

        // one-time code must be consumed
        $this->assertNull(Cache::get('oauth_code:abc123'));
    }

    public function test_throws_invalid_oauth_code_exception_when_code_is_missing_or_expired(): void
    {
        $this->expectException(InvalidOAuthCodeException::class);

        $this->service->exchangeOneTimeCode('does-not-exist');
    }

    public function test_throws_model_not_found_when_the_cached_user_no_longer_exists(): void
    {
        Cache::put('oauth_code:ghost', [
            'user_id' => 999999, // adjust to match your PK type (int/uuid)
            'token' => 'irrelevant',
        ], now()->addMinutes(2));

        $this->expectException(ModelNotFoundException::class);

        $this->service->exchangeOneTimeCode('ghost');
    }
}
