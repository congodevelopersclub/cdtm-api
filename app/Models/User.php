<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\UserRole;
use OpenApi\Attributes as OA;

#[Fillable(['name', 'email', 'password', 'avatar_url', 'linkedin_id', 'email_verified', 'role'])]
#[Hidden(['password', 'remember_token'])]
#[OA\Schema(
    schema: 'User',
    title: 'User',
    description: 'A user model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'The user ID'),
        new OA\Property(property: 'name', type: 'string', description: 'The user name'),
        new OA\Property(property: 'email', type: 'string', description: 'The user email'),
        new OA\Property(property: 'avatar_url', type: 'string', description: 'The user avatar URL'),
        new OA\Property(property: 'linkedin_id', type: 'string', description: 'The user LinkedIn ID'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', description: 'The email verification timestamp'),
        new OA\Property(property: 'role', ref: '#/components/schemas/UserRole'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'The creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'The update timestamp'),
    ]
)]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasApiTokens;

    /**
     * @return HasOne<Profile, $this>
    */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }


    /**
    * @return bool
    */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
