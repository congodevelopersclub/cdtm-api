<?php

namespace App\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProfileLinks',
    type: 'object',
    nullable: true,
    description: 'Map of LinkType to URL. Omitted keys are not set.',
    properties: [
        new OA\Property(
            property: LinkType::LinkedIn->value,
            type: 'string',
            format: 'uri',
            maxLength: 255,
            pattern: '^https://www\.linkedin\.com/in/[A-Za-z0-9\-_%]+$',
            example: 'https://www.linkedin.com/in/jane-doe',
        ),
        new OA\Property(
            property: LinkType::GitHub->value,
            type: 'string',
            format: 'uri',
            maxLength: 255,
            pattern: '^https://github\.com/[A-Za-z0-9\-]+$',
            example: 'https://github.com/jane-doe',
        ),
        new OA\Property(
            property: LinkType::Website->value,
            type: 'string',
            format: 'uri',
            maxLength: 255,
            example: 'https://jane.dev',
        ),
    ],
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
