<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('admin123'),
                'is_super_admin' => true,
                'is_active' => true,
            ]
        );
    }
}
