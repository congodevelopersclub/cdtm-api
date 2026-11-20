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
        new OA\Property(property: 'DISABLED', type: 'string', description: 'The profile has been disabled by its owner and is hidden from listings and searches'),
    ]
)]
enum ProfileAccountStatus: string
{
    case PENDING_VALIDATION = 'PENDING_VALIDATION';
    case VALIDATED = 'VALIDATED';
    case REJECTED = 'REJECTED';
    case DISABLED = 'DISABLED';

    /**
     * Statuses an admin is allowed to set through the validation endpoint.
     * DISABLED is reserved to the profile owner.
     *
     * @return array<int, self>
     */
    public static function moderationStatuses(): array
    {
        return [self::PENDING_VALIDATION, self::VALIDATED, self::REJECTED];
    }
}
