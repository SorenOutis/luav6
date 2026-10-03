<?php

namespace Database\Seeders;

use App\Models\Merch;
use Illuminate\Database\Seeder;

class MerchSeeder extends Seeder
{
    public function run(): void
    {
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
                'name' => 'LSI Developer Minimalist Hoodie',
                'description' => 'Ultra-soft fleece with double-lined hood, hidden phone pouch, and tonal matte eyelets. Warm and durable.',
                'price' => 1450.00,
                'currency' => 'PHP',
                'stock' => 12,
                'is_out_of_stock' => false,
                'image_path' => null,
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
