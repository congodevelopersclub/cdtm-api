<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Hidden};
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Skill",
    title: "Skill",
    required: ["name"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Laravel"),
        new OA\Property(property: "slug", type: "string", example: "laravel"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[Fillable(['name', 'slug'])]
#[Hidden(['created_at', 'updated_at'])]
class Skill extends Model
{
    /** @use HasFactory<\Database\Factories\SkillFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Skill $skill) {
            if ($skill->slug === null || $skill->slug === '') {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    /**
     * @return BelongsToMany<Profile, $this, SkillProfile, 'pivot'>
    */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class)
            ->using(SkillProfile::class)
            ->withPivot(['proficiency', 'years_experience']);
    }
}
