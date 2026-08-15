<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Services\MailService;
use App\Exceptions\InvalidOAuthCodeException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuthService
{
    public function __construct(
        protected MailService $mailService
    ) {}
    /**
     * Redirect to LinkedIn and authenticate the user with LinkedIn using OAuth.
     *
     * @return SocialiteUser
     */
    public function linkedIdAuthenticate(): SocialiteUser
    {
        return Socialite::driver('linkedin-openid')
            ->stateless()
            ->user();
    }

    /**
     * Sign up or log in a user based on the LinkedIn OAuth user data.
     *
     * @return array{one_time_code: string}
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
        $code = $this->generateAndCacheOneTimeCode($user->id, $token);

        return [
            'one_time_code' => $code,
        ];
    }

    /**
     * Exchange a one-time code for a user and token.
     *
     * @param string $code
     * @return array{user: User, token: string}
     * @throws InvalidOAuthCodeException
     * @throws ModelNotFoundException
     */
    public function exchangeOneTimeCode(string $code): ?array
    {
        $token_data = Cache::pull("oauth_code:{$code}");
        if ($token_data === null || !isset($token_data['user_id'], $token_data['token'])) {
            throw new InvalidOAuthCodeException();
        }

        $user = User::find($token_data['user_id']);
        if ($user === null) {
            throw new ModelNotFoundException('User not found');
        }

        return [
            'user' => $user->load('profile'),
            'token' => $token_data['token'],
        ];
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
                'linkedin_id' => $user->linkedin_id,
                'avatar_url' => $user->avatar_url,
                'account_status' => 'PENDING_VALIDATION',
            ]);

            $this->mailService->sendWelcomeEmail($user);

            return $user;
        });
    }

    private function issueToken(User $user): string
    {
        $user->tokens()->delete();
        return $user->createToken('api')->plainTextToken;
    }

    private function generateAndCacheOneTimeCode(string $user_id, string $token): string
    {
        $oneTimeCode = Str::random(40);
        Cache::put("oauth_code:{$oneTimeCode}", [
            'user_id' => $user_id,
            'token' => $token,
        ], now()->addMinutes(2));

        return $oneTimeCode;
    }
}
