<?php

namespace App\Notifications;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CategoryCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $category;

    public function __construct(Category $category)
    {
        $this->category = $category;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🏷️ New Category Created')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("A new category '{$this->category->name}' has been created.")
            ->action('View Categories', url('/categories'))
            ->line('Your inventory is now better organized!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'category.created',
            'title' => 'New Category Added',
            'message' => "Category '{$this->category->name}' has been created",
            'category_id' => $this->category->id,
            'category_name' => $this->category->name,
            'icon' => '🏷️',
        ];
    }
}

