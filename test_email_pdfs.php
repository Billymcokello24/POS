<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PdfService;
use App\Models\Subscription;

echo "🧪 Testing PDF Service...\n\n";

try {
    $pdfService = app(PdfService::class);

    echo "1️⃣ Testing with existing subscription...\n";
    $subscription = Subscription::with('business', 'plan')->where('status', 'active')->first();

    if ($subscription) {
        echo "   Found subscription ID: {$subscription->id}\n";
        echo "   Business: {$subscription->business->name}\n";
        echo "   Plan: {$subscription->plan_name}\n\n";

        echo "📄 Testing Receipt PDF generation...\n";
        $receiptPdf = $pdfService->generateReceipt($subscription);
        $receiptSize = strlen($receiptPdf->output());
        echo "✅ Receipt PDF generated successfully! Size: " . round($receiptSize / 1024, 2) . " KB\n\n";

        echo "📄 Testing Invoice PDF generation...\n";
        $invoicePdf = $pdfService->generateInvoice($subscription, 7);
        $invoiceSize = strlen($invoicePdf->output());
        echo "✅ Invoice PDF generated successfully! Size: " . round($invoiceSize / 1024, 2) . " KB\n\n";

        echo "📄 Testing Plans Brochure PDF generation...\n";
        $plansPdf = $pdfService->generatePlansBrochure();
        $plansSize = strlen($plansPdf->output());
        echo "✅ Plans Brochure PDF generated successfully! Size: " . round($plansSize / 1024, 2) . " KB\n\n";

        echo "🎉 All PDF generation tests passed!\n\n";
        echo "💾 Saving test PDFs to /tmp/...\n";
        file_put_contents('/tmp/test_receipt.pdf', $receiptPdf->output());
        file_put_contents('/tmp/test_invoice.pdf', $invoicePdf->output());
        file_put_contents('/tmp/test_plans.pdf', $plansPdf->output());
        echo "✅ Test PDFs saved:\n";
        echo "   - /tmp/test_receipt.pdf\n";
        echo "   - /tmp/test_invoice.pdf\n";
        echo "   - /tmp/test_plans.pdf\n\n";

    } else {
        echo "❌ No active subscription found for testing\n";
        echo "💡 Create a subscription first or use existing data\n";
    }

    echo "\n2️⃣ Testing notification email...\n";
    $user = \App\Models\User::where('is_super_admin', true)->first();
    if ($user && $subscription) {
        echo "   Sending test email to: {$user->email}\n";
        $user->notify(new \App\Notifications\SubscriptionCreatedNotification($subscription));
        echo "✅ Email notification sent!\n";
        echo "📧 Check your inbox at: {$user->email}\n";
    } else {
        echo "❌ No admin user found for email testing\n";
    }

    echo "\n✨ All tests completed successfully!\n";

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n📚 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
