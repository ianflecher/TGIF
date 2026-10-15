<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) throw new \RuntimeException('Storefront demo products are only for local previews and tests.');
        // Illustrative products/prices for the local preview, never a production menu import.
        $products = [
            ['House Burger & Fries', 185, 'Burgers & combos', 'burger-fries.jpg', 'A juicy burger, fresh toppings, and a generous side of golden fries.'],
            ['Classic Takeaway Combo', 165, 'Burgers & combos', 'burger-takeaway.jpg', 'The familiar favorite. A comforting burger and fries, ready for your next break.'],
            ['Loaded Flatbread & Fries', 225, 'Fries & sides', 'loaded-flatbread.jpg', 'A hearty flatbread topped with colorful vegetables and served with chunky fries.'],
            ['Peppercorn Steak Plate', 395, 'Steaks & plates', 'steak-peppercorn.jpg', 'Tender steak with creamy peppercorn sauce and golden potato wedges.'],
            ['Steak & Greens', 375, 'Steaks & plates', 'steak-greens.jpg', 'Sliced steak, fresh greens, crisp cucumber, and potatoes. A plate with a little of everything.'],
            ['The Sharing Steak Board', 595, 'For sharing', 'steak-sharing.jpg', 'A generous steak board with greens, golden potatoes, and dips for the table.'],
            ['Rustic Steak & Potatoes', 385, 'Steaks & plates', 'steak-rustic.jpg', 'A hearty steak plate paired with roasted potatoes and creamy sauce.'],
            ['Table Favorites', 295, 'For sharing', 'table-sharing.jpg', 'Colorful bites and bold flavors for your next good-food moment together.'],
        ];
        foreach ($products as $index => [$name, $price, $category, $photo, $description]) {
            $slug = 'demo-'.Str::slug($name);
            if (DB::table('products')->where('slug', $slug)->exists()) continue;
            DB::table('products')->insert([
                'product_name' => $name, 'slug' => $slug, 'price' => $price,
                'category' => $category, 'description' => $description, 'short_description' => $description,
                'images' => json_encode(['images/storefront/'.$photo]),
                'attributes' => json_encode(['sizes' => [], 'flavors' => [], 'varieties' => []]),
                'status' => 'published', 'stock_quantity' => 50, 'sold_count' => 100 - $index * 9,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->command?->info('Local storefront demo menu is ready. Products and prices are illustrative.');
    }
}
