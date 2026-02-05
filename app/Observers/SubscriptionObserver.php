<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Models\User;
use App\Events\GeneralNotification;
use Illuminate\Support\Facades\Broadcast;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "created" event - NEW SUBSCRIPTION
     */
    public function created(Subscription $subscription): void
    {
        // Only send email notifications if payment is confirmed (has mpesa_receipt)
        $paymentConfirmed = !empty($subscription->mpesa_receipt) || $subscription->status === 'active';

        // Notify all super admins about new subscription
        $admins = User::where('is_super_admin', true)->get();

        foreach ($admins as $admin) {
            // Only send email notification if payment is confirmed
            if ($paymentConfirmed) {
                $admin->notify(new \App\Notifications\SubscriptionCreatedNotification($subscription));
            }

            // Always broadcast to Pusher (real-time notifications)
            broadcast(new GeneralNotification(
                $admin->id,
                '📋 New Subscription',
                "Business {$subscription->business->name} subscribed to {$subscription->plan->name}",
                'subscription.created',
                [
                    'business_id' => $subscription->business_id,
                    'plan_name' => $subscription->plan->name,
                ]
            ));
        }

        // Notify business owner
        if ($subscription->business && $subscription->business->owner) {
            // Only send email notification if payment is confirmed
            if ($paymentConfirmed) {
                $subscription->business->owner->notify(
                    new \App\Notifications\SubscriptionCreatedNotification($subscription)
                );
            }

            // Always broadcast to Pusher
            broadcast(new GeneralNotification(
                $subscription->business->owner->id,
                '✅ Subscription Activated',
                "Your {$subscription->plan->name} subscription is now active!",
                'subscription.created',
                ['plan_name' => $subscription->plan->name]
            ));
        }
    }

    /**
     * Handle the Subscription "updated" event - UPGRADED/DOWNGRADED
     */
    public function updated(Subscription $subscription): void
    {
        if ($subscription->isDirty('plan_id')) {
            $oldPlanId = $subscription->getOriginal('plan_id');
            $oldPlan = \App\Models\Plan::find($oldPlanId);
            $newPlan = $subscription->plan;

            // Determine if upgrade or downgrade
            $isUpgrade = $newPlan->price > $oldPlan->price;
            $action = $isUpgrade ? 'UPGRADED' : 'DOWNGRADED';
            $icon = $isUpgrade ? '📈' : '📉';

            // Send email notification to business owner
            if ($subscription->business) {
                $recipient = $subscription->business->owner ?: $subscription->business->users()->first();

                if ($recipient) {
                    try {
                        if ($isUpgrade) {
                            $recipient->notify(
                                new \App\Notifications\SubscriptionUpgradedNotification($subscription, $oldPlan->name)
                            );
                        } else {
                            $recipient->notify(
                                new \App\Notifications\SubscriptionDowngradedNotification($subscription, $oldPlan->name)
                            );
                        }

                        \Illuminate\Support\Facades\Log::info("Subscription {$action} email sent", [
                            'subscription_id' => $subscription->id,
                            'recipient' => $recipient->email,
                            'old_plan' => $oldPlan->name,
                            'new_plan' => $newPlan->name
                        ]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to send upgrade/downgrade email', [
                            'error' => $e->getMessage(),
                            'subscription_id' => $subscription->id
                        ]);
                    }
                }
            }

            // Notify all super admins
            $admins = User::where('is_super_admin', true)->get();
            foreach ($admins as $admin) {
                broadcast(new GeneralNotification(
                    $admin->id,
                    "$icon Subscription $action",
                    "Business {$subscription->business->name} $action from {$oldPlan->name} to {$newPlan->name}",
                    'subscription.updated',
                    [
                        'business_id' => $subscription->business_id,
                        'old_plan' => $oldPlan->name,
                        'new_plan' => $newPlan->name,
                        'action' => strtolower($action),
                    ]
                ));
            }

            // Notify business owner via broadcast
            if ($subscription->business && $subscription->business->owner) {
                broadcast(new GeneralNotification(
                    $subscription->business->owner->id,
                    "$icon Plan $action",
                    "Your subscription has been $action to {$newPlan->name}",
                    'subscription.updated',
                    ['new_plan' => $newPlan->name]
                ));
            }
        }
    }

    /**
     * Handle the Subscription "saved" event - Expiry, Auto-suspend, Duplicate
     */
    public function saved(Subscription $subscription): void
    {
        // Expiry warning (7, 3, 1 days)
        $daysLeft = $subscription->ends_at ? now()->diffInDays($subscription->ends_at, false) : null;
        if ($daysLeft !== null && in_array($daysLeft, [7, 3, 1])) {
            $admins = User::where('is_super_admin', true)->get();
            foreach ($admins as $admin) {
                broadcast(new GeneralNotification(
                    $admin->id,
                    '⏰ Subscription Expiring',
                    "Business {$subscription->business->name} subscription expires in {$daysLeft} days",
                    'subscription.expiring',
                    ['business_id' => $subscription->business_id, 'days' => $daysLeft]
                ));
            }
            if ($subscription->business && $subscription->business->owner) {
                broadcast(new GeneralNotification(
                    $subscription->business->owner->id,
                    '⏰ Subscription Expiring',
                    "Your subscription expires in {$daysLeft} days",
                    'subscription.expiring',
                    ['days' => $daysLeft]
                ));
            }
        }
        // Expired/auto-suspend
        if ($subscription->ends_at && now()->greaterThan($subscription->ends_at) && $subscription->is_active) {
            $subscription->update(['is_active' => false, 'status' => 'expired']);
            $admins = User::where('is_super_admin', true)->get();
            foreach ($admins as $admin) {
                broadcast(new GeneralNotification(
                    $admin->id,
                    '❌ Subscription Expired',
                    "Business {$subscription->business->name} subscription expired and auto-suspended",
                    'subscription.expired',
                    ['business_id' => $subscription->business_id]
                ));
            }
            if ($subscription->business && $subscription->business->owner) {
                broadcast(new GeneralNotification(
                    $subscription->business->owner->id,
                    '❌ Subscription Expired',
                    "Your subscription has expired and your account is suspended.",
                    'subscription.expired',
                    []
                ));
            }
        }
        // Duplicate subscriptions
        $activeCount = $subscription->business->subscriptions()->where('status', 'active')->count();
        if ($activeCount > 1) {
            $admins = User::where('is_super_admin', true)->get();
            foreach ($admins as $admin) {
                broadcast(new GeneralNotification(
                    $admin->id,
                    '📊 Duplicate Subscriptions',
                    "Business {$subscription->business->name} has {$activeCount} active subscriptions",
                    'subscription.duplicate',
                    ['business_id' => $subscription->business_id, 'count' => $activeCount]
                ));
            }
        }
    }
}

