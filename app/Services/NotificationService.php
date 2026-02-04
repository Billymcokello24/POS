<?php

namespace App\Services;

use App\Events\GeneralNotification;
use App\Models\User;
use App\Notifications\SubscriptionCreatedNotification;
use App\Notifications\SubscriptionExpiryReminderNotification;
use App\Notifications\DuplicateSubscriptionNotification;
use App\Notifications\ImpersonationNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Notify super admins about new subscription
     */
    public function notifyNewSubscription($subscription)
    {
        $superAdmins = User::where('is_super_admin', true)->get();

        foreach ($superAdmins as $admin) {
            // Broadcast real-time notification
            broadcast(new GeneralNotification(
                $admin->id,
                'New Subscription',
                "Business {$subscription->business->name} has subscribed to {$subscription->plan->name}",
                'subscription.created',
                [
                    'business_id' => $subscription->business_id,
                    'subscription_id' => $subscription->id,
                ]
            ));

            // Save database notification
            $admin->notify(new SubscriptionCreatedNotification($subscription));
        }

        // Notify the business owner
        $business = $subscription->business;
        if ($business->owner) {
            broadcast(new GeneralNotification(
                $business->owner->id,
                'Subscription Activated',
                "Your {$subscription->plan->name} subscription is now active!",
                'subscription.created',
                ['subscription_id' => $subscription->id]
            ));

            $business->owner->notify(new SubscriptionCreatedNotification($subscription));
        }
    }

    /**
     * Notify about subscription expiry
     */
    public function notifySubscriptionExpiry($subscription, int $daysUntilExpiry)
    {
        $superAdmins = User::where('is_super_admin', true)->get();

        foreach ($superAdmins as $admin) {
            broadcast(new GeneralNotification(
                $admin->id,
                'Subscription Expiring Soon',
                "Business {$subscription->business->name}'s subscription expires in {$daysUntilExpiry} days",
                'subscription.expiring',
                [
                    'business_id' => $subscription->business_id,
                    'subscription_id' => $subscription->id,
                    'days_until_expiry' => $daysUntilExpiry,
                ]
            ));

            $admin->notify(new SubscriptionExpiryReminderNotification($subscription, $daysUntilExpiry));
        }

        // Notify the business owner
        if ($subscription->business->owner) {
            broadcast(new GeneralNotification(
                $subscription->business->owner->id,
                'Subscription Expiring Soon',
                match($daysUntilExpiry) {
                    0 => 'Your subscription expires today!',
                    1 => 'Your subscription expires tomorrow!',
                    default => "Your subscription expires in {$daysUntilExpiry} days"
                },
                'subscription.expiring',
                ['subscription_id' => $subscription->id]
            ));

            $subscription->business->owner->notify(
                new SubscriptionExpiryReminderNotification($subscription, $daysUntilExpiry)
            );
        }
    }

    /**
     * Notify about duplicate subscriptions
     */
    public function notifyDuplicateSubscription($subscription)
    {
        $activeCount = $subscription->business->subscriptions()
            ->where('status', 'active')
            ->count();

        $superAdmins = User::where('is_super_admin', true)->get();

        foreach ($superAdmins as $admin) {
            broadcast(new GeneralNotification(
                $admin->id,
                'Multiple Subscriptions Alert',
                "Business {$subscription->business->name} now has {$activeCount} active subscriptions",
                'subscription.duplicate',
                [
                    'business_id' => $subscription->business_id,
                    'active_count' => $activeCount,
                ]
            ));

            $admin->notify(new DuplicateSubscriptionNotification($subscription, $activeCount - 1));
        }
    }

    /**
     * Notify about impersonation
     */
    public function notifyImpersonationStarted(User $businessUser, User $admin)
    {
        // Notify the business user
        broadcast(new GeneralNotification(
            $businessUser->id,
            'Account Access Alert',
            "Admin {$admin->name} has started impersonating your account",
            'impersonation.started',
            ['admin_id' => $admin->id]
        ));

        $businessUser->notify(new ImpersonationNotification($admin->name, 'started'));

        // Notify super admins about the impersonation action
        $superAdmins = User::where('is_super_admin', true)
            ->where('id', '!=', $admin->id)
            ->get();

        foreach ($superAdmins as $superAdmin) {
            broadcast(new GeneralNotification(
                $superAdmin->id,
                'Impersonation Alert',
                "Admin {$admin->name} is impersonating {$businessUser->name}",
                'impersonation.audit',
                ['impersonated_user_id' => $businessUser->id]
            ));
        }
    }

    /**
     * Notify about impersonation ended
     */
    public function notifyImpersonationEnded(User $businessUser, User $admin)
    {
        broadcast(new GeneralNotification(
            $businessUser->id,
            'Account Access Ended',
            "Impersonation session by {$admin->name} has ended",
            'impersonation.ended',
            ['admin_id' => $admin->id]
        ));

        $businessUser->notify(new ImpersonationNotification($admin->name, 'ended'));
    }

    /**
     * Send custom notification to users
     */
    public function sendCustomNotification(array $userIds, string $title, string $message, string $type = 'info', array $data = [])
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            broadcast(new GeneralNotification(
                $user->id,
                $title,
                $message,
                $type,
                $data
            ));
        }
    }
}

