<?php

namespace App\Services;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Send welcome email to newly registered user.
     *
     * @param User $user
     * @return void
     */
    public function sendWelcomeEmail(User $user): void
    {
        Mail::to($user->email)->send(new WelcomeUserMail($user));
    }
}
