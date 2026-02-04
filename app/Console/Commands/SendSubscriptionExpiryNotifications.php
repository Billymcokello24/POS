<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Events\GeneralNotification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendSubscriptionExpiryNotifications extends Command
{
    protected $signature = 'notifications:subscription-expiry';
    protected $description = 'Send subscription expiry reminders';

    public function handle()
    {
        $this->info('Sending subscription expiry notifications...');

        // Get subscriptions expiring in 7 days
        $expiringIn7Days = Subscription::where('ends_at', Carbon::tomorrow()->addDays(6))
            ->where('status', 'active')
            ->get();

        foreach ($expiringIn7Days as $sub) {
            $this->notifyExpiry($sub, 7);
        }

        // Get subscriptions expiring in 3 days
        $expiringIn3Days = Subscription::where('ends_at', Carbon::tomorrow()->addDays(2))
            ->where('status', 'active')
            ->get();

        foreach ($expiringIn3Days as $sub) {
            $this->notifyExpiry($sub, 3);
        }

        // Get subscriptions expiring tomorrow
        $expiringTomorrow = Subscription::where('ends_at', Carbon::tomorrow())
            ->where('status', 'active')
            ->get();

        foreach ($expiringTomorrow as $sub) {
            $this->notifyExpiry($sub, 1);
        }

        // Get subscriptions expiring today
        $expiringToday = Subscription::where('ends_at', Carbon::today())
            ->where('status', 'active')
            ->get();

        foreach ($expiringToday as $sub) {
            $this->notifyExpiry($sub, 0);
        }

        // Get already expired subscriptions (auto-suspend)
        $expired = Subscription::where('ends_at', '<', Carbon::now())
            ->where('status', 'active')
            ->get();

        foreach ($expired as $sub) {
            // Mark as expired
            $sub->update(['status' => 'expired', 'is_active' => false]);

            // Notify admin
            $admins = User::where('is_super_admin', true)->get();
            foreach ($admins as $admin) {
                broadcast(new GeneralNotification(
                    $admin->id,
                    '❌ Subscription Expired',
                    "Business {$sub->business->name} subscription has expired and been suspended",
                    'subscription.expired',
                    ['business_id' => $sub->business_id]
                ));
            }

            // Notify business
            if ($sub->business->owner) {
                broadcast(new GeneralNotification(
                    $sub->business->owner->id,
                    '❌ Subscription Expired',
                    "Your subscription has expired. Contact support to renew.",
                    'subscription.expired',
                    []
                ));
            }

            $this->info("Suspended subscription {$sub->id} for business {$sub->business_id}");
        }

        $this->info('✅ Subscription expiry notifications sent');
    }

    private function notifyExpiry(Subscription $sub, int $daysRemaining): void
    {
        $message = match ($daysRemaining) {
            0 => 'Your subscription expires TODAY!',
            1 => 'Your subscription expires TOMORROW!',
            default => "Your subscription expires in {$daysRemaining} days"
        };

        $icon = $daysRemaining == 0 ? '🚨' : '⏰';

        // Notify admin
        $admins = User::where('is_super_admin', true)->get();
        foreach ($admins as $admin) {
            broadcast(new GeneralNotification(
                $admin->id,
                "$icon Subscription Expiring Soon",
                "Business {$sub->business->name} subscription expiring in {$daysRemaining} days",
                'subscription.expiring',
                ['business_id' => $sub->business_id, 'days' => $daysRemaining]
            ));
        }

        // Notify business
        if ($sub->business->owner) {
            broadcast(new GeneralNotification(
                $sub->business->owner->id,
                "$icon Subscription Expiring",
                $message,
                'subscription.expiring',
                ['days' => $daysRemaining]
            ));
        }
    }
}

