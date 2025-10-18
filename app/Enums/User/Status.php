<?php

namespace App\Enums\User;

use App\Enums\EnumTrait;

enum Status: int
{
    use EnumTrait;

    case INACTIVE = 0;
    case ACTIVE = 1;
    case PENDING = 2;
    case SUSPENDED = 3;

    /**
     * Get display name of a specific status value.
     */
    public static function getName(int $value): ?string
    {
        return match ($value) {
            self::INACTIVE->value => 'Inactive',
            self::ACTIVE->value => 'Active',
            self::PENDING->value => 'Pending',
            self::SUSPENDED->value => 'Suspended',
            default => null,
        };
    }
}
