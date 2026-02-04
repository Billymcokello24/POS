<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        return ['database', 'broadcast'];
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

