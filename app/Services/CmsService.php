<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Support\Facades\Crypt;

class CmsService
{
    /**
     * Return CMS content for the app landing page.
     *
     * @return array|null|string
     */
    public function getContent()
    {
        try {
            $page = Page::where('key', 'welcome')->first();
            return $page?->content ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Return the MPESA configuration stored in the CMS (welcome page) with
     * sensitive fields decrypted where possible.
     *
     * This centralizes the logic so all callers fetch the same decrypted values
     * regardless of how the content was stored (string vs array) and avoids
     * repeating decryption code across controllers.
     *
     * @return array|null
     */
    public function getMpesaConfig(): ?array
    {
        try {
            $content = $this->getContent();
            $arr = [];

            if (is_string($content)) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $arr = $decoded;
                } else {
                    return null;
                }
            } elseif (is_array($content)) {
                $arr = $content;
            } elseif (is_object($content)) {
                $arr = (array) $content;
            } else {
                return null;
            }

            $mpesa = $arr['mpesa'] ?? null;
            if (!is_array($mpesa)) return null;

            // Attempt to decrypt sensitive values if they appear encrypted
            foreach (['consumer_secret', 'passkey', 'head_office_passkey', 'initiator_password', 'security_credential'] as $key) {
                if (array_key_exists($key, $mpesa) && $mpesa[$key] !== null && $mpesa[$key] !== '') {
                    try {
                        $mpesa[$key] = Crypt::decryptString($mpesa[$key]);
                    } catch (\Throwable $e) {
                        // leave as-is if cannot decrypt
                    }
                }
            }

            // Ensure booleans/structure
            $mpesa['simulate'] = isset($mpesa['simulate']) ? (bool) $mpesa['simulate'] : false;
            $mpesa['environment'] = $mpesa['environment'] ?? config('mpesa.environment', 'sandbox');

            return $mpesa;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
