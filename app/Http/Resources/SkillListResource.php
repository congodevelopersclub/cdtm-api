<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
#[OA\Schema(
    schema: "SkillList",
    title: "SkillList",
    description: "Lightweight Skill shape used in list/index responses (no timestamps)",
    required: ["id", "name", "slug"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Laravel"),
        new OA\Property(property: "slug", type: "string", example: "laravel"),
    ]
)]
class SkillListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
