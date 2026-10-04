<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Project',
    title: 'Project',
    description: 'A project model associated with a user profile',
    properties: [
        new OA\Property(property: 'id', type: 'uuid', description: 'The project ID', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'title', type: 'string', description: 'The project title'),
        new OA\Property(property: 'description', type: 'string', description: 'The project description'),
        new OA\Property(property: 'link', type: 'string', description: 'The project link'),
        new OA\Property(property: 'profile_id', type: 'uuid', description: 'The associated profile ID', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'The creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'The update timestamp'),
    ]
)]
#[Fillable(['title', 'description', 'link', 'profile_id'])]
class Project extends BaseModel
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Profile, $this>
    */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
