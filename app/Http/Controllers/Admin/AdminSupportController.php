<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Inertia\Inertia;

class AdminSupportController extends Controller
{
    /**
     * Display list of all support tickets.
     */
    public function index()
    {
        $tickets = SupportTicket::with(['business:id,name,email', 'user:id,name,email'])
            ->whereNotNull('is_verified')
            ->where('is_verified', true)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
        ]);
    }

    /**
     * Get ticket details with messages (for API/AJAX call from chat panel).
     */
    public function show(SupportTicket $ticket)
    {
        $ticket->load([
            'messages' => fn($q) => $q->with('user:id,name')->orderBy('created_at'),
            'business:id,name,email',
            'user:id,name,email',
        ]);

        return response()->json([
            'ticket' => $ticket,
        ]);
    }
}
