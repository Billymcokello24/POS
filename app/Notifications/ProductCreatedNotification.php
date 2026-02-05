<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📦 New Product Added to Inventory')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("Product '{$this->product->name}' has been successfully added to your inventory.")
            ->action('View Product', url('/products/' . $this->product->id))
            ->line('Your inventory has been updated!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product.created',
            'title' => 'New Product Added',
            'message' => "Product '{$this->product->name}' has been added to inventory",
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'icon' => '📦',
        ];
    }
}

