<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LanguageController extends Controller
{

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function changeLanguage(Request $request): RedirectResponse
    {
        $request->validate([
            'language' => 'required|string|exists:languages,code'
        ]);

        $language = Language::where('code', $request->language)
            ->where('is_active', true)
            ->first();

        if (!$language) {
            return back()->with('error', 'Invalid language selection');
        }

        if (auth()->check()) {
            auth()->user()->update(['language' => $request->language]);
        }

        session(['language' => $request->language]);
        Cache::forget("translations_{$request->language}");
        Cache::forget('frontend_languages');
        return back()->with('success', 'Language updated successfully');
    }

    /**
     * @return JsonResponse
     */
    public function getLanguages(): JsonResponse
    {
        $languages = Cache::remember('frontend_languages', 3600, function () {
            return Language::where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get([
                    'code',
                    'name',
                    'native_name',
                    'flag',
                    'is_default',
                    'is_active'
                ])
                ->map(function ($lang) {
                    return [
                        'code' => $lang->code,
                        'name' => $lang->name,
                        'nativeName' => $lang->native_name,
                        'flag' => $lang->flag,
                        'is_default' => $lang->is_default,
                        'is_active' => $lang->is_active,
                    ];
                })
                ->toArray();
        });

        return response()->json($languages);
    }

    /**
     * @param string $languageCode
     * @return JsonResponse
     */
    public function getTranslations(string $languageCode): JsonResponse
    {
        $language = Language::where('code', $languageCode)
            ->where('is_active', true)
            ->first();

        if (!$language) {
            return response()->json(['error' => 'Language not found'], 404);
        }

        $translations = Cache::remember("translations_{$languageCode}", 3600, function () use ($language) {
            $dbTranslations = $language->getTranslationsArray();
            if (empty($dbTranslations)) {
                $filePath = resource_path("js/lang/{$language->code}.json");
                if (file_exists($filePath)) {
                    $jsonContent = file_get_contents($filePath);
                    $fileTranslations = json_decode($jsonContent, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($fileTranslations)) {
                        return $fileTranslations;
                    }
                }
            }

            return $dbTranslations;
        });

        return response()->json($translations);
    }


}
