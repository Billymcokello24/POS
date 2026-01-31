<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        // Text Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('event', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhereHas('user', function ($uq) use ($request) {
                        $uq->where('name', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%");
                    });
            });
        }

        // Event Filter
        if ($request->event) {
            $query->where('event', $request->event);
        }

        // User Filter
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Date Range
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()
            ->paginate(30)
            ->withQueryString();

        // Metadata for filters
        $eventTypes = AuditLog::select('event')->distinct()->pluck('event');
        $admins = \App\Models\User::where('is_super_admin', true)->get(['id', 'name', 'email']);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'event', 'user_id', 'date_from', 'date_to']),
            'eventTypes' => $eventTypes,
            'admins' => $admins,
        ]);
    }
}
