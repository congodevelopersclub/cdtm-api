<?php

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LinkType',
    title: 'LinkType',
    description: 'The type of the link',
    properties: [
        new OA\Property(property: 'LinkedIn', type: 'string', description: 'LinkedIn link type'),
        new OA\Property(property: 'GitHub', type: 'string', description: 'GitHub link type'),
        new OA\Property(property: 'Medium', type: 'string', description: 'Medium link type'),
        new OA\Property(property: 'Website', type: 'string', description: 'Website link type'),
    ]
)]
enum LinkType: string
{
    case LinkedIn = 'linkedin';
    case GitHub   = 'github';
    case Medium   = 'medium';
    case Website  = 'website';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::GitHub   => 'GitHub',
            self::Medium  => 'Medium',
            self::Website  => 'Website',
        };
    }

    /** Regex the URL must match, or null for any http(s) URL. */
    public function pattern(): ?string
    {
        return match ($this) {
            self::LinkedIn => '#^https://www\.linkedin\.com/in/[A-Za-z0-9\-_%]+$#i',
            self::GitHub   => '#^https://github\.com/[A-Za-z0-9\-]+$#i',
            self::Medium   => '#^https://medium\.com/[A-Za-z0-9\-]+$#i',
            self::Website  => null,
        };
    }
}
