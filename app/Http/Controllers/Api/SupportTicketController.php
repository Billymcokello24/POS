<?php

namespace App\Http\Controllers\Api;

use App\Events\SupportMessageSent;
use App\Events\SupportTyping;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\NewSupportTicket;
use App\Notifications\SupportVerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class SupportTicketController extends Controller
{
    /**
     * Initiate a new support ticket and send verification code.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $ticket = SupportTicket::create([
            'business_id' => auth()->user()->current_business_id,
            'user_id' => auth()->id(),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'verification_code' => $code,
            'is_verified' => false,
        ]);

        // Send verification code to business email
        $business = \App\Models\Business::find(auth()->user()->current_business_id);
        Notification::route('mail', $business->email)->notify(new SupportVerificationCode($code));

        return response()->json([
            'success' => true,
            'ticket_id' => $ticket->id,
            'message' => 'Verification code sent to your email.',
        ]);
    }

    /**
     * Verify the support ticket.
     */
    public function verify(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if ($ticket->verification_code !== $validated['code']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $ticket->update(['is_verified' => true]);

        // Create initial message
        $message = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'message' => $ticket->message,
        ]);

        // Broadcast the initial message
        broadcast(new SupportMessageSent($message))->toOthers();

        // Notify Super Admins now that it's verified
        $superAdmins = User::where('is_super_admin', true)->get();
        Notification::send($superAdmins, new NewSupportTicket($ticket));

        return response()->json([
            'success' => true,
            'message' => 'Support chat verified.',
            'messages' => $ticket->messages()->with('user:id,name')->get(),
        ]);
    }

    /**
     * Send a new message in the support chat.
     */
    public function sendMessage(Request $request, SupportTicket $ticket)
    {
        if (!$ticket->is_verified) {
             return response()->json(['success' => false, 'message' => 'Ticket not verified.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $message = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $validated['message'],
            'is_from_admin' => auth()->user()->is_super_admin,
        ]);

        // Process broadcasting and notifications in background for speed!
        ProcessSupportMessageJob::dispatch($message->id)->onQueue('support');

        return response()->json([
            'success' => true,
            'message' => $message->load('user:id,name'),
        ]);
    }

    /**
     * Broadcast typing indicator.
     */
    public function typing(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        broadcast(new SupportTyping(
            $ticket->id,
            auth()->user(),
            $validated['is_typing']
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Get chat history.
     */
    public function getMessages(SupportTicket $ticket)
    {
        return response()->json([
            'success' => true,
            'messages' => $ticket->messages()->with('user:id,name')->get(),
        ]);
    }
}
