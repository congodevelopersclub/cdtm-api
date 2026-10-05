<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/v1/categories',
        operationId: 'categoryIndex',
        summary: 'List categories',
        description: 'Returns a paginated list of categories ordered by sort_order.',
        tags: ['Category'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of categories',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Category')
                        ),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 5),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return response()->json($categories, 200);
    }

    #[OA\Post(
        path: '/api/v1/categories',
        operationId: 'categoryStore',
        summary: 'Create a category',
        description: 'Creates a new developer category. The slug is auto-generated from the name when omitted.',
        tags: ['Category'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Backend'),
                    new OA\Property(property: 'slug', type: 'string', example: 'backend'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Server-side development'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Category created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Category'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $category = Category::create($validated);

        return response()->json(['data' => $category], 201);
    }

    #[OA\Get(
        path: '/api/v1/categories/{category}',
        operationId: 'categoryShow',
        summary: 'Get a single category',
        description: 'Returns a category by ID.',
        tags: ['Category'],
        parameters: [
            new OA\Parameter(
                name: 'category',
                description: 'ID of the category to retrieve',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Category'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => $category], 200);
    }

    #[OA\Put(
        path: '/api/v1/categories/{category}',
        operationId: 'categoryUpdate',
        summary: 'Update a category',
        description: 'Updates an existing category.',
        tags: ['Category'],
        parameters: [
            new OA\Parameter(
                name: 'category',
                description: 'ID of the category to update',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Backend'),
                    new OA\Property(property: 'slug', type: 'string', example: 'backend'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Server-side development'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'sort_order', type: 'integer', example: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Category'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $category->update($validated);

        return response()->json(['data' => $category], 200);
    }

    #[OA\Delete(
        path: '/api/v1/categories/{category}',
        operationId: 'categoryDestroy',
        summary: 'Delete a category',
        description: 'Soft-deletes a category.',
        tags: ['Category'],
        parameters: [
            new OA\Parameter(
                name: 'category',
                description: 'ID of the category to delete',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Category deleted successfully'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['data' => null, 'message' => 'Category deleted successfully'], 204);
    }
}
