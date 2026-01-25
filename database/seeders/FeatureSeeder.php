<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feature;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            ['name' => 'Point of Sale', 'key' => 'pos', 'description' => 'Core point-of-sale checkout feature.'],
            ['name' => 'Inventory Management', 'key' => 'inventory', 'description' => 'Track stock and inventory transactions.'],
            ['name' => 'Reporting & Analytics', 'key' => 'reports', 'description' => 'Sales and inventory reports.'],
            ['name' => 'Multi-Branch', 'key' => 'multi_branch', 'description' => 'Support multiple physical locations.'],
            ['name' => 'API Access', 'key' => 'api_access', 'description' => 'Access platform APIs for integrations.'],
            ['name' => 'SMS Notifications', 'key' => 'sms_notifications', 'description' => 'Send transactional SMS notifications.' ],
            ['name' => 'Employee Management', 'key' => 'employees', 'description' => 'Manage employee records and roles.'],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['key' => $f['key']], $f);
        }
    }
}

