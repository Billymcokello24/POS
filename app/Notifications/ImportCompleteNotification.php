<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportCompleteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $type;
    protected $imported;
    protected $updated;
    protected $errors;
    protected $warnings;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $type, int $imported, int $updated, int $errors, int $warnings)
    {
        $this->type = $type;
        $this->imported = $imported;
        $this->updated = $updated;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("✅ {$this->type} Import Complete")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your {$this->type} import has been completed.");

        if ($this->imported > 0) {
            $message->line("✅ {$this->imported} new records imported");
        }
        if ($this->updated > 0) {
            $message->line("🔄 {$this->updated} records updated");
        }
        if ($this->errors > 0) {
            $message->line("❌ {$this->errors} errors occurred");
        }
        if ($this->warnings > 0) {
            $message->line("⚠️ {$this->warnings} warnings");
        }

        return $message
            ->action('View Details', url('/products'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'import_complete',
            'title' => "✅ {$this->type} Import Complete",
            'message' => "{$this->imported} imported, {$this->updated} updated, {$this->errors} errors",
            'imported' => $this->imported,
            'updated' => $this->updated,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
