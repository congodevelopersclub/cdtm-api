<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Services\AuthService;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    #[OA\Get(
        path: '/api/v1/auth',
        operationId: 'authRedirect',
        summary: 'Redirect the user to LinkedIn for authentication',
        description: 'Redirects the user to LinkedIn for OAuth authentication.',
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 302,
                description: 'Redirect to LinkedIn OAuth',
            ),
        ]
    )]
    public function redirect()
    {
        return Socialite::driver('linkedin-openid')
                ->stateless()
                ->redirect();
    }

    #[OA\Get(
        path: '/api/v1/auth/sign-up',
        operationId: 'authSignUp',
        summary: 'Sign up or log in a user via LinkedIn OAuth',
        description: 'Authenticates the user against LinkedIn, then either creates a new account or logs the user in if one already exists. Returns 201 if a new account was created, 200 if an existing user logged in.',
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Existing user successfully logged in',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 201,
                description: 'New user successfully created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 421,
                description: 'Could not authenticate with LinkedIn',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Could not authenticate with LinkedIn.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'LinkedIn did not return an email address',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'LinkedIn did not return an email address.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected error while signing in or creating the user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Something went wrong when signing in or refreshing the user.'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function signUp(): RedirectResponse
    {
        $linkedInUser = null;

        try {
            $linkedInUser = $this->authService->linkedIdAuthenticate();
        } catch (\Throwable $e) {
            Log::error('LinkedIn OAuth error: ' . $e->getMessage());
            return redirect()->away(
                config('services.frontend_url') . '/auth/callback?error=' . urlencode('AUTH_FAILED')
            );
        }

        if ($linkedInUser->getEmail() === null || $linkedInUser->getEmail() === '') {
            return redirect()->away(
                config('services.frontend_url') . '/auth/callback?error=' . urlencode('AUTH_FAILED')
            );
        }

        try {
            $data = $this->authService->signUpOrLogin($linkedInUser);

            return redirect()->away(
                config('services.frontend_url') . '/auth/callback?code=' . $data['one_time_code']
            );
        } catch (\Throwable $e) {
            Log::error('LinkedIn OAuth error: ' . $e->getMessage());
            return redirect()->away(
                config('services.frontend_url') . '/auth/callback?error=' . urlencode('AUTH_FAILED')
            );
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/exchange-code',
        operationId: 'authExchangeCode',
        summary: 'Exchange a one-time code for a user and token',
        description: 'Exchanges a one-time code obtained from LinkedIn OAuth for a user and token.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'one-time-code'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'One-time code exchanged successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid or expired one-time code',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired code.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found for the provided one-time code',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'App\\Models\\User not found.'),
                        new OA\Property(property: 'code', type: 'string', example: 'MODEL_NOT_FOUND'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error while exchanging the one-time code',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error.'),
                        new OA\Property(property: 'code', type: 'string', example: 'INTERNAL_SERVER_ERROR'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function exchangeCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $data = $this->authService->exchangeOneTimeCode($request->input('code'));

        return response()->json(['token' => $data['token'], 'user' => $data['user']], 200);
    }

    #[OA\Get(
        path: '/api/v1/me',
        summary: 'Get the authenticated user',
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user retrieved successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/User'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user(), 200);
    }

}
