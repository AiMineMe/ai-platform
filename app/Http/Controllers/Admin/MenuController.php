<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        $menus = Menu::ordered()->get();
        $availableComponents = [
            ['value' => 'Hero', 'label' => 'Hero Section'],
            ['value' => 'CryptoPrice', 'label' => 'Crypto Price'],
            ['value' => 'Service', 'label' => 'Services'],
            ['value' => 'AdvancedFeature', 'label' => 'Advanced Features'],
            ['value' => 'Mining', 'label' => 'Mining'],
            ['value' => 'Network', 'label' => 'Network'],
            ['value' => 'Blog', 'label' => 'Blog'],
        ];

        return Inertia::render('Admin/Menus/Index', [
            'menus' => $menus,
            'availableComponents' => $availableComponents
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string|unique:menus,identifier|max:255',
            'menu_name' => 'required|string|max:255',
            'path' => 'required|string|max:255',
            'components' => 'nullable|array',
            'components.*' => 'string|in:Hero,CryptoPrice,Service,AdvancedFeature,Mining,Network,Blog',
            'component_props' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        Cache::forget('frontend_menu');
        $validated['path'] = '/' . ltrim($validated['path'], '/');
        if (empty($validated['identifier'])) {
            $validated['identifier'] = \Str::slug($validated['menu_name']);
        }

        Menu::create($validated);
        return redirect()->back()->with('success', 'Menu item created successfully');
    }

    /**
     * @param Request $request
     * @param Menu $menu
     * @return RedirectResponse
     */
    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string|unique:menus,identifier,' . $menu->id . '|max:255',
            'menu_name' => 'required|string|max:255',
            'path' => 'required|string|max:255',
            'components' => 'nullable|array',
            'components.*' => 'string|in:Hero,CryptoPrice,Service,AdvancedFeature,Mining,Network,Blog',
            'component_props' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        Cache::forget('frontend_menu');
        $validated['path'] = '/' . ltrim($validated['path'], '/');
        $menu->update($validated);
        return redirect()->back()->with('success', 'Menu item updated successfully');
    }

    /**
     * @param Menu $menu
     * @return RedirectResponse
     */
    public function toggleStatus(Menu $menu): RedirectResponse
    {
        $menu->update(['is_active' => !$menu->is_active]);

        return redirect()->back()->with('success', 'Menu status updated successfully');
    }

    /**
     * @param Menu $menu
     * @return RedirectResponse
     */
    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return redirect()->back()->with('success', 'Menu item deleted successfully');
    }
}
