<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feature;

class FeatureSeeder extends Seeder
{
    public function run()
    {
        $features = [
            ['name' => 'Products', 'key' => 'products', 'description' => 'Access to product management'],
            ['name' => 'Sales', 'key' => 'sales', 'description' => 'Point of sale and sales management'],
            ['name' => 'Inventory', 'key' => 'inventory', 'description' => 'Inventory tracking and adjustments'],
            ['name' => 'Reports', 'key' => 'reports', 'description' => 'Access to reporting features'],
            ['name' => 'Business Settings', 'key' => 'business_settings', 'description' => 'Allow access to business settings page'],
            ['name' => 'Subscriptions', 'key' => 'subscriptions', 'description' => 'Subscription management and billing'],
            ['name' => 'AI Agent', 'key' => 'ai_agent', 'description' => 'AI inventory assistant (search/reports)'],
            ['name' => 'Point of Sale', 'key' => 'pos', 'description' => 'Core point-of-sale checkout feature.'],
            ['name' => 'Inventory Management', 'key' => 'inventory', 'description' => 'Track stock and inventory transactions.'],
            ['name' => 'Reporting & Analytics', 'key' => 'reports', 'description' => 'Sales and inventory reports.'],
            ['name' => 'Multi-Branch', 'key' => 'multi_branch', 'description' => 'Support multiple physical locations.'],
            ['name' => 'API Access', 'key' => 'api_access', 'description' => 'Access platform APIs for integrations.'],
            ['name' => 'SMS Notifications', 'key' => 'sms_notifications', 'description' => 'Send transactional SMS notifications.' ],
            ['name' => 'Categories', 'key' => 'categories', 'description' => 'Product categories and grouping.'],
            // add any other features your app expects here
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['key' => $f['key']], $f);
        }
    }
}
