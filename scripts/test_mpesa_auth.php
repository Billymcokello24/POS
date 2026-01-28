<?php
// Boot Laravel app and test MPESA auth using platform CMS credentials
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $cms = app(\App\Services\CmsService::class);
    $mp = $cms->getMpesaConfig();
    if (! $mp) {
        echo json_encode(['ok' => false, 'message' => 'No MPESA config found in CMS']);
        exit(0);
    }

    $env = $mp['environment'] ?? config('mpesa.environment', 'production');
    $urls = config("mpesa.urls.{$env}");
    $authUrl = config('mpesa.auth_url') ?: ($urls['oauth'] ?? null);
    if (! $authUrl) {
        echo json_encode(['ok' => false, 'message' => 'Auth URL not configured']);
        exit(0);
    }

    $response = Illuminate\Support\Facades\Http::withBasicAuth($mp['consumer_key'], $mp['consumer_secret'])
        ->timeout(20)
        ->get($authUrl . (strpos($authUrl, '?') !== false ? '&' : '?') . 'grant_type=client_credentials');

    $status = method_exists($response, 'status') ? $response->status() : null;
    $body = null;
    try { $body = $response->body(); } catch (\Throwable $_) { $body = null; }

    echo json_encode(['ok' => true, 'auth_url' => $authUrl, 'status' => $status, 'body_snippet' => $body ? substr($body,0,1000) : null], JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo json_encode(['ok' => false, 'exception' => $e->getMessage()]);
}

