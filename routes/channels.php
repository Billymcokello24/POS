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

// Only register channels during HTTP requests, not during artisan commands
if (!app()->runningInConsole() || request()->is('broadcasting/*')) {
    Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id;
    });

    // Admin private notifications channel
    Broadcast::channel('private-admin.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id && $user->is_super_admin;
    });

    // Business private notifications channel
    Broadcast::channel('private-business.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id && !$user->is_super_admin;
    });

    // Chat messages channel
    Broadcast::channel('private-chat.{chatId}', function ($user, $chatId) {
        // Allow users to listen to any chat they're part of
        return true;
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
}

