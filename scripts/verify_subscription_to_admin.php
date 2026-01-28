<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

try {
    // Find or create a business user
    $businessUser = \App\Models\User::first();
    if (! $businessUser) {
        echo json_encode(['ok'=>false,'error'=>'no_user']); exit(1);
    }
    auth()->loginUsingId($businessUser->id);

    $business = $businessUser->currentBusiness ?? \App\Models\Business::first();
    if (! $business) { echo json_encode(['ok'=>false,'error'=>'no_business']); exit(1); }

    $plan = \App\Models\Plan::first();
    if (! $plan) { echo json_encode(['ok'=>false,'error'=>'no_plan']); exit(1); }

    // Create a TILL payment via Business\SubscriptionController::initiatePayment
    $txn = 'TILL-' . strtoupper(uniqid());
    $post = [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'payment_method' => 'till',
        'transaction_code' => $txn,
    ];

    $req = Request::create('/subscription', 'POST', $post, [], [], ['HTTP_ACCEPT' => 'application/json']);
    $ctrl = app(\App\Http\Controllers\Business\SubscriptionController::class);
    $res = $ctrl->initiatePayment($req);

    // Collect created subscription and subscription_payment
    $sub = \App\Models\Subscription::where('business_id', $business->id)->where('plan_id', $plan->id)->where('transaction_id', $txn)->orWhere('status','pending')->latest()->first();

    // Find matching subscription payments by transaction_code in metadata or by business_id + amount recently
    $sp = \App\Models\SubscriptionPayment::where('business_id', $business->id)
        ->where(function($q) use ($txn) {
            $q->where('checkout_request_id', $txn)
              ->orWhere('mpesa_receipt', $txn)
              ->orWhereRaw("JSON_EXTRACT(metadata, '$$.transaction_code') = ?", [$txn]);
        })->latest()->first();

    // If none found, try by recent amount/business_id
    if (! $sp) {
        $sp = \App\Models\SubscriptionPayment::where('business_id', $business->id)->orderBy('id','desc')->first();
    }

    // Now get admin ledger via controller index as super admin
    // Ensure there is a super admin user
    $admin = \App\Models\User::where('is_super_admin', true)->first();
    if (! $admin) {
        // make a temporary super admin user
        $admin = \App\Models\User::create([
            'name' => 'temp-admin',
            'email' => 'temp-admin+' . time() . '@example.test',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);
    }

    // Create request and set user
    $adminReq = Request::create('/admin/subscriptions', 'GET');
    $adminReq->setUserResolver(function () use ($admin) { return $admin; });

    $adminCtrl = app(\App\Http\Controllers\Admin\SubscriptionController::class);
    $adminResp = $adminCtrl->index($adminReq);

    // Try to extract data from Inertia response
    $adminData = null;
    if (is_object($adminResp) && method_exists($adminResp, 'getData')) {
        try { $adminData = $adminResp->getData(); } catch (\Throwable $_) { $adminData = null; }
    }
    if (!$adminData && method_exists($adminResp, 'getContent')) {
        try { $adminData = json_decode($adminResp->getContent(), true); } catch (\Throwable $_) { $adminData = null; }
    }

    $out = [
        'ok' => true,
        'created_subscription' => $sub ? $sub->toArray() : null,
        'created_subscription_payment' => $sp ? $sp->toArray() : null,
        'admin_response_sample' => is_array($adminData) ? array_slice($adminData,0,10) : (is_object($adminData) ? 'object' : null),
    ];

    echo json_encode($out, JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    echo json_encode(['ok'=>false,'exception'=>$e->getMessage(),'trace'=>substr($e->getTraceAsString(),0,1000)]);
}

