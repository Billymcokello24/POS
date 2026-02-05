<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Notifications\GenericBulkEmail;
use App\Jobs\SendBulkEmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class BulkEmailController extends Controller
{
    public function index(Request $request)
    {
        $selectedIds = $request->input('ids', []);

        $businesses = [];
        if (!empty($selectedIds)) {
            $businesses = Business::whereIn('id', $selectedIds)->get(['id', 'name', 'email']);
        }

        return Inertia::render('Admin/BulkEmail', [
            'selectedBusinesses' => $businesses,
            'filters' => [
                'ids' => $selectedIds
            ]
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'recipients' => 'required|string|in:all,active,suspended,selected',
            'selected_ids' => 'required_if:recipients,selected|array',
        ]);

        $query = Business::query();

        if ($request->recipients === 'active') {
            $query->where('is_active', true);
        } elseif ($request->recipients === 'suspended') {
            $query->where('is_active', false);
        } elseif ($request->recipients === 'selected') {
            $query->whereIn('id', $request->selected_ids);
        }

        $businesses = $query->get();
        $sentCount = 0;

        foreach ($businesses as $business) {
            // Find admin user(s) for this business
            $admin = $business->users()->wherePivot('role_id', function ($q) {
                $q->select('id')->from('roles')->where('name', 'admin');
            })->first() ?: $business->users()->first();

            if ($admin) {
                // Dispatch to queue for async processing - much faster!
                SendBulkEmailJob::dispatch($admin, $request->subject, $request->content);
                $sentCount++;
            }
        }

        \App\Models\AuditLog::log(
            'admin.bulk_email_sent',
            "Bulk email '{$request->subject}' sent to {$sentCount} businesses.",
            [
                'subject' => $request->subject,
                'recipient_type' => $request->recipients,
                'sent_count' => $sentCount
            ]
        );

        return redirect()->route('admin.businesses.index')->with('success', "Bulk email queued for {$sentCount} recipients.");
    }
}
