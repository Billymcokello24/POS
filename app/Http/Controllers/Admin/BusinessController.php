<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $newStatus = !$business->is_active;
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
            "Business '{$business->name}' status changed to " . ($business->is_active ? 'Active' : 'Suspended'),
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

        if (!$admin) {
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

        if (!$admin) {
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
        if (!session()->has('impersonating_from')) {
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
        \Illuminate\Support\Facades\Log::info('=== DELETE BUSINESS METHOD CALLED ===', [
            'business_id' => $business->id,
            'business_name' => $business->name,
            'deleted_by' => auth()->id(),
        ]);

        $businessName = $business->name;
        $businessId = $business->id;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($business, $businessId, $businessName) {
                // Delete all related data in the correct order to avoid foreign key constraints

                // 1. Delete sale items (depends on sales)
                \Illuminate\Support\Facades\DB::table('sale_items')
                    ->whereIn('sale_id', function ($query) use ($businessId) {
                        $query->select('id')->from('sales')->where('business_id', $businessId);
                    })->delete();

                // 2. Delete sales
                \Illuminate\Support\Facades\DB::table('sales')->where('business_id', $businessId)->delete();

                // 3. Delete products
                \Illuminate\Support\Facades\DB::table('products')->where('business_id', $businessId)->delete();

                // 4. Delete inventory transactions
                \Illuminate\Support\Facades\DB::table('inventory_transactions')->where('business_id', $businessId)->delete();

                // 5. Delete categories
                \Illuminate\Support\Facades\DB::table('categories')->where('business_id', $businessId)->delete();

                // 6. Delete customers
                if (\Illuminate\Support\Facades\Schema::hasTable('customers')) {
                    \Illuminate\Support\Facades\DB::table('customers')->where('business_id', $businessId)->delete();
                }

                // 7. Delete suppliers
                if (\Illuminate\Support\Facades\Schema::hasTable('suppliers')) {
                    \Illuminate\Support\Facades\DB::table('suppliers')->where('business_id', $businessId)->delete();
                }

                // 8. Delete expenses
                if (\Illuminate\Support\Facades\Schema::hasTable('expenses')) {
                    \Illuminate\Support\Facades\DB::table('expenses')->where('business_id', $businessId)->delete();
                }

                // 9. Delete subscriptions
                \Illuminate\Support\Facades\DB::table('subscriptions')->where('business_id', $businessId)->delete();

                // 10. Delete subscription payments
                \Illuminate\Support\Facades\DB::table('subscription_payments')->where('business_id', $businessId)->delete();

                // 11. Delete mpesa payments
                if (\Illuminate\Support\Facades\Schema::hasTable('mpesa_payments')) {
                    \Illuminate\Support\Facades\DB::table('mpesa_payments')->where('business_id', $businessId)->delete();
                }

                // 12. Delete audit logs
                \Illuminate\Support\Facades\DB::table('audit_logs')->where('business_id', $businessId)->delete();

                // 13. Detach all users from this business (role_user pivot table)
                \Illuminate\Support\Facades\DB::table('role_user')->where('business_id', $businessId)->delete();

                // 14. Detach all features (business_feature pivot table)
                if (\Illuminate\Support\Facades\Schema::hasTable('business_feature')) {
                    \Illuminate\Support\Facades\DB::table('business_feature')->where('business_id', $businessId)->delete();
                }

                // 15. Finally, delete the business itself
                $business->forceDelete();

                \Illuminate\Support\Facades\Log::info('Business deleted successfully', [
                    'business_id' => $businessId,
                    'business_name' => $businessName
                ]);
            });

            // Log the deletion
            \App\Models\AuditLog::log(
                'business.deleted',
                "Business '{$businessName}' (ID: {$businessId}) has been permanently deleted by Super Admin.",
                [
                    'business_id' => $businessId,
                    'business_name' => $businessName,
                    'deleted_by' => auth()->id(),
                ]
            );

            return redirect()->route('admin.businesses.index')->with('success', "Business '{$businessName}' has been permanently deleted.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Business deletion failed', [
                'business_id' => $businessId,
                'business_name' => $businessName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to delete business: ' . $e->getMessage());
        }
    }
}
