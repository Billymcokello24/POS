<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Events\GeneralNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class DiagnosticNotificationController extends Controller
{
    /**
     * Comprehensive diagnostic of notification system
     */
    public function diagnose()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $results = [
            'user_id' => $user->id,
            'is_admin' => $user->is_super_admin,
            'channel' => $user->is_super_admin
                ? "private-admin.{$user->id}"
                : "private-business.{$user->id}",
            'tests' => []
        ];

        // Test 1: Broadcast a notification
        try {
            Log::info('TEST: Broadcasting notification', ['user_id' => $user->id]);

            broadcast(new GeneralNotification(
                $user->id,
                '🧪 Test Broadcast',
                'This is a test broadcast notification',
                'test.broadcast',
                ['timestamp' => now()->toDateTimeString()]
            ));

            $results['tests'][] = [
                'name' => 'Broadcast Event',
                'status' => 'success',
                'message' => 'Event broadcasted successfully'
            ];
        } catch (\Exception $e) {
            $results['tests'][] = [
                'name' => 'Broadcast Event',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // Test 2: Save to database
        try {
            Log::info('TEST: Saving notification to database', ['user_id' => $user->id]);

            $user->notify(new \App\Notifications\SubscriptionCreatedNotification(
                Subscription::first() ?? new Subscription([
                    'plan_name' => 'Test Plan',
                    'business_id' => 1,
                    'status' => 'active'
                ])
            ));

            $results['tests'][] = [
                'name' => 'Database Notification',
                'status' => 'success',
                'message' => 'Notification saved to database'
            ];
        } catch (\Exception $e) {
            $results['tests'][] = [
                'name' => 'Database Notification',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // Test 3: Check API endpoint
        try {
            $notifications = $user->notifications()->latest()->take(5)->get();

            $results['tests'][] = [
                'name' => 'API Endpoint',
                'status' => 'success',
                'message' => "Found {$notifications->count()} notifications",
                'sample_notification' => $notifications->first() ? [
                    'id' => $notifications->first()->id,
                    'type' => $notifications->first()->data['type'] ?? 'unknown',
                    'title' => $notifications->first()->data['title'] ?? 'unknown',
                ] : null
            ];
        } catch (\Exception $e) {
            $results['tests'][] = [
                'name' => 'API Endpoint',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // Test 4: Unread count
        try {
            $unreadCount = $user->unreadNotifications()->count();

            $results['tests'][] = [
                'name' => 'Unread Count',
                'status' => 'success',
                'message' => "Total unread: {$unreadCount}"
            ];
        } catch (\Exception $e) {
            $results['tests'][] = [
                'name' => 'Unread Count',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // Test 5: Check if observers are loaded
        try {
            $observerCount = count(\App\Models\Subscription::getEventDispatcher()->getListeners('created'));

            $results['tests'][] = [
                'name' => 'Observers Loaded',
                'status' => $observerCount > 0 ? 'success' : 'warning',
                'message' => $observerCount > 0
                    ? "Found {$observerCount} listeners on Subscription model"
                    : 'No observers detected on Subscription model'
            ];
        } catch (\Exception $e) {
            $results['tests'][] = [
                'name' => 'Observers Loaded',
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        $results['all_tests_passed'] = !collect($results['tests'])->contains(fn($t) => $t['status'] === 'error');

        return response()->json($results, 200);
    }

    /**
     * Trigger all notification types for testing
     */
    public function triggerAll()
    {
        $user = auth()->user();

        if (!$user || !$user->is_super_admin) {
            return response()->json(['error' => 'Admin only'], 403);
        }

        $results = [];

        // 1. New Subscription
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '📋 New Subscription',
                'Test Business subscribed to Pro Plan',
                'subscription.created',
                ['business' => 'Test Business', 'plan' => 'Pro']
            ));
            $results[] = ['type' => 'subscription.created', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'subscription.created', 'status' => 'error', 'error' => $e->getMessage()];
        }

        // 2. Subscription Upgraded
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '📈 Subscription Upgraded',
                'Test Business upgraded from Basic to Pro',
                'subscription.updated',
                ['old_plan' => 'Basic', 'new_plan' => 'Pro']
            ));
            $results[] = ['type' => 'subscription.updated', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'subscription.updated', 'status' => 'error', 'error' => $e->getMessage()];
        }

        // 3. Subscription Expiring
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '⏰ Subscription Expiring',
                'Test Business subscription expires in 7 days',
                'subscription.expiring',
                ['business' => 'Test Business', 'days' => 7]
            ));
            $results[] = ['type' => 'subscription.expiring', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'subscription.expiring', 'status' => 'error', 'error' => $e->getMessage()];
        }

        // 4. Subscription Expired
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '❌ Subscription Expired',
                'Test Business subscription has expired',
                'subscription.expired',
                ['business' => 'Test Business']
            ));
            $results[] = ['type' => 'subscription.expired', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'subscription.expired', 'status' => 'error', 'error' => $e->getMessage()];
        }

        // 5. New Support Ticket
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '🎫 New Support Ticket',
                'New ticket from John Doe: Payment Issue',
                'support.ticket.created',
                ['user' => 'John Doe', 'subject' => 'Payment Issue']
            ));
            $results[] = ['type' => 'support.ticket.created', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'support.ticket.created', 'status' => 'error', 'error' => $e->getMessage()];
        }

        // 6. Support Message
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '💬 New Support Message',
                'John Doe replied to your support ticket',
                'support.message',
                ['user' => 'John Doe']
            ));
            $results[] = ['type' => 'support.message', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'support.message', 'status' => 'error', 'error' => $e->getMessage()];
        }

        // 7. Duplicate Subscriptions
        try {
            broadcast(new GeneralNotification(
                $user->id,
                '📊 Duplicate Subscriptions',
                'Test Business has 2 active subscriptions',
                'subscription.duplicate',
                ['business' => 'Test Business', 'count' => 2]
            ));
            $results[] = ['type' => 'subscription.duplicate', 'status' => 'triggered'];
        } catch (\Exception $e) {
            $results[] = ['type' => 'subscription.duplicate', 'status' => 'error', 'error' => $e->getMessage()];
        }

        return response()->json([
            'message' => 'All notification types triggered!',
            'triggered' => $results
        ]);
    }
}

