<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/users/{user}',
        operationId: 'getUserById',
        summary: 'Get a single user with their profile',
        description: 'Returns a user (route-model-bound by ID) along with their related profile.',
        tags: ['Users'],
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
                        new OA\Property(property: 'message', type: 'string', example: 'User not found.'),
                        new OA\Property(property: 'code', type: 'string', example: 'MODEL_NOT_FOUND'),
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
