<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        // Only admins can view users
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        $businessId = $request->user()->current_business_id;

        // Only show users belonging to the current business
        $query = User::whereHas('roles', function ($q) use ($businessId) {
            $q->where('business_id', $businessId);
        })->where('id', '!=', $request->user()->id);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->paginate(15)->through(function ($user) use ($businessId) {
            $roleForBusiness = $user->roles()
                ->wherePivot('business_id', $businessId)
                ->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleForBusiness?->display_name ?? $user->role,
                'role_id' => $roleForBusiness?->id,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'last_login' => $user->last_login_at ?? null,
            ];
        });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status']),
            'availableRoles' => \App\Models\Role::orderBy('level', 'desc')->get(),
        ]);
    }

    public function create(Request $request)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Users/Create', [
            'roles' => \App\Models\Role::orderBy('level', 'desc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ]);

        $currentBusinessId = $request->user()->current_business_id;

        // Plan Limit Check
        $business = \App\Models\Business::find($currentBusinessId);
        if ($business && !$business->withinPlanLimits('users')) {
            return back()->with('error', 'Your current plan limit for employees (' . $business->plan->max_users . ') has been reached. Please upgrade to add more.');
        }

        DB::transaction(function () use ($validated, $currentBusinessId) {
            $role = \App\Models\Role::findOrFail($validated['role_id']);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $role->name, // Keep for legacy/UI check
                'is_active' => $validated['is_active'] ?? true,
                'current_business_id' => $currentBusinessId,
            ]);

            $user->roles()->attach($role->id, ['business_id' => $currentBusinessId]);
        });

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    private function ensureUserBelongsToBusiness($user, $businessId)
    {
        // specific check: does this user have a role in this business?
        $belongs = $user->roles()->wherePivot('business_id', $businessId)->exists();
        if (!$belongs) {
            abort(404); // Hide existence
        }
    }

    public function show(Request $request, User $user)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        $this->ensureUserBelongsToBusiness($user, $request->user()->current_business_id);

        return Inertia::render('Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    public function edit(Request $request, User $user)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        $this->ensureUserBelongsToBusiness($user, $request->user()->current_business_id);

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->roles()->wherePivot('business_id', $request->user()->current_business_id)->first()?->id,
                'is_active' => $user->is_active,
            ],
            'roles' => \App\Models\Role::orderBy('level', 'desc')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ]);

        $this->ensureUserBelongsToBusiness($user, $request->user()->current_business_id);

        $role = \App\Models\Role::findOrFail($validated['role_id']);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $role->name,
            'is_active' => $validated['is_active'] ?? false,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync role for this business
        $user->roles()->wherePivot('business_id', $request->user()->current_business_id)->detach();
        $user->roles()->attach($role->id, ['business_id' => $request->user()->current_business_id]);

        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        // Prevent deleting self
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account']);
        }

        $this->ensureUserBelongsToBusiness($user, $request->user()->current_business_id);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully');
    }

    public function toggleStatus(Request $request, User $user)
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Unauthorized');
        }

        $this->ensureUserBelongsToBusiness($user, $request->user()->current_business_id);

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'User status updated successfully');
    }
}
