<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
    public function signUp(): JsonResponse
    {
        $linkedInUser = null;

        try {
            $linkedInUser = $this->authService->linkedIdAuthenticate();
        } catch (\Throwable $e) {
            Log::error('LinkedIn OAuth error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Could not authenticate with LinkedIn.',
            ], 421);
        }

        if ($linkedInUser->getEmail() === null || $linkedInUser->getEmail() === '') {
            return response()->json([
                'message' => 'LinkedIn did not return an email address.',
            ], 422);
        }

        try {
            $data = $this->authService->signUpOrLogin($linkedInUser);
            return response()->json([
                'user'  => $data['user'],
                'token' => $data['token'],
            ], (bool) $data['is_new_user'] ? 201 : 200);
        } catch (\Throwable $e) {
            Log::error('LinkedIn OAuth error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong when signing in or refreshing the user.',
            ], 500);
        }
    }


    #[OA\Get(
        path: '/api/v1/auth/users/{user}',
        operationId: 'authShowUser',
        summary: 'Get a single user with their profile',
        description: 'Returns a user (route-model-bound by ID) along with their related profile.',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(
                name: 'user',
                description: 'ID of the user to retrieve',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/User'),
                                new OA\Schema(
                                    properties: [
                                        new OA\Property(property: 'profile', ref: '#/components/schemas/Profile'),
                                    ]
                                ),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\User].'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $user->load(['profile'])], 200);
    }

}
