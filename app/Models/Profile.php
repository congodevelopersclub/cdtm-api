<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Profile',
    title: 'Profile',
    description: 'A user profile model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'The profile ID'),
        new OA\Property(property: 'user_id', type: 'integer', description: 'The associated user ID'),
        new OA\Property(property: 'email', type: 'string', description: 'The profile email'),
        new OA\Property(property: 'name', type: 'string', description: 'The profile name'),
        new OA\Property(property: 'headline', type: 'string', description: 'The profile headline'),
        new OA\Property(property: 'bio', type: 'string', description: 'The profile bio'),
        new OA\Property(property: 'avatar_url', type: 'string', description: 'The profile avatar URL'),
        new OA\Property(property: 'location', type: 'string', description: 'The profile location'),
        new OA\Property(property: 'status', type: 'string', description: 'The profile status'),
        new OA\Property(property: 'account_status', ref: '#/components/schemas/ProfileAccountStatus'),
         new OA\Property(
            property: 'skills',
            description: 'Skills linked to this profile',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Skill')
        ),
        new OA\Property(
            property: 'projects',
            description: 'Projects linked to this profile',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Project')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'The creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'The update timestamp'),
    ]
)]
#[Fillable(['user_id', 'email', 'name', 'headline', 'bio', 'avatar_url', 'location', 'status', 'account_status'])]
class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $casts = [
        'is_confirmed' => 'boolean',
        'account_status' => \App\Enums\ProfileAccountStatus::class,
    ];

    /**
     * @return BelongsTo<User, $this>
    */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Project, $this>
    */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return BelongsToMany<Skill, $this, SkillProfile, 'details'>
    */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)
            ->using(SkillProfile::class)
            ->as('details')
            ->withPivot(['proficiency', 'years_experience']);
    }
}
