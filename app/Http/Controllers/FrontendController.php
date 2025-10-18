<?php

namespace App\Http\Controllers;

use App\Concerns\UploadedFile;
use App\Models\Blog;
use App\Models\Currency;
use App\Models\Menu;
use App\Models\Setting;
use App\Services\DefaultImageService;
use Inertia\Inertia;
use Inertia\Response;

class FrontendController extends Controller
{
    use UploadedFile;
    /**
     * @return Response
     */
    public function index(): Response
    {
        $blogs = Blog::published()
            ->latest()
            ->limit(6)
            ->get()
            ->map(function ($blog) {
                return [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'excerpt' => $blog->excerpt,
                    'slug' => $blog->slug,
                    'date' => $blog->created_at,
                    'readTime' => $blog->read_time,
                    'icon' => $blog->icon,
                    'color' => $blog->color,
                    'image' => $blog->image ?: DefaultImageService::getImageUrl(null, 'blog', 1200, 630)
                ];
            });

        $cryptoData = Currency::latest('last_updated')
            ->limit(10)
            ->get()
            ->map(function ($crypto, $index) {
                return [
                    'id' => $crypto->id ?? ($crypto->symbol . '-' . $index),
                    'rank' => $crypto->rank ?? ($index + 1),
                    'symbol' => strtoupper($crypto->symbol ?? 'N/A'),
                    'name' => $crypto->name ?? 'Unknown',
                    'price' => floatval($crypto->current_price ?? 0),
                    'change' => floatval($crypto->change_percent ?? 0),
                    'volume' => floatval($crypto->total_volume ?? 0),
                    'marketCap' => floatval($crypto->market_cap ?? 0),
                    'image_url' => $crypto->image_url ?? DefaultImageService::getImageUrl(400, 400),
                ];
            });

        $marketStats = [
            'totalMarketCap' => $cryptoData->sum('marketCap'),
            'totalVolume' => $cryptoData->sum('volume'),
            'dominancePercentage' => $cryptoData->where('symbol', 'BTC')->first()['marketCap'] ?? 0
        ];

        return Inertia::render('Frontend', [
            'blogs' => $blogs,
            'cryptoData' => $cryptoData,
            'marketStats' => $marketStats,
            'primaryColor' => Setting::get('primary_color', '#1f2937'),
        ]);
    }

    /**
     * @return Response
     */
    public function privacy(): Response
    {
        return Inertia::render('Privacy', [

        ]);
    }

    /**
     * @return Response
     */
    public function terms(): Response
    {
        return Inertia::render('Terms', [

        ]);
    }

    /**
     * @return Response
     */
    public function cookies(): Response
    {
        return Inertia::render('Cookies', [

        ]);
    }

    /**
     * @param $path
     * @return Response
     */
    public function dynamicPage($path): Response
    {
        $menu = Menu::where('path', $path)
            ->where('is_active', true)
            ->first();

        if (!$menu) {
            abort(404);
        }

        $componentData = $this->getComponentData($menu->components);
        return Inertia::render('DynamicPage', [
            'menu' => $menu,
            'componentData' => $componentData,
            'pageTitle' => $menu->menu_name,
        ]);
    }

    /**
     * @param $components
     * @return array
     */
    private function getComponentData($components): array
    {
        $data = [];

        if (!is_array($components)) {
            return $data;
        }

        foreach ($components as $component) {
            switch ($component) {
                case 'Blog':
                    $data['blogs'] = Blog::published()
                        ->latest()
                        ->limit(6)
                        ->get()
                        ->map(function ($blog) {
                            return [
                                'id' => $blog->id,
                                'title' => $blog->title,
                                'excerpt' => $blog->excerpt,
                                'slug' => $blog->slug,
                                'date' => $blog->created_at,
                                'readTime' => $blog->read_time,
                                'icon' => $blog->icon,
                                'color' => $blog->color,
                                'image' => $blog->image ?: DefaultImageService::getImageUrl(null, 'blog', 1200, 630)
                            ];
                        });
                    break;

                case 'CryptoPrice':
                    $cryptoData = Currency::latest('last_updated')
                        ->limit(10)
                        ->get()
                        ->map(function ($crypto, $index) {
                            return [
                                'id' => $crypto->id ?? ($crypto->symbol . '-' . $index),
                                'rank' => $crypto->rank ?? ($index + 1),
                                'symbol' => strtoupper($crypto->symbol ?? 'N/A'),
                                'name' => $crypto->name ?? 'Unknown',
                                'price' => floatval($crypto->current_price ?? 0),
                                'change' => floatval($crypto->change_percent ?? 0),
                                'volume' => floatval($crypto->volume_24h ?? 0),
                                'marketCap' => floatval($crypto->market_cap ?? 0),
                                'image_url' => $crypto->image_url ?? null,
                            ];
                        });

                    $marketStats = [
                        'totalMarketCap' => $cryptoData->sum('marketCap'),
                        'totalVolume' => $cryptoData->sum('volume'),
                        'dominancePercentage' => $cryptoData->where('symbol', 'BTC')->first()['marketCap'] ?? 0
                    ];

                    $data['cryptoData'] = $cryptoData;
                    $data['marketStats'] = $marketStats;
                    break;
            }
        }

        return $data;
    }
}
