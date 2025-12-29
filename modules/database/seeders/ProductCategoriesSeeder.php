<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        DB::table('product_categories')->delete();
        
        $categories = [
            [
                'name' => 'Snacks',
                'slug' => Str::slug('Snacks'),
                'description' => 'Delicious snacks and finger foods',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Frozen Foods',
                'slug' => Str::slug('Frozen Foods'),
                'description' => 'Frozen food items and ready-to-cook meals',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Beverages',
                'slug' => Str::slug('Beverages'),
                'description' => 'Cold and hot drinks',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Specialties',
                'slug' => Str::slug('Specialties'),
                'description' => 'Special menu items and limited editions',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Combo Meals',
                'slug' => Str::slug('Combo Meals'),
                'description' => 'Value meal combos',
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('product_categories')->insert($categories);
        
        $this->command->info('✅ Product categories seeded successfully!');
        $this->command->info('📊 Total categories: ' . count($categories));
    }
}