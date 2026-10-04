<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
 
class WebAuthControllerTest extends TestCase
{
    use RefreshDatabase;
 
    private const PASSWORD = 'secret-password';

    protected function setUp(): void
    {
        parent::setUp();
    }
 
    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'testing',
            'email' => 'admin@congodevelopers.club',
            'password' => bcrypt(self::PASSWORD),
            'email_verified_at' => now(),
            'avatar_url' => 'some-avatar-url'
        ]);
    }
 
    // ---------------------------------------------------------------
    // index
    // ---------------------------------------------------------------
 
    public function test_index_displays_the_login_view(): void
    {
        $response = $this->get('/admin/login');
 
        $response->assertOk();
        $response->assertViewIs('admin-login');
    }
 
    // ---------------------------------------------------------------
    // login
    // ---------------------------------------------------------------
 
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->makeUser();
 
        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);
 
        $response->assertRedirect('/telescope');
        $this->assertAuthenticatedAs($user);
    }
 
    public function test_login_redirects_to_intended_url_when_present(): void
    {
        $user = $this->makeUser();
 
        $response = $this
            ->withSession(['url.intended' => 'http://localhost/horizon'])
            ->post('/admin/login', [
                'email' => $user->email,
                'password' => self::PASSWORD,
            ]);
 
        $response->assertRedirect('http://localhost/horizon');
        $this->assertAuthenticatedAs($user);
    }
 
    public function test_login_regenerates_the_session_id(): void
    {
        $user = $this->makeUser();
 
        $this->startSession();
        $oldSessionId = session()->getId();
 
        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);
 
        $this->assertNotSame($oldSessionId, session()->getId());
    }
 
    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->makeUser();
 
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
 
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $this->assertGuest();
    }
 
    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'nobody@congodevelopers.club',
            'password' => self::PASSWORD,
        ]);
 
        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $this->assertGuest();
    }
 
    public function test_failed_login_flashes_only_the_email_input(): void
    {
        $user = $this->makeUser();
 
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
 
        $response->assertSessionHasInput('email', $user->email);
        $response->assertSessionMissing('_old_input.password');
    }
 
    // ---------------------------------------------------------------
    // logout
    // ---------------------------------------------------------------
 
    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->makeUser();
 
        $response = $this->actingAs($user)->post('/admin/logout');
 
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
 
    public function test_logout_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = $this->makeUser();
 
        $response = $this
            ->actingAs($user)
            ->withSession(['foo' => 'bar', '_token' => 'old-token'])
            ->post('/admin/logout');
 
        $response->assertSessionMissing('foo');
        $this->assertNotSame('old-token', session()->token());
    }
}
