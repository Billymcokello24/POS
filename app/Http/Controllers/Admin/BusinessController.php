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

    public function toggleStatus(Business $business)
    {
        $business->update(['is_active' => ! $business->is_active]);

        \App\Models\AuditLog::log(
            'business.status_updated',
            "Business '{$business->name}' status changed to ".($business->is_active ? 'Active' : 'Suspended'),
            ['business_id' => $business->id]
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

        return back()->with('success', "Password for {$admin->email} reset to: $password");
    }

    public function impersonate(Business $business)
    {
        $admin = $business->users()->wherePivot('role_id', function ($q) {
            $q->select('id')->from('roles')->where('name', 'admin');
        })->first() ?: $business->users()->first();

        if (! $admin) {
            return back()->with('error', 'No user found to impersonate.');
        }

        \App\Models\AuditLog::log(
            'auth.impersonation_start',
            "Super Admin started impersonating '{$admin->email}' (Business: {$business->name})",
            ['business_id' => $business->id, 'target_user_id' => $admin->id]
        );

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
}
