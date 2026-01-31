<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::where('is_super_admin', true)
            ->latest()
            ->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_super_admin' => true,
        ]);

        return back()->with('success', 'SuperAdmin user created successfully.');
    }

    public function update(Request $request, User $admin_user)
    {
        if (!$admin_user->is_super_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $admin_user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $admin_user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($validated['password']) {
            $admin_user->update(['password' => Hash::make($validated['password'])]);
        }

        return back()->with('success', 'SuperAdmin user updated successfully.');
    }

    public function destroy(User $admin_user)
    {
        if (!$admin_user->is_super_admin || $admin_user->id === auth()->id()) {
            return back()->with('error', 'Cannot delete this user.');
        }

        $admin_user->delete();

        return back()->with('success', 'SuperAdmin user deleted successfully.');
    }
}
