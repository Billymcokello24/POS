<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Feature;

class AddCategoriesFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Feature::updateOrCreate(
            ['key' => 'categories'],
            ['name' => 'Categories', 'description' => 'Product categories and grouping']
        );
    }
}

