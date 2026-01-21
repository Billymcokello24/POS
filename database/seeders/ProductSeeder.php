<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create business
        $business = DB::table('businesses')->first();
        if (!$business) {
            DB::table('businesses')->insert([
                'name' => 'Demo Store',
                'business_type' => 'retail',
                'address' => '123 Main Street',
                'phone' => '+1234567890',
                'email' => 'demo@store.com',
                'receipt_prefix' => 'DS',
                'currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $business = DB::table('businesses')->first();
        }

        // Update users with business
        DB::table('users')->update(['current_business_id' => $business->id]);

        // Create categories
        $electronicsId = DB::table('categories')->where('slug', 'electronics')->value('id');
        if (!$electronicsId) {
            DB::table('categories')->insert([
                'business_id' => $business->id,
                'name' => 'Electronics',
                'slug' => 'electronics',
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $electronicsId = DB::table('categories')->where('slug', 'electronics')->value('id');
        }

        $clothingId = DB::table('categories')->where('slug', 'clothing')->value('id');
        if (!$clothingId) {
            DB::table('categories')->insert([
                'business_id' => $business->id,
                'name' => 'Clothing',
                'slug' => 'clothing',
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $clothingId = DB::table('categories')->where('slug', 'clothing')->value('id');
        }

        $foodId = DB::table('categories')->where('slug', 'food-beverage')->value('id');
        if (!$foodId) {
            DB::table('categories')->insert([
                'business_id' => $business->id,
                'name' => 'Food & Beverage',
                'slug' => 'food-beverage',
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $foodId = DB::table('categories')->where('slug', 'food-beverage')->value('id');
        }

        // Create products
        $products = [
            [
                'business_id' => $business->id,
                'category_id' => $electronicsId,
                'name' => 'Laptop HP ProBook 450 G8',
                'sku' => 'ELEC-001',
                'barcode' => '1234567890123',
                'barcode_type' => 'CODE128',
                'description' => 'HP ProBook 450 G8 - Intel Core i5, 8GB RAM, 256GB SSD',
                'cost_price' => 600.00,
                'selling_price' => 750.00,
                'quantity' => 10,
                'reorder_level' => 5,
                'unit' => 'pcs',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $electronicsId,
                'name' => 'Wireless Mouse Logitech',
                'sku' => 'ELEC-002',
                'barcode' => '1234567890124',
                'barcode_type' => 'CODE128',
                'description' => 'Logitech Wireless Mouse',
                'cost_price' => 15.00,
                'selling_price' => 20.00,
                'quantity' => 8,
                'reorder_level' => 10,
                'unit' => 'pcs',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $electronicsId,
                'name' => 'USB-C Charging Cable',
                'sku' => 'ELEC-003',
                'barcode' => '1234567890127',
                'barcode_type' => 'CODE128',
                'description' => 'USB-C Cable 2M',
                'cost_price' => 5.00,
                'selling_price' => 8.00,
                'quantity' => 75,
                'reorder_level' => 20,
                'unit' => 'pcs',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $clothingId,
                'name' => 'T-Shirt Blue Cotton',
                'sku' => 'CLOTH-001',
                'barcode' => '1234567890125',
                'barcode_type' => 'CODE128',
                'description' => '100% Cotton Blue T-Shirt',
                'cost_price' => 10.00,
                'selling_price' => 15.00,
                'quantity' => 15,
                'reorder_level' => 20,
                'unit' => 'pcs',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $clothingId,
                'name' => 'Jeans Black Denim',
                'sku' => 'CLOTH-002',
                'barcode' => '1234567890126',
                'barcode_type' => 'CODE128',
                'description' => 'Black Denim Jeans',
                'cost_price' => 35.00,
                'selling_price' => 45.00,
                'quantity' => 30,
                'reorder_level' => 10,
                'unit' => 'pcs',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $clothingId,
                'name' => 'Sneakers White',
                'sku' => 'CLOTH-003',
                'barcode' => '1234567890128',
                'barcode_type' => 'CODE128',
                'description' => 'White Canvas Sneakers',
                'cost_price' => 25.00,
                'selling_price' => 40.00,
                'quantity' => 20,
                'reorder_level' => 8,
                'unit' => 'pairs',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $foodId,
                'name' => 'Coffee Beans Premium',
                'sku' => 'FOOD-001',
                'barcode' => '1234567890129',
                'barcode_type' => 'CODE128',
                'description' => 'Premium Arabica Coffee',
                'cost_price' => 12.00,
                'selling_price' => 18.00,
                'quantity' => 50,
                'reorder_level' => 15,
                'unit' => 'bags',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'business_id' => $business->id,
                'category_id' => $foodId,
                'name' => 'Organic Green Tea',
                'sku' => 'FOOD-002',
                'barcode' => '1234567890130',
                'barcode_type' => 'CODE128',
                'description' => 'Organic Green Tea',
                'cost_price' => 8.00,
                'selling_price' => 12.00,
                'quantity' => 35,
                'reorder_level' => 10,
                'unit' => 'boxes',
                'track_inventory' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($products as $product) {
            if (!DB::table('products')->where('sku', $product['sku'])->exists()) {
                DB::table('products')->insert($product);
            }
        }

        $count = DB::table('products')->count();
        $this->command->info("✅ Products in database: {$count}");
    }
}

