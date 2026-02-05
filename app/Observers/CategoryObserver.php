<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\User;
use App\Events\GeneralNotification;

class CategoryObserver
{
    /**
     * Handle the Category "created" event - NEW CATEGORY ADDED
     */
    public function created(Category $category): void
    {
        // Notify all users in the business
        if ($category->business) {
            $users = $category->business->users;

            foreach ($users as $user) {
                // Don't notify the user who created it
                if ($user->id === auth()->id()) {
                    continue;
                }

                broadcast(new GeneralNotification(
                    $user->id,
                    '🏷️ New Category Added',
                    "New category '{$category->name}' has been created",
                    'category.created',
                    [
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                    ]
                ));
            }
        }
    }
}

