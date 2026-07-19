<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\SkillListResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SkillController extends Controller
{
    #[OA\Get(
        path: "/skills",
        summary: "List all skills",
        tags: ["Skills"],
        parameters: [
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Paginated list of skills",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/SkillList")
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return SkillListResource::collection(Skill::latest()->paginate(10));
    }

    #[OA\Post(
        path: "/skills",
        summary: "Create a new skill",
        tags: ["Skills"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Laravel"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Skill created",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/Skill"),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:skills,slug'],
        ]);

        $skill = Skill::create($validated);

        return response()->json(['data' => $skill], 201);
    }

    #[OA\Get(
        path: "/skills/{id}",
        summary: "Get a single skill",
        tags: ["Skills"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Skill found",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/Skill"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Skill not found"),
        ]
    )]
    public function show(Skill $skill): JsonResponse
    {
        return response()->json(['data' => $skill]);
    }

    #[OA\Put(
        path: "/skills/{id}",
        summary: "Update a skill",
        tags: ["Skills"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Laravel"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Skill updated",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/Skill"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Skill not found"),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "The given data was invalid."),
                        new OA\Property(property: "code", type: "string", example: "VALIDATION_ERROR"),
                    ]
                )
            ),
        ]
    )]
    public function update(Request $request, Skill $skill): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $skill->update($validated);

        return response()->json(['data' => $skill]);
    }

    #[OA\Delete(
        path: "/skills/{id}",
        summary: "Delete a skill",
        tags: ["Skills"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 204, description: "Skill deleted successfully"),
            new OA\Response(response: 404, description: "Skill not found"),
        ]
    )]
    public function destroy(Skill $skill): JsonResponse
    {
        $skill->delete();

        return response()->json(['data' => null, 'message' => 'Skill deleted successfully'], 204);
    }
}
