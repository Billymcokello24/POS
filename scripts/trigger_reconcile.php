<?php
// scripts/trigger_reconcile.php
// Boot Laravel and call Admin\SubscriptionController::reconcile as a superadmin, print JSON result.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Models\User;

// Find a superadmin user
$admin = User::where('is_super_admin', true)->first();
if (! $admin) {
    echo json_encode(['success' => false, 'message' => 'No superadmin user found']);
    exit(1);
}

// Create a fake request and set user resolver
$req = Request::create('/admin/subscriptions/reconcile', 'POST');
$req->setUserResolver(function () use ($admin) { return $admin; });

$ctrl = new SubscriptionController();
$res = $ctrl->reconcile($req);

// $res may be a JsonResponse
if (method_exists($res, 'getContent')) {
    echo $res->getContent();
} else {
    echo json_encode(['success' => true, 'message' => 'Reconcile executed, response type: ' . gettype($res)]);
}

echo PHP_EOL;

