<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\{UpdateProfileRequest, ValidateProfileRequest, SearchProfilesRequest};
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
        description: 'Returns a paginated list of profiles, including their skills, projects and category.',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'uuid', example: '00000000-0000-0000-0000-000000000000')
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
        $posts = Profile::with(['skills', 'projects', 'category'])->visible()->paginate(20);
        return response()->json($posts, 200);
    }

    #[OA\Get(
        path: '/api/v1/profiles/search',
        operationId: 'profileSearch',
        summary: 'Search profiles',
        description: 'Returns a paginated list of profiles filtered by location, category name and skills. '
            . 'When `skills` is provided, profiles having at least one of the requested skills are returned, '
            . 'ordered by the number of matching skills (most matches first).',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'location',
                description: 'Partial, case-insensitive location match',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255, example: 'Par')
            ),
            new OA\Parameter(
                name: 'category',
                description: 'Partial, case-insensitive category name match',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255, example: 'Devel')
            ),
            new OA\Parameter(
                name: 'skills',
                description: 'Comma-separated list of skill slugs. Profiles matching at least one are returned.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255, example: 'php,laravel')
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1, example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of matching profiles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                allOf: [
                                    new OA\Schema(ref: '#/components/schemas/Profile'),
                                    new OA\Schema(
                                        properties: [
                                            new OA\Property(
                                                property: 'matched_skills_count',
                                                description: 'Number of requested skills this profile has (only present when `skills` is provided)',
                                                type: 'integer',
                                                example: 2
                                            ),
                                        ],
                                        type: 'object'
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 5),
                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 97),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The location field must not be greater than 255 characters.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: ['location' => ['The location field must not be greater than 255 characters.']]
                        ),
                    ],
                    type: 'object',
                    examples: [
                        new OA\Examples(
                            example: 'unknownParameter',
                            summary: 'Unknown query parameter',
                            value: [
                                'message' => 'The foo parameter is not allowed.',
                                'errors' => ['foo' => ['The foo parameter is not allowed.']],
                            ]
                        ),
                        new OA\Examples(
                            example: 'invalidValue',
                            summary: 'Invalid value',
                            value: [
                                'message' => 'The page field must be at least 1.',
                                'errors' => ['page' => ['The page field must be at least 1.']],
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function search(SearchProfilesRequest $request): JsonResponse
    {
        return response()->json($this->profileService->search($request->validated()), 200);
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
        description: 'Returns a profile (route-model-bound by ID) along with its skills, projects and category. '
            . 'A disabled profile is only returned to its owner; anyone else gets a 404.',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to retrieve',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'uuid', example: '00000000-0000-0000-0000-000000000000')
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
        // This route is public, so the caller is resolved through Sanctum only if a token was sent.
        if ($profile->isDisabled() && ! $profile->isOwnedBy(auth('sanctum')->user())) {
            abort(404);
        }

        return response()->json(['data' => $profile->load(['skills', 'projects', 'category'])], 200);
    }

    #[OA\Patch(
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
                schema: new OA\Schema(type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Fields to update on the profile. TODO: replace with the actual fields from UpdateProfileRequest::rules().',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UpdateProfileRequest'
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
                schema: new OA\Schema(type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')
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

    #[OA\Put(
        path: '/api/v1/profiles/{profile}/disable',
        operationId: 'profileDisable',
        summary: 'Disable a profile',
        description: 'Disables the profile of the authenticated user. A disabled profile no longer appears in the '
            . 'profiles list, in searches, nor on its public page, until its owner enables it again. '
            . 'Only the owner of the profile can perform this action. Calling it on an already disabled profile has no effect.',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to disable',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile disabled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'The authenticated user does not own this profile'),
            new OA\Response(response: 404, description: 'Profile not found'),
        ]
    )]
    public function disable(Request $request, Profile $profile): JsonResponse
    {
        abort_unless($profile->isOwnedBy($request->user()), 403, 'You can only disable your own profile.');

        return response()->json(['data' => $this->profileService->disableProfile($profile)], 200);
    }

    #[OA\Put(
        path: '/api/v1/profiles/{profile}/enable',
        operationId: 'profileEnable',
        summary: 'Enable a disabled profile',
        description: 'Enables again a profile previously disabled by its owner and restores the status it had before '
            . '(e.g. VALIDATED). Only the owner of the profile can perform this action. '
            . 'Calling it on a profile that is not disabled has no effect.',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(
                name: 'profile',
                description: 'ID of the profile to enable',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile enabled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'The authenticated user does not own this profile'),
            new OA\Response(response: 404, description: 'Profile not found'),
        ]
    )]
    public function enable(Request $request, Profile $profile): JsonResponse
    {
        abort_unless($profile->isOwnedBy($request->user()), 403, 'You can only enable your own profile.');

        return response()->json(['data' => $this->profileService->enableProfile($profile)], 200);
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
                schema: new OA\Schema(type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')
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
