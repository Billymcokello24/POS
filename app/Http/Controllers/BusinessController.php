<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\TaxConfiguration;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function settings()
    {
        $business = auth()->user()->currentBusiness;

        if (!$business) {
            abort(403, 'No business associated with your account');
        }

        $taxConfigurations = TaxConfiguration::where('business_id', $business->id)
            ->orderBy('priority')
            ->get();

        return Inertia::render('Business/Settings', [
            'business' => $business,
            'tax_configurations' => $taxConfigurations,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $business = auth()->user()->currentBusiness;

        if (!$business) {
            abort(403, 'No business associated with your account');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tax_id' => 'nullable|string|max:50',
            'receipt_prefix' => 'required|string|max:10',
            'currency' => 'required|string|size:3',
            'timezone' => 'nullable|string',
            // MPESA credential fields (per-business)
            'mpesa_consumer_key' => 'nullable|string|max:255',
            'mpesa_consumer_secret' => 'nullable|string|max:255',
            'mpesa_shortcode' => 'nullable|string|max:50',
            'mpesa_passkey' => 'nullable|string|max:255',
            'mpesa_environment' => 'nullable|in:sandbox,production,live',
            'mpesa_callback_url' => 'nullable|url',
            // New MPESA fields
            'mpesa_head_office_shortcode' => 'nullable|string|max:50',
            'mpesa_head_office_passkey' => 'nullable|string|max:255',
            'mpesa_result_url' => 'nullable|url',
            'mpesa_initiator_name' => 'nullable|string|max:255',
            'mpesa_initiator_password' => 'nullable|string|max:255',
            'mpesa_security_credential' => 'nullable|string|max:1024',
            'mpesa_simulate' => 'nullable|boolean',
        ]);

        // Extract MPESA fields and merge into settings JSON
        $settings = $business->settings ?? [];

        $settings['mpesa'] = [
            'consumer_key' => $validated['mpesa_consumer_key'] ?? null,
            'consumer_secret' => $validated['mpesa_consumer_secret'] ?? null,
            'shortcode' => $validated['mpesa_shortcode'] ?? null,
            'passkey' => $validated['mpesa_passkey'] ?? null,
            'environment' => $validated['mpesa_environment'] ?? null,
            'callback_url' => $validated['mpesa_callback_url'] ?? null,
            'head_office_shortcode' => $validated['mpesa_head_office_shortcode'] ?? null,
            'head_office_passkey' => $validated['mpesa_head_office_passkey'] ?? null,
            'result_url' => $validated['mpesa_result_url'] ?? ($validated['mpesa_callback_url'] ?? null),
            'initiator_name' => $validated['mpesa_initiator_name'] ?? null,
            'initiator_password' => $validated['mpesa_initiator_password'] ?? null,
            'security_credential' => $validated['mpesa_security_credential'] ?? null,
            'simulate' => isset($validated['mpesa_simulate']) ? (bool)$validated['mpesa_simulate'] : false,
        ];

        // Remove mpesa_* keys before updating the main columns
        foreach (['mpesa_consumer_key','mpesa_consumer_secret','mpesa_shortcode','mpesa_passkey','mpesa_environment','mpesa_callback_url','mpesa_head_office_shortcode','mpesa_head_office_passkey','mpesa_result_url','mpesa_initiator_name','mpesa_initiator_password','mpesa_security_credential','mpesa_simulate'] as $k) {
            if (array_key_exists($k, $validated)) unset($validated[$k]);
        }

        $validated['settings'] = $settings;

        $business->update($validated);

        // After saving, attempt to validate MPESA credentials (if provided) and provide feedback
        try {
            $mpesa = $business->settings['mpesa'] ?? null;
            if ($mpesa && !empty($mpesa['consumer_key']) && !empty($mpesa['consumer_secret'])) {
                $environment = $mpesa['environment'] ?? config('mpesa.environment', 'sandbox');
                $urls = config("mpesa.urls.{$environment}");
                $authUrl = config('mpesa.auth_url') ?: ($urls['oauth'] ?? null);
                $timeout = config('mpesa.timeout', 10);

                if (!$authUrl) {
                    // Auth URL not configured globally but we still saved settings - return success with warning
                    \Illuminate\Support\Facades\Log::warning('MPESA auth URL not configured when validating credentials for business ' . $business->id);
                    return back()->with('success', 'Business settings updated successfully (MPESA validation skipped: auth URL not configured).');
                }

                $response = \Illuminate\Support\Facades\Http::withBasicAuth($mpesa['consumer_key'], $mpesa['consumer_secret'])
                    ->timeout($timeout)
                    ->get($authUrl . (str_contains($authUrl, '?') ? '&' : '?') . 'grant_type=client_credentials');

                if ($response->successful()) {
                    return back()->with('success', 'Business settings updated successfully and MPESA credentials validated.');
                }

                // If we reach here token failed
                \Illuminate\Support\Facades\Log::warning('MPESA token validation failed for business ' . $business->id, ['status' => $response->status(), 'body' => $response->body()]);
                return back()->with('warning', 'Business settings saved but MPESA credential validation failed: ' . ($response->json('error_description') ?? $response->status()));
            }
        } catch (\Exception $e) {
            // Log error but return with warning
            \Illuminate\Support\Facades\Log::warning('MPESA validation error for business ' . $business->id, ['error' => $e->getMessage()]);
            return back()->with('warning', 'Business settings saved but MPESA validation encountered an error: ' . $e->getMessage());
        }

        return back()->with('success', 'Business settings updated successfully');
    }

    /**
     * Test MPESA credentials for the authenticated business.
     * Returns JSON { success: bool, message: string }
     */
    public function testMpesa(Request $request)
    {
        $business = auth()->user()->currentBusiness;
        if (!$business) {
            return response()->json(['success' => false, 'message' => 'No business associated with your account'], 403);
        }

        $mpesa = $business->settings['mpesa'] ?? null;
        if (!$mpesa || empty($mpesa['consumer_key']) || empty($mpesa['consumer_secret'])) {
            return response()->json(['success' => false, 'message' => 'MPESA credentials not configured for this business'], 400);
        }

        // Determine environment and token URL (prefer top-level config auth_url)
        $environment = $mpesa['environment'] ?? config('mpesa.environment', 'sandbox');
        $urls = config("mpesa.urls.{$environment}");
        $authUrl = config('mpesa.auth_url') ?: ($urls['oauth'] ?? null);
        $timeout = config('mpesa.timeout', 10);

        try {
            if (!$authUrl) {
                return response()->json(['success' => false, 'message' => 'MPESA auth URL not configured'], 500);
            }
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($mpesa['consumer_key'], $mpesa['consumer_secret'])
                ->timeout($timeout)
                ->get($authUrl . (str_contains($authUrl, '?') ? '&' : '?') . 'grant_type=client_credentials');

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'Failed to obtain token: ' . ($response->json('error_description') ?? $response->status())], 400);
            }

            $token = $response->json('access_token');
            return response()->json(['success' => true, 'message' => 'MPESA credentials are valid (access token obtained)']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error testing MPESA credentials: ' . $e->getMessage()], 500);
        }
    }
}
