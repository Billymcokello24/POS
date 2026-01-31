<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSupportTicket extends Notification
{
    use Queueable;

    protected $ticket;

    /**
     * Create a new notification instance.
     */
    public function __construct($ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('New Support Ticket: ' . $this->ticket->subject)
                    ->greeting('Hello ' . $notifiable->name . ',')
                    ->line('A new support ticket has been submitted by ' . $this->ticket->user->name . ' from ' . $this->ticket->business->name . '.')
                    ->line('**Subject:** ' . $this->ticket->subject)
                    ->line('**Priority:** ' . ucfirst($this->ticket->priority))
                    ->line('**Message:**')
                    ->line($this->ticket->message)
                    ->action('View Support Tickets', url('/admin/support'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'business_name' => $this->ticket->business->name,
            'user_name' => $this->ticket->user->name,
            'subject' => $this->ticket->subject,
            'priority' => $this->ticket->priority,
        ];
    }
}
