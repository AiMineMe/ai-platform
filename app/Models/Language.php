<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($language) {
            if ($language->is_default) {
                static::where('is_default', true)->update(['is_default' => false]);
            }
        });

        static::updating(function ($language) {
            if ($language->is_default && $language->isDirty('is_default')) {
                static::where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function getTranslationsArray(): array
    {
        return $this->translations()
            ->pluck('value', 'key')
            ->toArray();
    }

    public function updateTranslations(array $translations): void
    {
        foreach ($translations as $key => $value) {
            $this->translations()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    public function copyTranslationsFrom(Language $sourceLanguage): void
    {
        $sourceTranslations = $sourceLanguage->getTranslationsArray();
        if (empty($sourceTranslations)) {
            $sourceTranslations = $this->loadTranslationsFromJson($sourceLanguage->code);
            if (!empty($sourceTranslations)) {
                $sourceLanguage->updateTranslations($sourceTranslations);
            }
        }

        foreach ($sourceTranslations as $key => $value) {
            $this->translations()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    private function loadTranslationsFromJson(string $languageCode): array
    {
        $filePath = resource_path("js/lang/{$languageCode}.json");

        if (!file_exists($filePath)) {
            Log::warning("Translation file not found: {$filePath}");
            return [];
        }

        try {
            $jsonContent = file_get_contents($filePath);
            $translations = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Invalid JSON in translation file: {$filePath}. Error: " . json_last_error_msg());
                return [];
            }

            return $translations ?: [];

        } catch (\Exception $e) {
            Log::error("Error reading translation file {$filePath}: " . $e->getMessage());
            return [];
        }
    }

    public function loadTranslationsFromFile($language): void
    {
        $translations = $this->loadTranslationsFromJson($language->code);

        Log::info('----------- translations for language ---------------', ['code' => $language->code]);

        if (!empty($translations)) {
            $this->updateTranslations($translations);
            Log::info("Loaded " . count($translations) . " translations for language: {$this->code}");
        }
    }
}
