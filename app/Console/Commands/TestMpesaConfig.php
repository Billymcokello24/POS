<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestMpesaConfig extends Command
{
    protected $signature = 'mpesa:test-config';
    protected $description = 'Test M-Pesa configuration';

    public function handle()
    {
        $this->info('Testing M-Pesa Configuration...');
        $this->newLine();

        $consumerKey = config('mpesa.consumer_key');
        $consumerSecret = config('mpesa.consumer_secret');
        $shortcode = config('mpesa.shortcode');
        $passkey = config('mpesa.passkey');
        $environment = config('mpesa.environment');
        $callbackUrl = config('mpesa.callback_url');

        $this->table(
            ['Setting', 'Status', 'Value'],
            [
                ['Environment', $environment ? '✓' : '✗', $environment ?: 'NOT SET'],
                ['Consumer Key', $consumerKey ? '✓' : '✗', $consumerKey ? substr($consumerKey, 0, 10) . '...' : 'NOT SET'],
                ['Consumer Secret', $consumerSecret ? '✓' : '✗', $consumerSecret ? substr($consumerSecret, 0, 10) . '...' : 'NOT SET'],
                ['Shortcode', $shortcode ? '✓' : '✗', $shortcode ?: 'NOT SET'],
                ['Passkey', $passkey ? '✓' : '✗', $passkey ? substr($passkey, 0, 15) . '...' : 'NOT SET'],
                ['Callback URL', $callbackUrl ? '✓' : '✗', $callbackUrl ?: 'NOT SET'],
            ]
        );

        $this->newLine();

        if ($consumerKey && $consumerSecret && $shortcode && $passkey) {
            $this->info('✓ All M-Pesa credentials are configured!');
            $this->info('✓ Ready to process payments.');
            return 0;
        } else {
            $this->error('✗ Some M-Pesa credentials are missing!');
            $this->warn('Please add the missing credentials to your .env file:');
            $this->newLine();

            if (!$consumerKey) $this->line('  MPESA_CONSUMER_KEY=your_key_here');
            if (!$consumerSecret) $this->line('  MPESA_CONSUMER_SECRET=your_secret_here');
            if (!$shortcode) $this->line('  MPESA_SHORTCODE=your_shortcode_here');
            if (!$passkey) $this->line('  MPESA_PASSKEY=your_passkey_here');

            $this->newLine();
            $this->line('Then run: php artisan config:cache');
            return 1;
        }
    }
}

