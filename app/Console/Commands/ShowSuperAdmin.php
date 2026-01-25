<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ShowSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:show-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = \App\Models\User::where('email', 'admin@pos.com')->first();

        if ($user) {
            $this->info('Super Admin User Details:');
            $this->line('ID: ' . $user->id);
            $this->line('Name: ' . $user->name);
            $this->line('Email: ' . $user->email);
            $this->line('Role: ' . $user->role);
            $this->line('Is Super Admin: ' . ($user->is_super_admin ? 'Yes' : 'No'));
            $this->line('Is Active: ' . ($user->is_active ? 'Yes' : 'No'));
            $this->line('Email Verified At: ' . $user->email_verified_at);
            $this->line('Created At: ' . $user->created_at);
        } else {
            $this->error('Super Admin user not found.');
        }
    }
}
