<?php
// scripts/simulate_mpesa_callback_for_pending.php
// Find a pending subscription and simulate an STK callback payload processed by ProcessMpesaCallback

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ProcessMpesaCallback;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

$sub = Subscription::whereIn('status', ['initiated','pending','pending_verification'])->orderBy('created_at','desc')->first();
if (! $sub) {
    echo "No pending subscription found to simulate callback.\n";
    exit(1);
}

$subId = $sub->id;
$checkoutId = 'SIM-CHECKOUT-' . strtoupper(uniqid());
$receipt = 'SIM-RECEIPT-' . strtoupper(uniqid());
$amount = $sub->amount ?: 1.00;
$phone = $sub->payment_details['phone'] ?? '254700000000';

$payload = [
    'Body' => [
        'stkCallback' => [
            'MerchantRequestID' => 'SIMMER-' . strtoupper(uniqid()),
            'CheckoutRequestID' => $checkoutId,
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'CallbackMetadata' => [
                'Item' => [
                    ['Name' => 'Amount', 'Value' => (string)$amount],
                    ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                    ['Name' => 'TransactionDate', 'Value' => (int)date('YmdHis')],
                    ['Name' => 'PhoneNumber', 'Value' => (int)$phone],
                    ['Name' => 'AccountReference', 'Value' => 'SUB-' . $subId],
                ]
            ]
        ]
    ]
];

echo "Simulating STK callback for subscription id={$subId}\n";
echo "Before: status={$sub->status}, transaction_id={$sub->transaction_id}\n";

$job = new ProcessMpesaCallback($payload);
try {
    $job->handle();
    echo "Callback processed.\n";
} catch (\Throwable $e) {
    echo "Callback processing threw: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

$subFresh = Subscription::find($subId);

echo "After: status={$subFresh->status}, transaction_id={$subFresh->transaction_id}\n";
$pd = $subFresh->payment_details ?? [];
if (is_array($pd)) {
    echo "payment_details mpesa_receipt=" . ($pd['mpesa_receipt'] ?? 'NULL') . "\n";
    echo "payment_details auto_reconcile=" . (($pd['auto_reconcile'] ?? false) ? 'true' : 'false') . "\n";
}

// print subscription json
echo json_encode($subFresh->toArray(), JSON_PRETTY_PRINT) . "\n";

