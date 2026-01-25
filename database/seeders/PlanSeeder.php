<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $features = Feature::all();

        $plans = [
            [
                'name' => 'Small Business',
                'description' => 'Perfect for kiosks and micro-retailers.',
                'price_monthly' => 1500,
                'price_yearly' => 15000,
                'max_users' => 2,
                'max_employees' => 5,
                'max_products' => 100,
                'features' => ['pos', 'products', 'categories', 'inventory'],
            ],
            [
                'name' => 'Medium Business',
                'description' => 'Scaling retail businesses with multiple staff.',
                'price_monthly' => 3500,
                'price_yearly' => 35000,
                'max_users' => 10,
                'max_employees' => 20,
                'max_products' => 2000,
                'features' => ['pos', 'products', 'categories', 'inventory', 'reports', 'users', 'customers', 'expenses'],
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Unlimited power for high-volume retail chains.',
                'price_monthly' => 10000,
                'price_yearly' => 100000,
                'max_users' => 0,
                'max_employees' => 0,
                'max_products' => 0,
                'features' => ['pos', 'products', 'categories', 'inventory', 'reports', 'users', 'customers', 'expenses', 'loyalty', 'business_settings'],
            ],
        ];

        foreach ($plans as $pData) {
            $planFeatures = $pData['features'];
            unset($pData['features']);

            $pData['slug'] = Str::slug($pData['name']);

            $plan = Plan::updateOrCreate(['slug' => $pData['slug']], $pData);

            // Map features by key
            $featureIds = $features->whereIn('key', $planFeatures)->pluck('id');
            $plan->features()->sync($featureIds);
        }
    }
}
