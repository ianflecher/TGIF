<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SnacksProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, let's create inventory items for snacks and fries
        $inventories = [
            // Potato Chips/Crisps
            [
                'sku' => 'SNACK-CHIPS-001',
                'product_name' => 'Classic Potato Chips',
                'description' => 'Traditional salted potato chips, crispy and delicious',
                'category' => 'Snacks',
                'quantity' => 1000,
                'min_quantity' => 100,
                'max_quantity' => 5000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-01',
                'unit_price' => 1.99,
                'cost_price' => 0.89,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'SNACK-CHIPS-002',
                'product_name' => 'Barbecue Potato Chips',
                'description' => 'Smoky barbecue flavored potato chips',
                'category' => 'Snacks',
                'quantity' => 850,
                'min_quantity' => 100,
                'max_quantity' => 4000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-02',
                'unit_price' => 1.99,
                'cost_price' => 0.89,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'SNACK-CHIPS-003',
                'product_name' => 'Sour Cream & Onion Chips',
                'description' => 'Creamy sour cream and onion flavored potato chips',
                'category' => 'Snacks',
                'quantity' => 920,
                'min_quantity' => 100,
                'max_quantity' => 4000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-03',
                'unit_price' => 2.19,
                'cost_price' => 0.95,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Tortilla Chips
            [
                'sku' => 'SNACK-TORTILLA-001',
                'product_name' => 'Restaurant Style Tortilla Chips',
                'description' => 'Thick and crispy triangle tortilla chips',
                'category' => 'Snacks',
                'quantity' => 750,
                'min_quantity' => 80,
                'max_quantity' => 3000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-04',
                'unit_price' => 3.49,
                'cost_price' => 1.45,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'SNACK-TORTILLA-002',
                'product_name' => 'Nacho Cheese Tortilla Chips',
                'description' => 'Cheese flavored tortilla chips perfect for dipping',
                'category' => 'Snacks',
                'quantity' => 680,
                'min_quantity' => 80,
                'max_quantity' => 3000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-05',
                'unit_price' => 3.79,
                'cost_price' => 1.65,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // French Fries
            [
                'sku' => 'FRIES-FROZEN-001',
                'product_name' => 'Classic Frozen French Fries',
                'description' => 'Premium cut frozen french fries, ready to cook',
                'category' => 'Frozen Foods',
                'quantity' => 500,
                'min_quantity' => 50,
                'max_quantity' => 2000,
                'warehouse' => 'Freezer Storage',
                'zone' => 'FRZ-01',
                'unit_price' => 4.99,
                'cost_price' => 2.10,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'FRIES-FROZEN-002',
                'product_name' => 'Crinkle Cut French Fries',
                'description' => 'Crinkle cut frozen french fries, extra crispy',
                'category' => 'Frozen Foods',
                'quantity' => 420,
                'min_quantity' => 50,
                'max_quantity' => 2000,
                'warehouse' => 'Freezer Storage',
                'zone' => 'FRZ-02',
                'unit_price' => 4.99,
                'cost_price' => 2.10,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'FRIES-FROZEN-003',
                'product_name' => 'Seasoned Curly Fries',
                'description' => 'Spiral cut frozen fries with special seasoning',
                'category' => 'Frozen Foods',
                'quantity' => 380,
                'min_quantity' => 40,
                'max_quantity' => 1500,
                'warehouse' => 'Freezer Storage',
                'zone' => 'FRZ-03',
                'unit_price' => 5.49,
                'cost_price' => 2.45,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'FRIES-FROZEN-004',
                'product_name' => 'Sweet Potato Fries',
                'description' => 'Frozen sweet potato fries, naturally sweet',
                'category' => 'Frozen Foods',
                'quantity' => 320,
                'min_quantity' => 30,
                'max_quantity' => 1200,
                'warehouse' => 'Freezer Storage',
                'zone' => 'FRZ-04',
                'unit_price' => 6.29,
                'cost_price' => 2.85,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Pretzels
            [
                'sku' => 'SNACK-PRETZEL-001',
                'product_name' => 'Soft Pretzel Bites',
                'description' => 'Microwaveable soft pretzel bites with cheese sauce',
                'category' => 'Frozen Foods',
                'quantity' => 280,
                'min_quantity' => 30,
                'max_quantity' => 1000,
                'warehouse' => 'Freezer Storage',
                'zone' => 'FRZ-05',
                'unit_price' => 5.99,
                'cost_price' => 2.55,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'SNACK-PRETZEL-002',
                'product_name' => 'Hard Pretzel Rods',
                'description' => 'Classic hard pretzel rods, perfect for snacking',
                'category' => 'Snacks',
                'quantity' => 450,
                'min_quantity' => 50,
                'max_quantity' => 2000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-06',
                'unit_price' => 3.29,
                'cost_price' => 1.35,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Popcorn
            [
                'sku' => 'SNACK-POPCORN-001',
                'product_name' => 'Microwave Butter Popcorn',
                'description' => 'Movie theater style butter popcorn, microwaveable',
                'category' => 'Snacks',
                'quantity' => 600,
                'min_quantity' => 60,
                'max_quantity' => 2500,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-07',
                'unit_price' => 3.99,
                'cost_price' => 1.65,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sku' => 'SNACK-POPCORN-002',
                'product_name' => 'Caramel Popcorn',
                'description' => 'Sweet and crunchy caramel coated popcorn',
                'category' => 'Snacks',
                'quantity' => 480,
                'min_quantity' => 50,
                'max_quantity' => 2000,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-08',
                'unit_price' => 4.49,
                'cost_price' => 1.95,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Cheese Snacks
            [
                'sku' => 'SNACK-CHEESE-001',
                'product_name' => 'Cheese Puffs',
                'description' => 'Light and airy cheese flavored puffs',
                'category' => 'Snacks',
                'quantity' => 550,
                'min_quantity' => 60,
                'max_quantity' => 2500,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-09',
                'unit_price' => 2.49,
                'cost_price' => 1.05,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mixed Nuts
            [
                'sku' => 'SNACK-NUTS-001',
                'product_name' => 'Salted Mixed Nuts',
                'description' => 'Premium mix of almonds, cashews, walnuts, and peanuts',
                'category' => 'Snacks',
                'quantity' => 350,
                'min_quantity' => 40,
                'max_quantity' => 1500,
                'warehouse' => 'Food Warehouse',
                'zone' => 'F-10',
                'unit_price' => 8.99,
                'cost_price' => 4.20,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert inventories
        DB::table('inventories')->insert($inventories);

        // Get the inserted inventory IDs
        $inventoryIds = DB::table('inventories')->pluck('inventory_id', 'sku')->toArray();

        // Create snack product categories
        $categories = [
            ['name' => 'Snacks', 'slug' => 'snacks', 'description' => 'All kinds of snacks'],
            ['name' => 'Frozen Foods', 'slug' => 'frozen-foods', 'description' => 'Frozen snacks and appetizers'],
            ['name' => 'Potato Chips', 'slug' => 'potato-chips', 'parent_id' => 1],
            ['name' => 'Tortilla Chips', 'slug' => 'tortilla-chips', 'parent_id' => 1],
            ['name' => 'French Fries', 'slug' => 'french-fries', 'parent_id' => 2],
            ['name' => 'Pretzels', 'slug' => 'pretzels', 'parent_id' => 1],
            ['name' => 'Popcorn', 'slug' => 'popcorn', 'parent_id' => 1],
            ['name' => 'Cheese Snacks', 'slug' => 'cheese-snacks', 'parent_id' => 1],
            ['name' => 'Nuts & Seeds', 'slug' => 'nuts-seeds', 'parent_id' => 1],
            ['name' => 'Salty Snacks', 'slug' => 'salty-snacks', 'parent_id' => 1],
        ];

        DB::table('product_categories')->insert($categories);

        // Get category IDs
        $categoryIds = DB::table('product_categories')->pluck('id', 'slug')->toArray();

        // Now create snack products
        $products = [
            // Classic Potato Chips
            [
                'inventory_id' => $inventoryIds['SNACK-CHIPS-001'],
                'product_name' => 'CrunchTime Classic Salted Potato Chips',
                'price' => 2.49,
                'description' => 'Made from the finest potatoes and lightly salted to perfection. These classic potato chips are crispy, delicious, and perfect for any occasion. No artificial flavors or preservatives.',
                'short_description' => 'Classic salted potato chips, crispy and delicious',
                'slug' => Str::slug('CrunchTime Classic Salted Potato Chips'),
                'images' => json_encode([
                    'main' => 'products/chips-classic-main.jpg',
                    'gallery' => [
                        'products/chips-classic-1.jpg',
                        'products/chips-classic-2.jpg',
                        'products/chips-classic-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'CrunchTime',
                    'flavor' => 'Classic Salted',
                    'weight' => '200g',
                    'serving_size' => '30g',
                    'servings_per_container' => 'Approx 7',
                    'allergens' => 'Contains: None',
                    'storage' => 'Store in a cool, dry place',
                    'dietary_info' => 'Vegetarian, Vegan',
                    'expiry' => '6 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 1000,
                'sold_count' => 350,
                'created_at' => now()->subDays(60),
                'updated_at' => now(),
            ],
            // Barbecue Potato Chips
            [
                'inventory_id' => $inventoryIds['SNACK-CHIPS-002'],
                'product_name' => 'CrunchTime Smoky Barbecue Potato Chips',
                'price' => 2.49,
                'description' => 'Smoky, sweet, and tangy barbecue flavor in every crispy chip. Made from real potatoes with a perfect blend of spices. Great for parties, picnics, or everyday snacking.',
                'short_description' => 'Smoky barbecue flavored potato chips',
                'slug' => Str::slug('CrunchTime Smoky Barbecue Potato Chips'),
                'images' => json_encode([
                    'main' => 'products/chips-bbq-main.jpg',
                    'gallery' => [
                        'products/chips-bbq-1.jpg',
                        'products/chips-bbq-2.jpg',
                        'products/chips-bbq-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'CrunchTime',
                    'flavor' => 'Smoky Barbecue',
                    'weight' => '200g',
                    'serving_size' => '30g',
                    'servings_per_container' => 'Approx 7',
                    'allergens' => 'Contains: None',
                    'storage' => 'Store in a cool, dry place',
                    'dietary_info' => 'Vegetarian',
                    'expiry' => '6 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 850,
                'sold_count' => 420,
                'created_at' => now()->subDays(55),
                'updated_at' => now(),
            ],
            // Sour Cream & Onion Chips
            [
                'inventory_id' => $inventoryIds['SNACK-CHIPS-003'],
                'product_name' => 'CrunchTime Sour Cream & Onion Potato Chips',
                'price' => 2.79,
                'description' => 'Creamy sour cream and zesty onion flavor combined with crispy potato chips. A classic flavor combination that never disappoints. Perfect for movie nights or lunch boxes.',
                'short_description' => 'Creamy sour cream and onion flavored chips',
                'slug' => Str::slug('CrunchTime Sour Cream Onion Potato Chips'),
                'images' => json_encode([
                    'main' => 'products/chips-sourcream-main.jpg',
                    'gallery' => [
                        'products/chips-sourcream-1.jpg',
                        'products/chips-sourcream-2.jpg',
                        'products/chips-sourcream-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'CrunchTime',
                    'flavor' => 'Sour Cream & Onion',
                    'weight' => '200g',
                    'serving_size' => '30g',
                    'servings_per_container' => 'Approx 7',
                    'allergens' => 'Contains: Milk',
                    'storage' => 'Store in a cool, dry place',
                    'dietary_info' => 'Vegetarian',
                    'expiry' => '6 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 920,
                'sold_count' => 380,
                'created_at' => now()->subDays(50),
                'updated_at' => now(),
            ],
            // Restaurant Style Tortilla Chips
            [
                'inventory_id' => $inventoryIds['SNACK-TORTILLA-001'],
                'product_name' => 'MexiCrunch Restaurant Style Tortilla Chips',
                'price' => 4.29,
                'description' => 'Thick, crispy triangle tortilla chips made from whole corn. Authentic restaurant style that holds up to your favorite dips. Perfect for nachos, dips, or enjoying plain.',
                'short_description' => 'Authentic restaurant style tortilla chips',
                'slug' => Str::slug('MexiCrunch Restaurant Style Tortilla Chips'),
                'images' => json_encode([
                    'main' => 'products/tortilla-classic-main.jpg',
                    'gallery' => [
                        'products/tortilla-classic-1.jpg',
                        'products/tortilla-classic-2.jpg',
                        'products/tortilla-classic-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'MexiCrunch',
                    'flavor' => 'Restaurant Style',
                    'weight' => '400g',
                    'serving_size' => '50g',
                    'servings_per_container' => 'Approx 8',
                    'allergens' => 'Contains: Corn',
                    'storage' => 'Store in a cool, dry place',
                    'dietary_info' => 'Vegan, Gluten-Free',
                    'expiry' => '9 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 750,
                'sold_count' => 210,
                'created_at' => now()->subDays(45),
                'updated_at' => now(),
            ],
            // Classic Frozen French Fries
            [
                'inventory_id' => $inventoryIds['FRIES-FROZEN-001'],
                'product_name' => 'FryMaster Classic Cut French Fries',
                'price' => 5.99,
                'description' => 'Premium quality frozen french fries made from selected potatoes. Perfectly cut for even cooking and golden crispiness. Ready to bake or fry for delicious homemade fries.',
                'short_description' => 'Premium classic cut frozen french fries',
                'slug' => Str::slug('FryMaster Classic Cut French Fries'),
                'images' => json_encode([
                    'main' => 'products/fries-classic-main.jpg',
                    'gallery' => [
                        'products/fries-classic-1.jpg',
                        'products/fries-classic-2.jpg',
                        'products/fries-classic-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'FryMaster',
                    'type' => 'Classic Cut',
                    'weight' => '1kg',
                    'cooking_method' => 'Oven or Deep Fry',
                    'cooking_time' => '15-20 minutes',
                    'allergens' => 'Contains: None',
                    'storage' => 'Keep frozen at -18°C or below',
                    'dietary_info' => 'Vegetarian, Vegan',
                    'expiry' => '12 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 500,
                'sold_count' => 150,
                'created_at' => now()->subDays(40),
                'updated_at' => now(),
            ],
            // Crinkle Cut French Fries
            [
                'inventory_id' => $inventoryIds['FRIES-FROZEN-002'],
                'product_name' => 'FryMaster Crinkle Cut French Fries',
                'price' => 5.99,
                'description' => 'Extra crispy crinkle cut french fries with ridges that hold more flavor. Made from premium potatoes and perfect for dipping. Quick and easy to prepare.',
                'short_description' => 'Crispy crinkle cut frozen french fries',
                'slug' => Str::slug('FryMaster Crinkle Cut French Fries'),
                'images' => json_encode([
                    'main' => 'products/fries-crinkle-main.jpg',
                    'gallery' => [
                        'products/fries-crinkle-1.jpg',
                        'products/fries-crinkle-2.jpg',
                        'products/fries-crinkle-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'FryMaster',
                    'type' => 'Crinkle Cut',
                    'weight' => '1kg',
                    'cooking_method' => 'Oven or Deep Fry',
                    'cooking_time' => '15-20 minutes',
                    'allergens' => 'Contains: None',
                    'storage' => 'Keep frozen at -18°C or below',
                    'dietary_info' => 'Vegetarian, Vegan',
                    'expiry' => '12 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 420,
                'sold_count' => 95,
                'created_at' => now()->subDays(35),
                'updated_at' => now(),
            ],
            // Seasoned Curly Fries
            [
                'inventory_id' => $inventoryIds['FRIES-FROZEN-003'],
                'product_name' => 'FryMaster Seasoned Curly Fries',
                'price' => 6.49,
                'description' => 'Spiral cut potatoes with a special blend of seasonings. These curly fries are fun to eat and packed with flavor. Perfect as a side dish or appetizer.',
                'short_description' => 'Seasoned spiral cut curly fries',
                'slug' => Str::slug('FryMaster Seasoned Curly Fries'),
                'images' => json_encode([
                    'main' => 'products/fries-curly-main.jpg',
                    'gallery' => [
                        'products/fries-curly-1.jpg',
                        'products/fries-curly-2.jpg',
                        'products/fries-curly-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'FryMaster',
                    'type' => 'Seasoned Curly Fries',
                    'weight' => '1kg',
                    'cooking_method' => 'Oven or Deep Fry',
                    'cooking_time' => '15-20 minutes',
                    'seasoning' => 'Special blend of herbs and spices',
                    'allergens' => 'Contains: None',
                    'storage' => 'Keep frozen at -18°C or below',
                    'dietary_info' => 'Vegetarian',
                    'expiry' => '12 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 380,
                'sold_count' => 120,
                'created_at' => now()->subDays(30),
                'updated_at' => now(),
            ],
            // Sweet Potato Fries
            [
                'inventory_id' => $inventoryIds['FRIES-FROZEN-004'],
                'product_name' => 'FryMaster Sweet Potato Fries',
                'price' => 7.49,
                'description' => 'Delicious sweet potato fries with a natural sweetness and crispy texture. A healthier alternative to regular fries with more vitamins and fiber.',
                'short_description' => 'Frozen sweet potato fries, naturally sweet',
                'slug' => Str::slug('FryMaster Sweet Potato Fries'),
                'images' => json_encode([
                    'main' => 'products/fries-sweet-main.jpg',
                    'gallery' => [
                        'products/fries-sweet-1.jpg',
                        'products/fries-sweet-2.jpg',
                        'products/fries-sweet-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'FryMaster',
                    'type' => 'Sweet Potato Fries',
                    'weight' => '750g',
                    'cooking_method' => 'Oven or Air Fryer',
                    'cooking_time' => '12-15 minutes',
                    'allergens' => 'Contains: None',
                    'storage' => 'Keep frozen at -18°C or below',
                    'dietary_info' => 'Vegetarian, Vegan, Gluten-Free',
                    'nutrition' => 'Rich in Vitamin A and fiber',
                    'expiry' => '10 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 320,
                'sold_count' => 85,
                'created_at' => now()->subDays(25),
                'updated_at' => now(),
            ],
            // Soft Pretzel Bites
            [
                'inventory_id' => $inventoryIds['SNACK-PRETZEL-001'],
                'product_name' => 'PretzelTime Soft Pretzel Bites with Cheese',
                'price' => 6.99,
                'description' => 'Microwaveable soft pretzel bites served with creamy cheese sauce. Perfect for quick snacks, parties, or game day. Ready in just 90 seconds!',
                'short_description' => 'Soft pretzel bites with cheese sauce',
                'slug' => Str::slug('PretzelTime Soft Pretzel Bites with Cheese'),
                'images' => json_encode([
                    'main' => 'products/pretzel-bites-main.jpg',
                    'gallery' => [
                        'products/pretzel-bites-1.jpg',
                        'products/pretzel-bites-2.jpg',
                        'products/pretzel-bites-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'PretzelTime',
                    'type' => 'Soft Pretzel Bites',
                    'weight' => '400g',
                    'servings' => 'Approx 4',
                    'cooking_method' => 'Microwave or Oven',
                    'cooking_time' => '90 seconds (microwave)',
                    'sauce_included' => 'Yes - Cheese Sauce',
                    'allergens' => 'Contains: Wheat, Milk',
                    'storage' => 'Keep frozen at -18°C or below',
                    'dietary_info' => 'Vegetarian',
                    'expiry' => '8 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 280,
                'sold_count' => 110,
                'created_at' => now()->subDays(20),
                'updated_at' => now(),
            ],
            // Microwave Butter Popcorn
            [
                'inventory_id' => $inventoryIds['SNACK-POPCORN-001'],
                'product_name' => 'PopRight Movie Theater Butter Popcorn',
                'price' => 4.79,
                'description' => 'Movie theater style butter popcorn you can make at home. Microwaveable bags with real butter flavor. Perfect for movie nights and entertainment.',
                'short_description' => 'Movie theater style butter popcorn',
                'slug' => Str::slug('PopRight Movie Theater Butter Popcorn'),
                'images' => json_encode([
                    'main' => 'products/popcorn-butter-main.jpg',
                    'gallery' => [
                        'products/popcorn-butter-1.jpg',
                        'products/popcorn-butter-2.jpg',
                        'products/popcorn-butter-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'PopRight',
                    'flavor' => 'Movie Theater Butter',
                    'pack_size' => '3 x 100g bags',
                    'total_weight' => '300g',
                    'popping_time' => '2-4 minutes',
                    'allergens' => 'Contains: None',
                    'storage' => 'Store in a cool, dry place',
                    'dietary_info' => 'Vegetarian, Vegan',
                    'expiry' => '8 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 600,
                'sold_count' => 320,
                'created_at' => now()->subDays(15),
                'updated_at' => now(),
            ],
            // Cheese Puffs
            [
                'inventory_id' => $inventoryIds['SNACK-CHEESE-001'],
                'product_name' => 'CheezyPuff Cheese Flavored Puffs',
                'price' => 2.99,
                'description' => 'Light, airy, and delicious cheese puffs with irresistible cheesy flavor. Made from corn and covered in savory cheese seasoning. Fun to eat and perfect for snacking.',
                'short_description' => 'Light and airy cheese flavored puffs',
                'slug' => Str::slug('CheezyPuff Cheese Flavored Puffs'),
                'images' => json_encode([
                    'main' => 'products/cheese-puffs-main.jpg',
                    'gallery' => [
                        'products/cheese-puffs-1.jpg',
                        'products/cheese-puffs-2.jpg',
                        'products/cheese-puffs-3.jpg'
                    ]
                ]),
                'attributes' => json_encode([
                    'brand' => 'CheezyPuff',
                    'flavor' => 'Cheese',
                    'weight' => '150g',
                    'serving_size' => '30g',
                    'servings_per_container' => 'Approx 5',
                    'allergens' => 'Contains: Milk',
                    'storage' => 'Store in a cool, dry place',
                    'dietary_info' => 'Vegetarian',
                    'expiry' => '6 months from production'
                ]),
                'status' => 'published',
                'stock_quantity' => 550,
                'sold_count' => 280,
                'created_at' => now()->subDays(10),
                'updated_at' => now(),
            ],
        ];

        // Insert products
        DB::table('products')->insert($products);

        // Get product IDs for category assignment
        $productIds = DB::table('products')->pluck('product_id', 'slug')->toArray();

        // Create product-category relationships
        $productCategories = [];

        foreach ($productIds as $slug => $productId) {
            $categoryId = null;
            
            if (str_contains($slug, 'chips')) {
                $categoryId = $categoryIds['potato-chips'];
            } elseif (str_contains($slug, 'tortilla')) {
                $categoryId = $categoryIds['tortilla-chips'];
            } elseif (str_contains($slug, 'fries')) {
                $categoryId = $categoryIds['french-fries'];
            } elseif (str_contains($slug, 'pretzel')) {
                $categoryId = $categoryIds['pretzels'];
            } elseif (str_contains($slug, 'popcorn')) {
                $categoryId = $categoryIds['popcorn'];
            } elseif (str_contains($slug, 'cheese')) {
                $categoryId = $categoryIds['cheese-snacks'];
            }
            
            if ($categoryId) {
                $productCategories[] = [
                    'product_id' => $productId,
                    'category_id' => $categoryId,
                ];
                
                // Also add to parent category
                if (str_contains($slug, 'fries') || str_contains($slug, 'pretzel-bites')) {
                    $productCategories[] = [
                        'product_id' => $productId,
                        'category_id' => $categoryIds['frozen-foods'],
                    ];
                } else {
                    $productCategories[] = [
                        'product_id' => $productId,
                        'category_id' => $categoryIds['snacks'],
                    ];
                }
            }
        }

        // Insert product-category relationships
        if (!empty($productCategories)) {
            DB::table('product_category_pivot')->insert($productCategories);
        }

        $this->command->info('✅ Successfully seeded 12 snack and fries products with categories!');
        $this->command->info('📊 Products created: Potato chips (3), Tortilla chips (1), French fries (4), Pretzels (1), Popcorn (1), Cheese snacks (1)');
        $this->command->info('🏪 Inventory items created: 15 different snack items');
    }
}