<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $filename;
    protected $path;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $filename, string $path)
    {
        $this->filename = $filename;
        $this->path = $path;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'report_ready',
            'title' => '📊 Report Ready',
            'message' => "Your report '{$this->filename}' is ready for download.",
            'filename' => $this->filename,
            'path' => $this->path,
            'download_url' => route('api.download.export', ['path' => base64_encode($this->path)]),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'report_ready',
            'title' => '📊 Report Ready',
            'message' => "Your report '{$this->filename}' is ready for download.",
            'filename' => $this->filename,
            'download_url' => route('api.download.export', ['path' => base64_encode($this->path)]),
        ];
    }
}
