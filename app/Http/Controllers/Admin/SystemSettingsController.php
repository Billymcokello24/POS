<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    public function index()
    {
        // 1. Get MPESA from CMS 'welcome' page
        $cmsPage = Page::where('key', 'welcome')->first();
        $cmsContent = $cmsPage?->content ?? [];
        if (is_string($cmsContent)) {
            $cmsContent = json_decode($cmsContent, true) ?? [];
        }
        $mpesaStored = $cmsContent['mpesa'] ?? [];

        // Decrypt MPESA
        foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $key) {
            if (isset($mpesaStored[$key])) {
                try { $mpesaStored[$key] = Crypt::decryptString($mpesaStored[$key]); } catch (\Exception $e) {}
            }
        }

        // 2. Get SMTP from 'system_settings' or .env fallback
        $settingsPage = Page::where('key', 'system_settings')->first();
        $settingsContent = $settingsPage?->content ?? [];
        if (is_string($settingsContent)) {
            $settingsContent = json_decode($settingsContent, true) ?? [];
        }
        $smtpStored = $settingsContent['mail'] ?? [];

        $settings = [
            'mpesa' => array_merge([
                'consumer_key' => null,
                'consumer_secret' => null,
                'shortcode' => null,
                'passkey' => null,
                'environment' => 'sandbox',
                'callback_url' => null,
                'result_url' => null,
                'head_office_shortcode' => null,
                'head_office_passkey' => null,
                'initiator_name' => null,
                'initiator_password' => null,
                'security_credential' => null,
                'simulate' => false,
            ], $mpesaStored),
            'mail' => array_merge([
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ], $smtpStored),
        ];

        // Decrypt SMTP password if it was stored manually in DB
        if (isset($settings['mail']['password']) && !empty($settings['mail']['password'])) {
            try { $settings['mail']['password'] = Crypt::decryptString($settings['mail']['password']); } catch (\Exception $e) {}
        }

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mpesa.consumer_key' => 'nullable|string',
            'mpesa.consumer_secret' => 'nullable|string',
            'mpesa.shortcode' => 'nullable|string',
            'mpesa.passkey' => 'nullable|string',
            'mpesa.environment' => 'nullable|string|in:sandbox,production',
            'mpesa.callback_url' => 'nullable|url',
            'mpesa.result_url' => 'nullable|url',
            'mpesa.head_office_shortcode' => 'nullable|string',
            'mpesa.head_office_passkey' => 'nullable|string',
            'mpesa.initiator_name' => 'nullable|string',
            'mpesa.initiator_password' => 'nullable|string',
            'mpesa.security_credential' => 'nullable|string',
            'mpesa.simulate' => 'nullable|boolean',

            'mail.host' => 'nullable|string',
            'mail.port' => 'nullable|string',
            'mail.username' => 'nullable|string',
            'mail.password' => 'nullable|string',
            'mail.encryption' => 'nullable|string',
            'mail.from_address' => 'nullable|email',
            'mail.from_name' => 'nullable|string',
        ]);

        // 1. Sync MPESA back to 'welcome' page
        $cmsPage = Page::firstOrCreate(['key' => 'welcome']);
        $cmsContent = $cmsPage->content ?? [];
        if (is_string($cmsContent)) {
            $cmsContent = json_decode($cmsContent, true) ?? [];
        }

        $incomingMpesa = $request->input('mpesa', []);
        
        // Encrypt MPESA sensitive fields
        foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $skey) {
            if (!empty($incomingMpesa[$skey])) {
                $incomingMpesa[$skey] = Crypt::encryptString($incomingMpesa[$skey]);
            }
        }

        $cmsContent['mpesa'] = array_merge($cmsContent['mpesa'] ?? [], $incomingMpesa);
        $cmsPage->update(['content' => $cmsContent]);

        // 2. Save SMTP to 'system_settings'
        $settingsPage = Page::firstOrCreate(['key' => 'system_settings']);
        $incomingMail = $request->input('mail', []);

        // Encrypt SMTP password
        if (!empty($incomingMail['password'])) {
            $incomingMail['password'] = Crypt::encryptString($incomingMail['password']);
        }

        $settingsPage->update(['content' => ['mail' => $incomingMail]]);

        \App\Models\AuditLog::log(
            'system.settings_update',
            "Super Admin updated global system settings (MPESA & SMTP).",
            $request->except(['mpesa.consumer_secret', 'mpesa.passkey', 'mpesa.head_office_passkey', 'mpesa.initiator_password', 'mpesa.security_credential', 'mail.password'])
        );

        return back()->with('success', 'System settings updated and synced with CMS.');
    }
}
