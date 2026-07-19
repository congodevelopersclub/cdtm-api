<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new AuthService();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Build a mock SocialiteUser with sensible defaults, overridable per test.
     */
    private function makeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $data = array_merge([
            'id'     => 'linkedin-123',
            'name'   => 'Jane Doe',
            'email'  => 'jane@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ], $overrides);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($data['id']);
        $socialiteUser->shouldReceive('getName')->andReturn($data['name']);
        $socialiteUser->shouldReceive('getEmail')->andReturn($data['email']);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($data['avatar']);

        return $socialiteUser;
    }

    #[Test]
    public function it_creates_a_new_user_and_profile_when_no_matching_user_exists(): void
    {
        $linkedInUser = $this->makeSocialiteUser([
            'id'    => 'linkedin-999',
            'email' => 'newuser@example.com',
            'name'  => 'New User',
        ]);

        $result = $this->authService->signUpOrLogin($linkedInUser);

        $this->assertTrue($result['is_new_user']);
        $this->assertNotEmpty($result['token']);
        $this->assertInstanceOf(User::class, $result['user']);

        $this->assertDatabaseHas('users', [
            'email'       => 'newuser@example.com',
            'linkedin_id' => 'linkedin-999',
            'name'        => 'New User',
        ]);

        $user = User::where('email', 'newuser@example.com')->firstOrFail();

        $this->assertDatabaseHas('profiles', [
            'user_id'        => $user->id,
            'email'          => 'newuser@example.com',
            'account_status' => 'PENDING_VALIDATION',
        ]);

        // Ensure the profile relation was eager-loaded on the returned model
        $this->assertTrue($result['user']->relationLoaded('profile'));
    }

    #[Test]
    public function test_logs_in_an_existing_user_matched_by_linkedin_id_without_creating_a_new_one(): void
    {
        $existingUser = User::factory()->create([
            'linkedin_id' => 'linkedin-123',
            'email'       => 'existing@example.com',
        ]);

        $linkedInUser = $this->makeSocialiteUser([
            'id'    => 'linkedin-123',
            'email' => 'different-email@example.com', // deliberately mismatched
        ]);

        $result = $this->authService->signUpOrLogin($linkedInUser);

        $this->assertFalse($result['is_new_user']);
        $this->assertTrue($result['user']->is($existingUser));

        // No duplicate user should have been created
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function it_logs_in_an_existing_user_matched_by_email_and_backfills_linkedin_id(): void
    {
        $existingUser = User::factory()->create([
            'linkedin_id' => null,
            'email'       => 'matched-by-email@example.com',
        ]);

        $linkedInUser = $this->makeSocialiteUser([
            'id'    => 'linkedin-456',
            'email' => 'matched-by-email@example.com',
        ]);

        $result = $this->authService->signUpOrLogin($linkedInUser);

        $this->assertFalse($result['is_new_user']);
        $this->assertTrue($result['user']->is($existingUser->fresh()));

        $this->assertDatabaseHas('users', [
            'id'          => $existingUser->id,
            'linkedin_id' => 'linkedin-456',
        ]);

        $this->assertSame(1, User::count());
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_linkedin_id_when_user_already_has_one(): void
    {
        $existingUser = User::factory()->create([
            'linkedin_id' => 'original-linkedin-id',
            'email'       => 'keep-my-id@example.com',
        ]);

        $linkedInUser = $this->makeSocialiteUser([
            'id'    => 'a-different-linkedin-id',
            'email' => 'keep-my-id@example.com',
        ]);

        $this->authService->signUpOrLogin($linkedInUser);

        $this->assertDatabaseHas('users', [
            'id'          => $existingUser->id,
            'linkedin_id' => 'original-linkedin-id',
        ]);
    }

    #[Test]
    public function it_issues_a_fresh_token_and_revokes_previous_tokens(): void
    {
        $existingUser = User::factory()->create([
            'linkedin_id' => 'linkedin-123',
        ]);

        // Simulate a pre-existing token from a previous session
        $oldToken = $existingUser->createToken('api');
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $oldToken->accessToken->id,
        ]);

        $linkedInUser = $this->makeSocialiteUser(['id' => 'linkedin-123']);

        $result = $this->authService->signUpOrLogin($linkedInUser);

        $this->assertNotEmpty($result['token']);

        // Old token should have been revoked, only the new one should remain
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->accessToken->id,
        ]);
        $this->assertSame(1, $existingUser->tokens()->count());
    }

    #[Test]
    public function it_wraps_user_and_profile_creation_in_a_transaction_and_rolls_back_on_failure(): void
    {
        // Force the profile creation to fail by using an email that violates
        // a unique constraint on the profiles table (adjust to your schema).
        \App\Models\Profile::factory()->create([
            'email' => 'conflict@example.com',
        ]);

        $linkedInUser = $this->makeSocialiteUser([
            'id'    => 'linkedin-conflict',
            'email' => 'conflict@example.com',
        ]);

        try {
            $this->authService->signUpOrLogin($linkedInUser);
            $this->fail('Expected an exception due to a profile creation conflict.');
        } catch (\Throwable $e) {
            // Expected: transaction should roll back the user creation too
        }

        $this->assertDatabaseMissing('users', [
            'linkedin_id' => 'linkedin-conflict',
        ]);
    }

    #[Test]
    public function linked_id_authenticate_delegates_to_socialite_with_the_linkedin_openid_driver_in_stateless_mode(): void
    {
        $expectedUser = Mockery::mock(SocialiteUser::class);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($expectedUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('linkedin-openid')
            ->andReturn($driver);

        $result = $this->authService->linkedIdAuthenticate();

        $this->assertSame($expectedUser, $result);
    }
}
