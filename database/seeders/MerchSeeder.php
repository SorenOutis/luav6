<?php

namespace Database\Seeders;

use App\Models\Merch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MerchSeeder extends Seeder
{
    public function run(): void
    {
        $sourceBlack = public_path('images/merch/techwear-hoodie-black.jpg');
        $sourceWhite = public_path('images/merch/techwear-hoodie-white.jpg');

        if (file_exists($sourceBlack) && ! Storage::disk('public')->exists('merch/techwear-hoodie-black.jpg')) {
            Storage::disk('public')->put('merch/techwear-hoodie-black.jpg', file_get_contents($sourceBlack));
        }

        if (file_exists($sourceWhite) && ! Storage::disk('public')->exists('merch/techwear-hoodie-white.jpg')) {
            Storage::disk('public')->put('merch/techwear-hoodie-white.jpg', file_get_contents($sourceWhite));
        }

        $items = [
            [
                'name' => 'KOAMISHIN Signature Heavyweight Tee',
                'description' => '240 GSM combed cotton with high-density embroidered insignia. Relaxed boxy silhouette built for daily wear.',
                'price' => 750.00,
                'currency' => 'PHP',
                'stock' => 24,
                'is_out_of_stock' => false,
                'image_path' => null,
                'button_url' => 'https://koamishin.com/',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'KOAMISHIN BSIT Techwear Hoodie',
                'description' => '380 GSM heavyweight technical fleece with topographic contour sleeves, weatherproof angular pocket, and BSIT signature insignia.',
                'price' => 1650.00,
                'currency' => 'PHP',
                'stock' => 12,
                'is_out_of_stock' => false,
                'image_path' => 'merch/techwear-hoodie-black.jpg',
                'variants' => [
                    [
                        'name' => 'Obsidian',
                        'image_path' => 'merch/techwear-hoodie-black.jpg',
                    ],
                    [
                        'name' => 'Alabaster',
                        'image_path' => 'merch/techwear-hoodie-white.jpg',
                    ],
                ],
                'button_url' => 'https://koamishin.com/',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'KOAMISHIN Structured Snapback Cap',
                'description' => '6-panel structured wool-blend crown with moisture-wicking sweatband and precision 3D stitched branding.',
                'price' => 550.00,
                'currency' => 'PHP',
                'stock' => 4,
                'is_out_of_stock' => false,
                'image_path' => null,
                'button_url' => 'https://koamishin.com/',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Classroom Canvas Field Tote',
                'description' => 'Heavyweight 16oz raw natural cotton canvas with reinforced cross-stitched handles and interior zipper divider.',
                'price' => 420.00,
                'currency' => 'PHP',
                'stock' => 0,
                'is_out_of_stock' => true,
                'image_path' => null,
                'button_url' => 'https://koamishin.com/',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($items as $item) {
            Merch::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
