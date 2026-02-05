<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\TestMpesaConfig::class,
        \App\Console\Commands\MonitorSubscriptions::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Run subscription monitor every minute to enforce expiry
        $schedule->command('subscriptions:monitor')->everyMinute();

        // Send subscription expiry notifications every day at 8 AM
        $schedule->command('notifications:subscription-expiry')->dailyAt('08:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
