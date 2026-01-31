<?php

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Support ticket private channel - allows ticket owner and super admins
Broadcast::channel('support.ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = SupportTicket::find($ticketId);
    
    if (!$ticket) {
        return false;
    }
    
    // Allow super admins
    if ($user->is_super_admin) {
        return ['id' => $user->id, 'name' => $user->name, 'is_admin' => true];
    }
    
    // Allow the ticket owner
    if ($ticket->user_id === $user->id) {
        return ['id' => $user->id, 'name' => $user->name, 'is_admin' => false];
    }
    
    return false;
});
