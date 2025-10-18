<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    /**
     * @return array
     */
    public static function getGeneralSettings(): array
    {
        return Setting::where('group', 'general')
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * @return array
     */
    public static function getAllSettings(): array
    {
        return Setting::all()
            ->groupBy('group')
            ->map(function ($group) {
                return $group->pluck('value', 'key')->toArray();
            })
            ->toArray();
    }

    /**
     * @return bool
     */
    public static function isRegistrationEnabled(): bool
    {
        return (bool) Setting::get('user_registration', true);
    }
}
