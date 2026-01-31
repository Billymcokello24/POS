<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $ticketId;
    public int $userId;
    public string $userName;
    public bool $isAdmin;
    public bool $isTyping;

    /**
     * Create a new event instance.
     */
    public function __construct(int $ticketId, User $user, bool $isTyping = true)
    {
        $this->ticketId = $ticketId;
        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->isAdmin = $user->is_super_admin;
        $this->isTyping = $isTyping;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.ticket.' . $this->ticketId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'is_admin' => $this->isAdmin,
            'is_typing' => $this->isTyping,
        ];
    }
}
