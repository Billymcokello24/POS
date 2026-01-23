<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Check if admin user exists
$existingUser = User::where('email', 'admin@pos.com')->first();

if ($existingUser) {
    echo "Admin user already exists: {$existingUser->email}\n";
    echo "Role: {$existingUser->role}\n";
    echo "Active: " . ($existingUser->is_active ? 'Yes' : 'No') . "\n";

    // Update password just in case
    $existingUser->password = Hash::make('admin123');
    $existingUser->role = 'admin';
    $existingUser->is_active = true;
    $existingUser->email_verified_at = now();
    $existingUser->save();
    echo "Password and settings updated!\n";
} else {
    echo "Creating admin user...\n";
    $user = User::create([
        'name' => 'System Administrator',
        'email' => 'admin@pos.com',
        'password' => Hash::make('admin123'),
        'role' => 'admin',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    echo "Admin user created successfully: {$user->email}\n";
}

// Create cashier user
$cashierUser = User::where('email', 'cashier@demo.com')->first();
if (!$cashierUser) {
    echo "\nCreating cashier user...\n";
    $cashier = User::create([
        'name' => 'Demo Cashier',
        'email' => 'cashier@demo.com',
        'password' => Hash::make('password'),
        'role' => 'cashier',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    echo "Cashier user created: {$cashier->email}\n";
} else {
    echo "\nCashier user already exists: {$cashierUser->email}\n";
}

echo "\n=== LOGIN CREDENTIALS ===\n";
echo "Admin: admin@pos.com / admin123\n";
echo "Cashier: cashier@demo.com / password\n";
echo "=========================\n";

