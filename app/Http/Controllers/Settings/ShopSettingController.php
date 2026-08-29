<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ShopSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingController extends Controller
{
    /**
     * Show the shop branding settings page (logo + name).
     */
    public function edit(Request $request): Response
    {
        $shop = ShopSetting::current();

        return Inertia::render('settings/Shop', [
            'shop' => [
                'shop_name' => $shop->shop_name,
                'logo_url' => $shop->logo_url,
            ],
        ]);
    }

    /**
     * Persist the uploaded logo and/or the shop name. Admin only.
     */
    public function update(Request $request): RedirectResponse
    {
        $shop = ShopSetting::current();

        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $shop->shop_name = $validated['shop_name'];

        if ($request->boolean('remove_logo') && $shop->logo_path) {
            Storage::disk('public')->delete($shop->logo_path);
            $shop->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($shop->logo_path) {
                Storage::disk('public')->delete($shop->logo_path);
            }

            $path = $request->file('logo')->store('shop', 'public');
            $shop->logo_path = $path;
        }

        $shop->save();

        return to_route('shop.edit')->with('success', 'Shop settings updated.');
    }
}
