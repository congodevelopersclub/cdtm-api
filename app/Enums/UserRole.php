<?php

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserRole',
    title: 'User Role',
    description: 'The role of the user',
    properties: [
        new OA\Property(property: 'USER', type: 'string', description: 'A regular user'),
        new OA\Property(property: 'ADMIN', type: 'string', description: 'An administrator'),
    ]
)]
enum UserRole: string
{
    case User = 'USER';
    case Admin = 'ADMIN';
}
