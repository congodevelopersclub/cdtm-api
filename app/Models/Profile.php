<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Enums\{LinkType, ProfileAccountStatus};
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Profile',
    title: 'Profile',
    description: 'A user profile model',
    properties: [
        new OA\Property(property: 'id', type: 'uuid', description: 'The profile ID', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'user_id', type: 'uuid', description: 'The associated user ID', example: '123e4567-e89b-12d3-a456-426614174000'),
        new OA\Property(property: 'email', type: 'string', description: 'The profile email'),
        new OA\Property(property: 'name', type: 'string', description: 'The profile name'),
        new OA\Property(property: 'linkedin_id', type: 'string', description: 'The profile LinkedIn ID'),
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
        new OA\Property(
            property: 'category_id',
            type: 'integer',
            nullable: true,
            description: 'The associated category ID',
            example: 1
        ),
        new OA\Property(
            property: 'category',
            nullable: true,
            description: 'The category linked to this profile',
            ref: '#/components/schemas/Category'
        ),
        new OA\Property(
            property: 'links',
            description: 'Links linked to this profile',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProfileLinks')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'The creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'The update timestamp'),
    ]
)]
#[Fillable(['user_id', 'email', 'name', 'linkedin_id', 'headline', 'bio', 'avatar_url', 'location', 'status', 'account_status', 'category_id', 'links'])]
class Profile extends BaseModel
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $hidden = [
        'category_id',
        'account_status_before_disabled',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'account_status' => ProfileAccountStatus::class,
        'account_status_before_disabled' => ProfileAccountStatus::class,
        'links' => 'array'
    ];

    /**
     * @return BelongsTo<User, $this>
    */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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


    /**
     * Only keep profiles that have not been disabled by their owner.
     *
     * @param Builder<Profile> $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('account_status', '!=', ProfileAccountStatus::DISABLED->value);
    }

    public function isDisabled(): bool
    {
        return $this->account_status === ProfileAccountStatus::DISABLED;
    }

    public function isOwnedBy(?Authenticatable $user): bool
    {
        return $user !== null && $this->user_id === $user->getAuthIdentifier();
    }

    /**
     * @param LinkType $type
     * @return string
    */
    public function link(LinkType $type): ?string
    {
        return $this->links[$type->value] ?? null;
    }
}
