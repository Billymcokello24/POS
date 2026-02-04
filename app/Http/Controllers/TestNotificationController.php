<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Events\GeneralNotification;
use Illuminate\Support\Facades\Broadcast;

class TestNotificationController extends Controller
{
    /**
     * Test sending a notification to current user
     */
    public function test()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        // Send a test notification
        $channel = $user->is_super_admin
            ? "private-admin.{$user->id}"
            : "private-business.{$user->id}";

        // Broadcast the event
        broadcast(new GeneralNotification(
            $user->id,
            '🧪 Test Notification',
            'This is a test notification to verify your notification system is working!',
            'test.notification',
            ['test' => true]
        ));

        // Also save to database via notification
        $user->notify(new \App\Notifications\SubscriptionCreatedNotification(
            Subscription::first() ?? new Subscription(['plan_name' => 'Test Plan', 'business_id' => 1])
        ));

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent!',
            'channel' => $channel,
            'user_id' => $user->id,
            'is_admin' => $user->is_super_admin,
        ]);
    }
}

