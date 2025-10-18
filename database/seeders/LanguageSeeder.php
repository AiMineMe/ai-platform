<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'flag' => '🇺🇸',
                'is_default' => true,
                'is_active' => true,
            ],
        ];

        foreach ($languages as $languageData) {
            $language = Language::create($languageData);
            $language->loadTranslationsFromFile($language);
        }
    }
}
