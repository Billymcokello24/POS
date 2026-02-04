<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ImpersonationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $adminName;
    protected $action;

    public function __construct(string $adminName, string $action = 'started')
    {
        $this->adminName = $adminName;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $isStarted = $this->action === 'started';

        return [
            'type' => $isStarted ? 'impersonation.started' : 'impersonation.ended',
            'title' => $isStarted ? 'Impersonation Started' : 'Impersonation Ended',
            'message' => $isStarted
                ? "Admin {$this->adminName} has started impersonating your account"
                : "Impersonation session by {$this->adminName} has ended",
            'admin_name' => $this->adminName,
            'action' => $this->action,
            'icon' => $isStarted ? '🔐' : '🔓',
        ];
    }
}

