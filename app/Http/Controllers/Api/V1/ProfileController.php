<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\{UpdateProfileRequest, ValidateProfileRequest};
use Illuminate\Support\Facades\{DB, Log};
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
        $posts = Profile::with(['skills', 'projects', 'category'])->paginate(20);
        return response()->json($posts, 200);
    }


    #[OA\Get(
        path: '/api/v1/profiles/stats',
        operationId: 'profileStats',
        summary: 'Profile statistics',
        description: 'Returns aggregated profile counts grouped by account status, work status, stack (category), location and skill. Optional filters: account_status, category_id, location. `limit` caps the skills and locations lists (default 10, max 100).',
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(name: 'account_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['PENDING_VALIDATION', 'VALIDATED', 'REJECTED'])),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile statistics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 97),
                                new OA\Property(property: 'by_account_status', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'by_status', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'by_stack', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'by_location', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'by_skill', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'by_signup_month', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'by_experience_range', type: 'array', items: new OA\Items(type: 'object')),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'account_status' => ['sometimes', 'string', 'in:PENDING_VALIDATION,VALIDATED,REJECTED'],
            'category_id' => ['sometimes', 'integer'],
            'location' => ['sometimes', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = (int) ($filters['limit'] ?? 10);

        $base = fn () => Profile::query()
            ->when(isset($filters['account_status']), fn ($q) => $q->where('profiles.account_status', $filters['account_status']))
            ->when(isset($filters['category_id']), fn ($q) => $q->where('profiles.category_id', $filters['category_id']))
            ->when(isset($filters['location']), fn ($q) => $q->where('profiles.location', $filters['location']));

        $grouped = fn (string $column) => $base()
            ->selectRaw("profiles.{$column} as label, count(*) as total")
            ->groupBy("profiles.{$column}")
            ->orderByDesc('total')
            ->orderBy('label');

        $bySkill = $base()
            ->join('profile_skill', 'profile_skill.profile_id', '=', 'profiles.id')
            ->join('skills', 'skills.id', '=', 'profile_skill.skill_id')
            ->selectRaw('skills.id as skill_id, skills.name as label, count(*) as total, avg(profile_skill.proficiency) as avg_proficiency, avg(profile_skill.years_experience) as avg_years_experience')
            ->groupBy('skills.id', 'skills.name')
            ->orderByDesc('total')
            ->orderBy('label')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'skill_id' => $row->skill_id,
                'label' => $row->label,
                'total' => (int) $row->total,
                'avg_proficiency' => $row->avg_proficiency === null ? null : round((float) $row->avg_proficiency, 2),
                'avg_years_experience' => $row->avg_years_experience === null ? null : round((float) $row->avg_years_experience, 2),
            ]);

        $byStack = $base()
            ->leftJoin('categories', 'categories.id', '=', 'profiles.category_id')
            ->selectRaw('profiles.category_id as category_id, categories.name as label, count(*) as total')
            ->groupBy('profiles.category_id', 'categories.name')
            ->orderByDesc('total')
            ->orderBy('label')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'label' => $row->label,
                'total' => (int) $row->total,
            ]);

        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', profiles.created_at)",
            'pgsql' => "to_char(profiles.created_at, 'YYYY-MM')",
            default => "date_format(profiles.created_at, '%Y-%m')",
        };

        $byMonth = $base()
            ->selectRaw("{$monthExpression} as label, count(*) as total")
            ->groupBy('label')
            ->orderBy('label')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->values();

        // Experience = highest years_experience declared across the profile's skills
        $experience = DB::table('profile_skill')
            ->selectRaw('profile_id, max(years_experience) as years')
            ->groupBy('profile_id');

        $rangeOrder = ['0-1', '2-3', '4-5', '6-10', '10+', 'unknown'];
        $byExperience = $base()
            ->leftJoinSub($experience, 'exp', 'exp.profile_id', '=', 'profiles.id')
            ->selectRaw("case when exp.years is null then 'unknown' when exp.years <= 1 then '0-1' when exp.years <= 3 then '2-3' when exp.years <= 5 then '4-5' when exp.years <= 10 then '6-10' else '10+' end as label, count(*) as total")
            ->groupBy('label')
            ->toBase()
            ->get()
            ->sortBy(fn ($row) => array_search($row->label, $rangeOrder, true))
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->values();

        $simple = fn ($rows) => $rows->map(fn ($row) => [
            'label' => $row->label instanceof \BackedEnum ? $row->label->value : $row->label,
            'total' => (int) $row->total,
        ])->values();

        return response()->json(['data' => [
            'total' => $base()->count(),
            'by_account_status' => $simple($grouped('account_status')->toBase()->get()),
            'by_status' => $simple($grouped('status')->toBase()->get()),
            'by_stack' => $byStack,
            'by_location' => $simple($grouped('location')->limit($limit)->toBase()->get()),
            'by_skill' => $bySkill,
            'by_signup_month' => $byMonth,
            'by_experience_range' => $byExperience,
        ]], 200);
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
        description: 'Returns a profile (route-model-bound by ID) along with its skills, projects and category.',
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
