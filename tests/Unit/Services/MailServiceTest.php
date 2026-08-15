<?php

namespace Tests\Unit\Services;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_welcome_email_queues_welcome_user_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'testwelcome@example.com',
        ]);

        $mailService = new MailService();
        $mailService->sendWelcomeEmail($user);

        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user) {
            return $mail->user->id === $user->id && $mail->hasTo('testwelcome@example.com');
        });
    }

    public function test_welcome_user_mail_properties(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $mailable = new WelcomeUserMail($user);

        $this->assertEquals('Bienvenue sur CDTM', $mailable->envelope()->subject);
        $this->assertEquals('emails.welcome', $mailable->content()->markdown);
        $this->assertEquals(['name' => 'Alice'], $mailable->content()->with);
        $this->assertEmpty($mailable->attachments());
    }
}
