<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenericBulkEmail extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subject;
    protected $content;

    /**
     * Create a new notification instance.
     */
    public function __construct($subject, $content)
    {
        $this->subject = $subject;
        $this->content = $content;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $business = $notifiable->currentBusiness ?? $notifiable->businesses()->first();
        
        $placeholders = [
            '{business_name}' => $business ? $business->name : 'Your Business',
            '{admin_name}' => $notifiable->name,
            '{business_email}' => $business ? $business->email : $notifiable->email,
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $this->subject);
        $content = str_replace(array_keys($placeholders), array_values($placeholders), $this->content);

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . ',');

        // Process markdown/content lines
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $mail->line(trim($line));
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->subject,
            'content' => $this->content,
        ];
    }
}
