<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestMailSend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $user = $email ? \App\Models\User::where('email', $email)->first() : \App\Models\User::first();

        if (!$user) {
            $this->error("User not found.");
            return;
        }

        $business = $user->currentBusiness ?? \App\Models\Business::first();

        if (!$business) {
            $this->error("Business not found for user.");
            return;
        }

        $this->info("Sending test notification to {$user->email}...");

        try {
            $user->notify(new \App\Notifications\BusinessRegistered($business));
            $this->info("Notification sent successfully!");
        } catch (\Throwable $e) {
            $this->error("Failed to send notification: " . $e->getMessage());
        }
    }
}
