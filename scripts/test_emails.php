<?php
/**
 * Email Testing Script
 *
 * Run with: php scripts/test_emails.php
 *
 * This script tests that all email notifications are working correctly.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Support\Facades\Mail;

echo "📧 Email Configuration Test Suite\n";
echo "==================================\n\n";

// Test 1: Check SMTP Configuration
echo "1️⃣  Testing SMTP Configuration...\n";
$mailer = config('mail.default');
$host = config('mail.mailers.smtp.host');
$port = config('mail.mailers.smtp.port');
$from = config('mail.from.address');

echo "   Mailer: {$mailer}\n";
echo "   Host: {$host}:{$port}\n";
echo "   From: {$from}\n";

if ($mailer === 'log') {
    echo "   ⚠️  WARNING: Using 'log' mailer. Emails will be logged, not sent.\n";
    echo "   Update MAIL_MAILER in .env to use SMTP.\n\n";
} else {
    echo "   ✅ SMTP configured\n\n";
}

// Test 2: Send Test Email
echo "2️⃣  Sending Test Email...\n";
$testEmail = 'test@example.com';

echo "   Enter email address to test (or press Enter for {$testEmail}): ";
$input = trim(fgets(STDIN));
if (!empty($input)) {
    $testEmail = $input;
}

try {
    Mail::raw('This is a test email from POS System. If you receive this, your email configuration is working!', function ($message) use ($testEmail) {
        $message->to($testEmail)
                ->subject('POS System - Email Test');
    });
    echo "   ✅ Test email queued/sent to {$testEmail}\n\n";
} catch (\Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n\n";
    exit(1);
}

// Test 3: Test Notification Classes
echo "3️⃣  Testing Notification Classes...\n";

$notificationClasses = [
    'ImpersonationStarted',
    'ImpersonationEndedNotification',
    'SubscriptionActivated',
    'SubscriptionRejected',
    'SubscriptionExpiring',
    'SubscriptionExpired',
    'BusinessRegistered',
    'BusinessSuspended',
    'UserAccountCreated',
    'NewSupportTicket',
];

foreach ($notificationClasses as $class) {
    $fullClass = "App\\Notifications\\{$class}";
    if (class_exists($fullClass)) {
        echo "   ✅ {$class}\n";
    } else {
        echo "   ❌ {$class} NOT FOUND\n";
    }
}

echo "\n";

// Test 4: Check Queue Configuration
echo "4️⃣  Checking Queue Configuration...\n";
$queueDriver = config('queue.default');
echo "   Queue Driver: {$queueDriver}\n";

if ($queueDriver === 'sync') {
    echo "   ⚠️  WARNING: Using 'sync' queue. Emails sent immediately (blocking).\n";
    echo "   Consider using 'database' or 'redis' for production.\n\n";
} else {
    echo "   ✅ Async queue configured\n";
    echo "   💡 Remember to run: php artisan queue:work\n\n";
}

// Test 5: Check Pending Jobs
echo "5️⃣  Checking Pending Jobs...\n";
try {
    $pendingJobs = \DB::table('jobs')->count();
    $failedJobs = \DB::table('failed_jobs')->count();

    echo "   Pending Jobs: {$pendingJobs}\n";
    echo "   Failed Jobs: {$failedJobs}\n";

    if ($failedJobs > 0) {
        echo "   ⚠️  You have failed jobs. Run: php artisan queue:failed\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   ⚠️  Could not check job tables: {$e->getMessage()}\n\n";
}

// Test 6: Live Notification Test
echo "6️⃣  Live Notification Test (Optional)\n";
echo "   Would you like to send a real test notification? (y/N): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) === 'y') {
    echo "   Finding a test user...\n";

    try {
        $user = User::first();

        if (!$user) {
            echo "   ❌ No users found in database\n";
        } else {
            echo "   Found user: {$user->name} ({$user->email})\n";
            echo "   Sending UserAccountCreated notification...\n";

            $user->notify(new \App\Notifications\UserAccountCreated('TestPassword123!'));

            echo "   ✅ Notification sent!\n";
            echo "   Check email: {$user->email}\n";

            if ($queueDriver !== 'sync') {
                echo "   💡 Run 'php artisan queue:work --once' to process\n";
            }
        }
    } catch (\Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n";
    }
}

echo "\n";
echo "==================================\n";
echo "✅ Email Test Complete!\n\n";

echo "Next Steps:\n";
echo "1. If using queue, run: php artisan queue:work\n";
echo "2. Check logs: tail -f storage/logs/laravel.log\n";
echo "3. For production, configure SendGrid/Mailgun/SES\n";
echo "4. See EMAIL_NOTIFICATIONS_COMPLETE_GUIDE.md for details\n";
