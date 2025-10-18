<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoMiddleware
{
    /**
     * Routes that should be blocked in demo mode
     */
    protected array $blockedRoutes = [
        // Withdrawal Gateways
        'admin.withdrawal-gateways.*',

        // Menus
        'admin.menus.*',

        // Languages & Translations
        'admin.languages.store',
        'admin.languages.update',
        'admin.languages.destroy',
        'admin.languages.set-default',

        // Security (2FA)
        'admin.security.2fa.enable',
        'admin.security.2fa.disable',
        'admin.security.2fa.verify',

        // Settings (All setting updates)
        'admin.settings.general',
        'admin.settings.email',
        'admin.settings.sms',
        'admin.settings.security',
        'admin.settings.trading',
        'admin.settings.email-template',
        'admin.settings.sms-template',
        'admin.settings.seo',
        'admin.settings.test-email',
        'admin.settings.test-sms',

        // Trade Settings
        'admin.trade-settings.store',
        'admin.trade-settings.update',
        'admin.trade-settings.destroy',

        // ICO Tokens
        'admin.ico-tokens.store',
        'admin.ico-tokens.update',
        'admin.ico-tokens.destroy',
        'admin.ico-tokens.toggle-featured',
        'admin.ico-tokens.update-status',

        // Mining Sessions
        'admin.mining-sessions.update',
        'admin.mining-sessions.destroy',
        'admin.mining-sessions.update-status',
        'admin.mining-sessions.reset',

        // Mining Achievements
        'admin.mining-achievements.*',

        // Mining Competitions
        'admin.mining-competitions.*',

        // Payment Gateways
        'admin.payment-gateways.store',
        'admin.payment-gateways.update',

        // Blogs
        'admin.blogs.store',
        'admin.blogs.update',
        'admin.blogs.destroy',

        // Subscription Plans
        'admin.subscription-plans.store',
        'admin.subscription-plans.update',
        'admin.subscription-plans.destroy',
        'admin.subscription-plans.toggle',

        // System Tools
        'admin.cache-clear.execute',
        'admin.system.migrate',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (env('APP_DEMO') == 'demo') {
            // Check if request is a modifying method
            $isModifyingMethod = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);

            if ($isModifyingMethod) {
                $currentRoute = $request->route()->getName();

                foreach ($this->blockedRoutes as $pattern) {
                    if ($this->matchesPattern($currentRoute, $pattern)) {
                        if ($request->expectsJson()) {
                            return response()->json([
                                'error' => 'This is a demo version. You cannot change anything.',
                                'message' => 'Modifications are disabled in demo mode.'
                            ], 403);
                        }

                        return back()->withErrors([
                            'error' => 'This is a demo version. You cannot change anything.'
                        ]);
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if route name matches pattern
     */
    protected function matchesPattern(string $routeName, string $pattern): bool
    {
        // Convert pattern with * to regex
        $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/';
        return preg_match($regex, $routeName) === 1;
    }
}
