<?php

namespace App\Enums;

use OpenApi\Attributes as OA;


#[OA\Schema(
    schema: 'ProfileAccountStatus',
    title: 'Profile Account Status',
    description: 'The status of the profile',
    properties: [
        new OA\Property(property: 'PENDING_VALIDATION', type: 'string', description: 'The profile is pending validation'),
        new OA\Property(property: 'VALIDATED', type: 'string', description: 'The profile is validated'),
        new OA\Property(property: 'REJECTED', type: 'string', description: 'The profile is rejected'),
    ]
)]
enum ProfileAccountStatus: string
{
    case PENDING_VALIDATION = 'PENDING_VALIDATION';
    case VALIDATED = 'VALIDATED';
    case REJECTED = 'REJECTED';
}
