<?php

namespace App\Services\Http\Controllers\Admin;

use App\Services\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class CmsController extends Controller
{
    public function index()
    {
        // route is protected by super_admin middleware, explicit authorize not required
        $page = Page::where('key', 'welcome')->first();

        $defaults = [
            'hero_title' => '',
            'hero_subtitle' => '',
            'hero_bg_image' => '',
            'announcement_text' => '',
            'about_title' => '',
            'about_content' => '',
            'seo_site_title' => '',
            'seo_meta_description' => '',
            'media_logo_url' => '',
            'media_favicon_url' => '',
            // MPESA platform defaults (used for subscription/payment fallback)
            'mpesa' => [
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
            ],
        ];

        // Normalize stored content: if DB has a JSON string (older writes) decode it, else ensure it's an array
        $stored = $page?->content ?? [];
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($stored)) {
            $stored = (array) $stored;
        }

        $content = array_merge($defaults, $stored);

        // If mpesa sensitive values were stored encrypted, attempt to decrypt so the admin UI shows plaintext
        if (isset($content['mpesa']) && is_array($content['mpesa'])) {
            foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $key) {
                if (array_key_exists($key, $content['mpesa']) && $content['mpesa'][$key] !== null) {
                    try {
                        $content['mpesa'][$key] = Crypt::decryptString($content['mpesa'][$key]);
                    } catch (\Exception $e) {
                        // leave as-is if cannot decrypt (may already be plaintext)
                    }
                }
            }
            // ensure simulate is boolean
            $content['mpesa']['simulate'] = isset($content['mpesa']['simulate']) ? (bool) $content['mpesa']['simulate'] : false;
        }

        return Inertia::render('Admin/Cms/Index', [
            'cms' => $content,
        ]);
    }

    public function update(Request $request)
    {
        // route is protected by super_admin middleware, explicit authorize not required

        $validated = $request->validate([
            // Hero
            'hero_title' => 'nullable|string',
            'hero_subtitle' => 'nullable|string',
            'hero_bg_image' => 'nullable|url',
            'announcement_text' => 'nullable|string',
            // About section
            'about_title' => 'nullable|string',
            'about_content' => 'nullable|string',
            // SEO
            'seo_site_title' => 'nullable|string',
            'seo_meta_description' => 'nullable|string',
            // Media
            'media_logo_url' => 'nullable|url',
            'media_favicon_url' => 'nullable|url',
            // MPESA platform defaults
            'mpesa.consumer_key' => 'nullable|string|max:255',
            'mpesa.consumer_secret' => 'nullable|string|max:2048',
            'mpesa.shortcode' => 'nullable|string|max:50',
            'mpesa.passkey' => 'nullable|string|max:2048',
            'mpesa.environment' => 'nullable|in:sandbox,production,live',
            'mpesa.callback_url' => 'nullable|url',
            'mpesa.result_url' => 'nullable|url',
            'mpesa.head_office_shortcode' => 'nullable|string|max:50',
            'mpesa.head_office_passkey' => 'nullable|string|max:2048',
            'mpesa.initiator_name' => 'nullable|string|max:255',
            'mpesa.initiator_password' => 'nullable|string|max:2048',
            'mpesa.security_credential' => 'nullable|string|max:4096',
            'mpesa.simulate' => 'nullable|boolean',
        ]);

        $page = Page::firstOrCreate(['key' => 'welcome']);
        // Normalize existing content
        $existing = $page->content ?? [];
        if (is_string($existing)) {
            $decoded = json_decode($existing, true);
            $existing = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($existing)) {
            $existing = (array) $existing;
        }

        // Prepare mpesa subarray and encrypt sensitive values before storing
        $mpesa = $existing['mpesa'] ?? [];
        $incomingMpesa = $request->input('mpesa', []);

        $mpesa['consumer_key'] = $incomingMpesa['consumer_key'] ?? ($mpesa['consumer_key'] ?? null);
        $mpesa['shortcode'] = $incomingMpesa['shortcode'] ?? ($mpesa['shortcode'] ?? null);
        $mpesa['environment'] = $incomingMpesa['environment'] ?? ($mpesa['environment'] ?? 'sandbox');
        $mpesa['callback_url'] = $incomingMpesa['callback_url'] ?? ($mpesa['callback_url'] ?? null);
        $mpesa['result_url'] = $incomingMpesa['result_url'] ?? ($mpesa['result_url'] ?? ($mpesa['callback_url'] ?? null));
        $mpesa['head_office_shortcode'] = $incomingMpesa['head_office_shortcode'] ?? ($mpesa['head_office_shortcode'] ?? null);
        $mpesa['simulate'] = isset($incomingMpesa['simulate']) ? (bool) $incomingMpesa['simulate'] : ($mpesa['simulate'] ?? false);

        // Encrypt sensitive fields before persisting
        foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $skey) {
            if (array_key_exists($skey, $incomingMpesa) && $incomingMpesa[$skey] !== null && $incomingMpesa[$skey] !== '') {
                try {
                    $mpesa[$skey] = Crypt::encryptString((string) $incomingMpesa[$skey]);
                } catch (\Exception $e) {
                    // fallback to storing plaintext if encryption fails (avoid data loss)
                    $mpesa[$skey] = (string) $incomingMpesa[$skey];
                }
            } elseif (array_key_exists($skey, $mpesa) && $mpesa[$skey] === null) {
                // keep null
                $mpesa[$skey] = null;
            }
        }

        // Merge other incoming non-sensitive mpesa keys (already handled above for some)
        if (isset($incomingMpesa['consumer_key'])) {
            $mpesa['consumer_key'] = $incomingMpesa['consumer_key'];
        }
        if (isset($incomingMpesa['shortcode'])) {
            $mpesa['shortcode'] = $incomingMpesa['shortcode'];
        }
        if (isset($incomingMpesa['environment'])) {
            $mpesa['environment'] = $incomingMpesa['environment'];
        }
        if (isset($incomingMpesa['callback_url'])) {
            $mpesa['callback_url'] = $incomingMpesa['callback_url'];
        }
        if (isset($incomingMpesa['result_url'])) {
            $mpesa['result_url'] = $incomingMpesa['result_url'];
        }
        if (isset($incomingMpesa['head_office_shortcode'])) {
            $mpesa['head_office_shortcode'] = $incomingMpesa['head_office_shortcode'];
        }

        // Merge validated top-level fields excluding mpesa.* (we will set mpesa as subkey)
        $validatedTop = $validated;
        // Remove any nested mpesa.* keys from top-level validated
        foreach ($validatedTop as $k => $v) {
            if (strpos($k, 'mpesa.') === 0) {
                unset($validatedTop[$k]);
            }
        }

        $existing = array_merge($existing, $validatedTop);
        $existing['mpesa'] = $mpesa;

        $page->content = $existing;
        $page->save();

        return back()->with('success', 'Welcome page updated. Platform MPESA defaults saved.');
    }

    /**
     * Test the platform-level MPESA credentials stored in the CMS welcome page.
     * Returns JSON { success: bool, status: int|null, body_snippet: string|null }
     */
    public function testPlatformMpesa(Request $request)
    {
        try {
            $cms = app(\App\Services\CmsService::class);
            $mp = $cms->getMpesaConfig();
            if (! $mp) {
                return response()->json(['success' => false, 'message' => 'No MPESA configuration found in CMS'], 400);
            }

            $env = $mp['environment'] ?? config('mpesa.environment', 'production');
            $urls = config("mpesa.urls.{$env}");
            $authUrl = config('mpesa.auth_url') ?: ($urls['oauth'] ?? null);
            if (! $authUrl) return response()->json(['success' => false, 'message' => 'Auth URL not configured'], 500);

            $response = \Illuminate\Support\Facades\Http::withBasicAuth($mp['consumer_key'], $mp['consumer_secret'])
                ->timeout(15)
                ->get($authUrl . (str_contains($authUrl, '?') ? '&' : '?') . 'grant_type=client_credentials');

            $status = method_exists($response, 'status') ? $response->status() : null;
            $body = null;
            try { $body = substr($response->body() ?? '', 0, 1000); } catch (\Throwable $_) { $body = null; }

            if (! $response->successful()) {
                return response()->json(['success' => false, 'status' => $status, 'body_snippet' => $body], 400);
            }

            return response()->json(['success' => true, 'status' => $status, 'body_snippet' => $body]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
