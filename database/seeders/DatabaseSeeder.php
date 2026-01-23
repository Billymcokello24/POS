<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Create demo business
        $business = \App\Models\Business::create([
            'name' => 'Demo Store',
            'business_type' => 'retail',
            'address' => '123 Main Street',
            'phone' => '+1234567890',
            'email' => 'demo@store.com',
            'receipt_prefix' => 'DS',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        // Create demo tax configuration
        $tax = \App\Models\TaxConfiguration::create([
            'business_id' => $business->id,
            'name' => 'VAT',
            'rate' => 16.00,
            'is_inclusive' => false,
            'is_active' => true,
            'priority' => 1,
        ]);

        // Create demo categories
        $electronics = \App\Models\Category::create([
            'business_id' => $business->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $clothing = \App\Models\Category::create([
            'business_id' => $business->id,
            'name' => 'Clothing',
            'slug' => 'clothing',
            'is_active' => true,
        ]);

        // Get admin user created by seeder
        $adminUser = \App\Models\User::where('email', 'admin@pos.com')->first();
        $cashierUser = \App\Models\User::where('email', 'cashier@demo.com')->first();

        // Assign business to users
        if ($adminUser) {
            $adminUser->update(['current_business_id' => $business->id]);
        }
        if ($cashierUser) {
            $cashierUser->update(['current_business_id' => $business->id]);
        }

        // Create demo products
        $products = [
            [
                'name' => 'Laptop HP ProBook',
                'category_id' => $electronics->id,
                'sku' => 'ELEC-001',
                'barcode' => '1234567890123',
                'cost_price' => 500.00,
                'selling_price' => 750.00,
                'quantity' => 10,
                'reorder_level' => 5,
            ],
            [
                'name' => 'Wireless Mouse',
                'category_id' => $electronics->id,
                'sku' => 'ELEC-002',
                'barcode' => '1234567890124',
                'cost_price' => 10.00,
                'selling_price' => 20.00,
                'quantity' => 50,
                'reorder_level' => 10,
            ],
            [
                'name' => 'T-Shirt Blue',
                'category_id' => $clothing->id,
                'sku' => 'CLOTH-001',
                'barcode' => '1234567890125',
                'cost_price' => 5.00,
                'selling_price' => 15.00,
                'quantity' => 100,
                'reorder_level' => 20,
            ],
            [
                'name' => 'Jeans Black',
                'category_id' => $clothing->id,
                'sku' => 'CLOTH-002',
                'barcode' => '1234567890126',
                'cost_price' => 20.00,
                'selling_price' => 45.00,
                'quantity' => 30,
                'reorder_level' => 10,
            ],
        ];

        foreach ($products as $productData) {
            $product = \App\Models\Product::create(array_merge($productData, [
                'business_id' => $business->id,
                'tax_configuration_id' => $tax->id,
                'barcode_type' => 'CODE128',
                'track_inventory' => true,
                'is_active' => true,
            ]));

            // Create initial inventory transaction
            if ($productData['quantity'] > 0) {
                \App\Models\InventoryTransaction::create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'type' => 'IN',
                    'quantity' => $productData['quantity'],
                    'quantity_before' => 0,
                    'quantity_after' => $productData['quantity'],
                    'notes' => 'Initial stock',
                    'created_by' => $adminUser->id,
                ]);
            }
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin Login: admin@pos.com / admin123');
        $this->command->info('Cashier Login: cashier@demo.com / password');
    }
}
