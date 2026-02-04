<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\User;
use App\Events\GeneralNotification;

class ProductObserver
{
    /**
     * Handle the Product "created" event - NEW PRODUCT ADDED
     */
    public function created(Product $product): void
    {
        // Notify all users in the business
        if ($product->business) {
            $users = $product->business->users;

            foreach ($users as $user) {
                // Don't notify the user who created it
                if ($user->id === auth()->id()) {
                    continue;
                }

                broadcast(new GeneralNotification(
                    $user->id,
                    '📦 New Product Added',
                    "New product '{$product->name}' has been added to inventory",
                    'product.created',
                    [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                    ]
                ));
            }
        }
    }
}

