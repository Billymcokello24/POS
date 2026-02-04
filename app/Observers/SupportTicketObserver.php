<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Events\GeneralNotification;

class SupportTicketObserver
{
    /**
     * Handle the SupportTicket "created" event - NEW SUPPORT TICKET
     */
    public function created(SupportTicket $ticket): void
    {
        // Notify all super admins about new support ticket
        $admins = User::where('is_super_admin', true)->get();

        foreach ($admins as $admin) {
            broadcast(new GeneralNotification(
                $admin->id,
                '🎫 New Support Ticket',
                "New ticket from {$ticket->user->name}: {$ticket->subject}",
                'support.ticket.created',
                [
                    'ticket_id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'user_name' => $ticket->user->name,
                ]
            ));
        }
    }
}

