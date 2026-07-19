<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\{UpdateProfileRequest, ValidateProfileRequest};
use Illuminate\Support\Facades\Log;
use App\Services\ProfileService;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profileService)
    {
    }

    #[OA\Get(
        path: '/api/v1/profiles',
        operationId: 'profileIndex',
        summary: 'List profiles',
        description: 'Returns a paginated list of profiles, including their skills and projects.',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of profiles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Profile')
                        ),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 5),
                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 97),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $posts = Profile::with(['skills', 'projects'])->paginate(20);
        return response()->json($posts, 200);
    }


    #[OA\Post(
        path: '/api/v1/profiles',
        operationId: 'profileStore',
        summary: 'Create a profile',
        description: 'Not implemented yet — currently always returns a 501 response.',
        tags: ['Profile'],
        responses: [
            new OA\Response(
                response: 501,
                description: 'Endpoint not implemented',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'This endpoint is not implemented yet. The profile creation logic is handled during user registration so far.'),
                        new OA\Property(property: 'code', type: 'string', example: 'NOT_IMPLEMENTED'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'This endpoint is not implemented yet. The profile creation logic is handled during user registration so far',
            'code' => 'NOT_IMPLEMENTED',
        ], 501);
    }


    #[OA\Get(
        path: '/api/v1/profiles/{profile}',
        operationId: 'profileShow',
        summary: 'Get a single profile',
        description: 'Returns a profile (route-model-bound by ID) along with its skills and projects.',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to retrieve',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Profile not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Profile].'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function show(Profile $profile): JsonResponse
    {
        return response()->json(['data' => $profile->load(['skills', 'projects'])], 200);
    }


    #[OA\Put(
        path: '/api/v1/profiles/{profile}',
        operationId: 'profileUpdate',
        summary: 'Update a profile',
        description: 'Validates the request via UpdateProfileRequest and updates the given profile.',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to update',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Fields to update on the profile. TODO: replace with the actual fields from UpdateProfileRequest::rules().',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'bio', type: 'string', example: 'Product designer based in Lille.'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Profile not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Profile].'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function update(UpdateProfileRequest $request, Profile $profile): JsonResponse
    {
        $validated = $request->validated();

        $profile = $this->profileService->updateProfile($profile, $validated);

        return response()->json(['data' => $profile], 200);
    }


    #[OA\Post(
        path: '/api/v1/profiles/{profile}/validate',
        operationId: 'profileValidate',
        summary: 'Validate a profile',
        description: 'Validates the request via ValidateProfileRequest and runs profile validation logic (e.g. moderation/completeness check).',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to validate',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Fields required to validate the profile. TODO: replace with the actual fields from ValidateProfileRequest::rules().',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'approved'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile validated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Profile not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Profile].'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function validateProfile(ValidateProfileRequest $request, Profile $profile): JsonResponse
    {
        $validated = $request->validated();

        $profile = $this->profileService->validateProfile($profile, $validated);

        return response()->json(['data' => $profile], 200);
    }


    #[OA\Delete(
        path: '/api/v1/profiles/{profile}',
        operationId: 'profileDestroy',
        summary: 'Delete a profile',
        description: 'Not implemented yet — currently always returns a 501 response.',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to delete',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 501,
                description: 'Endpoint not implemented',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'This endpoint is not implemented yet.'),
                        new OA\Property(property: 'code', type: 'string', example: 'NOT_IMPLEMENTED'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function destroy(Profile $profile): JsonResponse
    {
        return response()->json([
            'message' => 'This endpoint is not implemented yet.',
            'code' => 'NOT_IMPLEMENTED',
        ], 501);
    }

}
