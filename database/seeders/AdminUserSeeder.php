<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user if it doesn't exist
        if (!User::where('email', 'admin@pos.com')->exists()) {
            User::create([
                'name' => 'System Administrator',
                'email' => 'admin@pos.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@pos.com');
            $this->command->info('Password: admin123');
        } else {
            $this->command->info('Admin user already exists.');
        }

        // Create demo cashier user if it doesn't exist
        if (!User::where('email', 'cashier@demo.com')->exists()) {
            User::create([
                'name' => 'Demo Cashier',
                'email' => 'cashier@demo.com',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->command->info('Demo cashier user created successfully!');
            $this->command->info('Email: cashier@demo.com');
            $this->command->info('Password: password');
        } else {
            $this->command->info('Demo cashier user already exists.');
        }
    }
}
