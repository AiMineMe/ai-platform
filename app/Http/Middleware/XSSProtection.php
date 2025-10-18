<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XSSProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() || $request->wantsJson()) {
            return $next($request);
        }

        $skipRoutes = [
            'api/*',
            'webhook/*',
        ];

        foreach ($skipRoutes as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $input = $request->all();

        array_walk_recursive($input, function (&$input): void {
            if (is_string($input)) {
                if (!$this->looksLikeJson($input) && !$this->isSerializedData($input)) {
                    $input = strip_tags($input);
                    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
                    $input = str_replace(chr(0), '', $input);
                    $input = preg_replace('/(javascript:|vbscript:|data:|about:)/i', '', $input);
                }
            }
        });

        $request->merge($input);

        return $next($request);
    }

    /**
     * Check if string looks like JSON
     */
    private function looksLikeJson(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        $trimmed = trim($string);
        $hasJsonStructure = (
            (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
        );

        if (!$hasJsonStructure) {
            return false;
        }

        json_decode($trimmed);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if string is serialized data
     */
    private function isSerializedData(string $string): bool
    {
        $trimmed = trim($string);
        return preg_match('/^[aObis]:\d+:/', $trimmed) === 1;
    }

    /**
     * Check if request should be completely skipped
     */
    private function shouldSkipRequest(Request $request): bool
    {
        if ($request->hasFile()) {
            return true;
        }

        $contentType = $request->header('Content-Type', '');
        $skipContentTypes = [
            'application/json',
            'text/json',
            'multipart/form-data',
            'application/xml',
            'text/xml'
        ];

        foreach ($skipContentTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return false;
    }
}
