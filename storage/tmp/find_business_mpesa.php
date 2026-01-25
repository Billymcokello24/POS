<?php
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$target = 'https://4da20dec2d17.ngrok-free.app';
$found = null;

foreach (App\Models\Business::all() as $b) {
    $mp = $b->mpesa();
    if (!$mp) continue;
    $cb = $mp['callback_url'] ?? '';
    if (strpos($cb, $target) !== false) { $found = $b; break; }
}

if (!$found) {
    echo "NO BUSINESS FOUND matching the callback URL: $target\n\n";
    echo "Businesses with MPESA callback URLs (summary):\n";
    foreach (App\Models\Business::all() as $b) {
        $mp = $b->mpesa();
        if ($mp && !empty($mp['callback_url'])) {
            echo "- {$b->id} {$b->name}: " . ($mp['callback_url'] ?? '(none)') . "\n";
        }
    }
    exit(0);
}

$mp = $found->mpesa();
$display = [
    'business_id' => $found->id,
    'business_name' => $found->name,
    'consumer_key' => $mp['consumer_key'] ?? null,
    'consumer_secret_mask' => isset($mp['consumer_secret']) ? substr($mp['consumer_secret'],0,4) . '...' . substr($mp['consumer_secret'],-4) : null,
    'shortcode' => $mp['shortcode'] ?? null,
    'passkey_mask' => isset($mp['passkey']) ? substr($mp['passkey'],0,4) . '...' : null,
    'head_office_shortcode' => $mp['head_office_shortcode'] ?? null,
    'head_office_passkey_present' => !empty($mp['head_office_passkey']),
    'environment' => $mp['environment'] ?? null,
    'callback_url' => $mp['callback_url'] ?? null,
    'simulate' => !empty($mp['simulate']) ? true : false,
];

print_r($display);

