<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Notifications\BusinessRegistered;
use Inertia\Inertia;

class BusinessAuthController extends Controller
{
    public function create()
    {
        return Inertia::render('auth/RegisterBusiness');
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::transaction(function () use ($request) {
            // 1. Create Business
            $business = Business::create([
                'name' => $request->business_name,
                'email' => $request->email,
                'currency' => 'KES', // Default
                'is_active' => true,
            ]);

            // 2. Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'current_business_id' => $business->id,
                'is_active' => true,
                'role' => 'admin', // Legacy support
            ]);

            // 3. Assign Admin Role for this Business
            // Find or create the Admin role
            $adminRole = Role::firstOrCreate(['name' => 'admin'], [
                'display_name' => 'Administrator',
                'description' => 'Full access to business settings and data',
                'level' => 100,
            ]);

            $user->roles()->attach($adminRole->id, ['business_id' => $business->id]);

            // 4. Login
            Auth::login($user);

            // 5. Notify the user
            $user->notify(new BusinessRegistered($business));
        });

        return redirect()->route('dashboard');
    }
}
