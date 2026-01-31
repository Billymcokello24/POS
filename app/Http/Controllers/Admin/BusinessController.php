<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $query = Business::query()->withCount(['users', 'sales']);

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $businesses = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businesses,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Business $business)
    {
        $business->load(['subscriptions.plan', 'plan']);
        
        $users = $business->users()
            ->select('users.*', 'roles.display_name as role_name')
            ->leftJoin('roles', 'role_user.role_id', '=', 'roles.id')
            ->get();

        $payments = \App\Models\SubscriptionPayment::where('business_id', $business->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Businesses/Show', [
            'business' => array_merge($business->toArray(), ['users' => $users]),
            'payments' => $payments,
            'features' => $business->getEnabledFeatureKeys(),
        ]);
    }

    public function toggleStatus(Request $request, Business $business)
    {
        $newStatus = ! $business->is_active;
        $reason = $request->input('reason');

        $business->update([
            'is_active' => $newStatus,
            'suspension_reason' => $newStatus ? null : ($reason ?: $business->suspension_reason)
        ]);

        // If suspending, we store the reason. If restoring, we clear it.
        if ($newStatus) {
            $business->update(['suspension_reason' => null]);
        } else {
            if ($reason) {
                $business->update(['suspension_reason' => $reason]);
            }
        }

        // Notify Business Admin
        $admin = $business->users()->wherePivot('role_id', function ($q) {
            $q->select('id')->from('roles')->where('name', 'admin');
        })->first() ?: $business->users()->first();

        if ($admin) {
            if ($business->is_active) {
                // When restoring, we reset the password to a temporary one for easy access
                $tempPassword = 'Password123!';
                $admin->update(['password' => Hash::make($tempPassword)]);
                $admin->notify(new \App\Notifications\BusinessRestored($business, $admin->email, $tempPassword));
            } else {
                $admin->notify(new \App\Notifications\BusinessSuspended($business, $business->suspension_reason));
            }
        }

        \App\Models\AuditLog::log(
            'business.status_updated',
            "Business '{$business->name}' status changed to ".($business->is_active ? 'Active' : 'Suspended'),
            [
                'business_id' => $business->id,
                'reason' => $business->suspension_reason
            ]
        );

        return back()->with('success', 'Business status updated successfully.');
    }

    public function resetAdminPassword(Request $request, Business $business)
    {
        // Find admin user...
        $admin = $business->users()->wherePivot('role_id', function ($q) {
            $q->select('id')->from('roles')->where('name', 'admin');
        })->first() ?: $business->users()->first();

        if (! $admin) {
            return back()->with('error', 'No admin user found for this business.');
        }

        $password = 'Password123!';
        $admin->update(['password' => Hash::make($password)]);

        \App\Models\AuditLog::log(
            'business.password_reset',
            "Admin password for '{$business->name}' ({$admin->email}) was reset by Super Admin.",
            ['business_id' => $business->id, 'user_id' => $admin->id]
        );

        return back()->with('success', "Password reset for {$admin->email}")
            ->with('new_password', [
                'email' => $admin->email,
                'password' => $password
            ]);
    }

    public function impersonate(Request $request, Business $business)
    {
        $admin = $business->users()->wherePivot('role_id', function ($q) {
            $q->select('id')->from('roles')->where('name', 'admin');
        })->first() ?: $business->users()->first();

        if (! $admin) {
            return back()->with('error', 'No user found to impersonate.');
        }

        $reason = $request->input('reason', 'Routine maintenance/support');

        \App\Models\AuditLog::log(
            'auth.impersonation_start',
            "Super Admin started impersonating '{$admin->email}' (Business: {$business->name}). Reason: {$reason}",
            ['business_id' => $business->id, 'target_user_id' => $admin->id, 'reason' => $reason]
        );

        // Notify the business admin
        $admin->notify(new \App\Notifications\ImpersonationStarted($reason, auth()->user()->name));

        session(['impersonating_from' => auth()->id()]);
        auth()->login($admin);

        return redirect()->route('dashboard')->with('success', "Now impersonating {$business->name}");
    }

    public function stopImpersonating()
    {
        if (! session()->has('impersonating_from')) {
            return redirect()->route('dashboard');
        }

        $originalId = session()->pull('impersonating_from');
        $originalUser = User::findOrFail($originalId);

        \App\Models\AuditLog::log(
            'auth.impersonation_stop',
            'Super Admin ended impersonation and returned to their session.',
            ['original_user_id' => $originalId]
        );

        auth()->login($originalUser);

        return redirect()->route('admin.businesses.index')->with('success', 'Impersonation ended.');
    }

    public function destroy(Business $business)
    {
        $businessName = $business->name;
        $businessId = $business->id;

        try {
            // Get count of users for logging
            $userCount = $business->users()->count();
            $userIds = $business->users()->pluck('users.id')->toArray();

            // Log before deletion
            \App\Models\AuditLog::log(
                'business.deleted',
                "Business '{$businessName}' (ID: {$businessId}) with {$userCount} user(s) was permanently deleted by Super Admin.",
                [
                    'business_id' => $businessId, 
                    'business_name' => $businessName,
                    'user_count' => $userCount,
                    'user_ids' => $userIds
                ]
            );


            \Illuminate\Support\Facades\DB::transaction(function () use ($business, $userIds) {
                // 1. Delete users FIRST (before detaching, so we can check their business count)
                foreach ($userIds as $userId) {
                    $user = User::find($userId);
                    if ($user && !$user->is_super_admin) { // Never delete super admins
                        // Check if user has other businesses BEFORE we detach
                        $otherBusinessCount = \Illuminate\Support\Facades\DB::table('role_user')
                            ->where('user_id', $userId)
                            ->where('business_id', '!=', $business->id)
                            ->count();
                        
                        // If no other businesses, delete the user account completely
                        if ($otherBusinessCount === 0) {
                            // Delete from users table directly
                            \Illuminate\Support\Facades\DB::table('users')
                                ->where('id', $userId)
                                ->delete();
                        }
                    }
                }

                // 2. Now detach all users from this business (clean up role_user pivot)
                $business->users()->detach();

                // 3. Detach all features
                $business->features()->detach();

                // 4. Delete related records if needed
                // Optionally delete products, sales, etc. for complete removal
                // $business->products()->delete();
                // $business->sales()->delete();
                
                // 5. Force delete the business (bypass soft delete)
                $business->forceDelete();
            });

            return back()->with('success', "Business '{$businessName}' and all associated user accounts have been permanently deleted.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Business deletion failed', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to delete business: ' . $e->getMessage());
        }
    }
}
