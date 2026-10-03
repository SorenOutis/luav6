<?php

namespace App\Http\Controllers;

use App\Models\Merch;
use App\Models\Setting;
use Laravel\Fortify\Features;

class ShopController extends Controller
{
    public function __invoke()
    {
        $merches = Merch::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->get()
            ->map(fn (Merch $merch): array => [
                'id' => $merch->id,
                'name' => $merch->name,
                'description' => $merch->description,
                'price' => (float) $merch->price,
                'currency' => $merch->currency ?? 'PHP',
                'formatted_price' => $merch->formatted_price,
                'image_url' => $merch->image_url,
                'variants' => $merch->formatted_variants,
                'stock' => (int) $merch->stock,
                'is_out_of_stock' => $merch->effective_out_of_stock,
                'stock_label' => $merch->stock_status_label,
                'url' => $merch->redirect_url,
            ]);

        return inertia('Shop', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
            'merches' => $merches,
        ]);
    }
}
