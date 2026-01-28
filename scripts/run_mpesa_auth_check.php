<?php
// Robust MPESA auth checker script
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

try {
    $cms = app(\App\Services\CmsService::class);
    $mp = $cms->getMpesaConfig();

    if (! $mp) {
        echo json_encode(['ok' => false, 'message' => 'No MPESA config found in CMS']);
        exit(0);
    }

    // Masked summary
    $summary = [
        'present' => true,
        'consumer_key_present' => !empty($mp['consumer_key']),
        'consumer_secret_present' => !empty($mp['consumer_secret']),
        'shortcode_present' => !empty($mp['shortcode']),
        'passkey_present' => !empty($mp['passkey']),
        'environment' => $mp['environment'] ?? config('mpesa.environment'),
        'callback_url' => $mp['callback_url'] ?? config('mpesa.callback_url'),
        'simulate' => isset($mp['simulate']) ? (bool)$mp['simulate'] : (bool)config('mpesa.simulate'),
    ];

    echo "CMS MPESA SUMMARY:\n";
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

    $env = $mp['environment'] ?? config('mpesa.environment', 'production');
    $urls = config("mpesa.urls.{$env}");
    $authUrl = config('mpesa.auth_url') ?: ($urls['oauth'] ?? null);

    if (! $authUrl) {
        echo json_encode(['ok' => false, 'message' => 'Auth URL not configured', 'env' => $env]);
        exit(0);
    }

    echo "Attempting OAuth at: $authUrl\n";

    try {
        $response = Http::withBasicAuth($mp['consumer_key'] ?? '', $mp['consumer_secret'] ?? '')
            ->timeout(15)
            ->get($authUrl . (strpos($authUrl, '?') !== false ? '&' : '?') . 'grant_type=client_credentials');

        $status = method_exists($response, 'status') ? $response->status() : null;
        $body = null;
        try { $body = $response->body(); } catch (\Throwable $_) { $body = null; }

        echo json_encode(['ok' => true, 'status' => $status, 'body_snippet' => $body ? substr($body,0,200) : null], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'exception' => $e->getMessage()]);
        exit(0);
    }

} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'exception' => $e->getMessage()]);
    exit(0);
}

