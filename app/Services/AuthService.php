<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * @return array{user: User, token: string, is_new_user: bool}
     */
    public function signUpOrLogin(SocialiteUser $linkedInUser): array
    {
        $user = User::where('linkedin_id', $linkedInUser->getId())
            ->orWhere('email', $linkedInUser->getEmail())
            ->first();

        $isNewUser = false;

        if ($user === null) {
            $user = $this->createUser($linkedInUser);
            $isNewUser = true;
        } elseif ($user->linkedin_id === null) {
            $user->update(['linkedin_id' => $linkedInUser->getId()]);
        }

        $token = $this->issueToken($user);

        return [
            'user' => $user->load('profile'),
            'token' => $token,
            'is_new_user' => $isNewUser,
        ];
    }

    public function linkedIdAuthenticate(): SocialiteUser
    {
        return Socialite::driver('linkedin-openid')
            ->stateless()
            ->user();
    }

    private function createUser(SocialiteUser $linkedInUser): User
    {
        return DB::transaction(function () use ($linkedInUser) {
            $user = User::create([
                'name' => $linkedInUser->getName(),
                'email' => $linkedInUser->getEmail(),
                'linkedin_id' => $linkedInUser->getId(),
                'avatar_url' => $linkedInUser->getAvatar(),
                'email_verified' => true,
                'password' => bcrypt(Str::random(8)), // Generate a random password since LinkedIn doesn't provide one
            ]);

            $user->profile()->create([
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'account_status' => 'PENDING_VALIDATION',
            ]);

            return $user;
        });
    }

    private function issueToken(User $user): string
    {
        $user->tokens()->delete();
        return $user->createToken('api')->plainTextToken;
    }
}
