<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormMail;
use App\Services\CmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill in all fields correctly.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get super admin email from CMS or use fallback
            $adminEmail = config('mail.from.address', 'admin@modernpos.com');

            try {
                $cmsService = app(CmsService::class);
                $cms = $cmsService->getContent();

                // Extract email from CMS
                if (is_string($cms)) {
                    $decoded = json_decode($cms, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $adminEmail = $decoded['contact_email'] ?? $adminEmail;
                    }
                } elseif (is_array($cms)) {
                    $adminEmail = $cms['contact_email'] ?? $adminEmail;
                } elseif (is_object($cms)) {
                    $cmsArr = (array) $cms;
                    $adminEmail = $cmsArr['contact_email'] ?? $adminEmail;
                }
            } catch (\Exception $e) {
                \Log::warning('Could not get CMS email, using default: ' . $e->getMessage());
            }

            // Log the contact form submission
            \Log::info('Contact form submission', [
                'from' => $request->email,
                'subject' => $request->subject,
                'message' => substr($request->message, 0, 100) . '...',
            ]);

            // Try to send email
            try {
                Mail::to($adminEmail)->send(new ContactFormMail(
                    $request->email,
                    $request->subject,
                    $request->message
                ));

                \Log::info('Contact form email sent successfully to: ' . $adminEmail);
            } catch (\Exception $e) {
                // Log email error but still return success to user
                \Log::error('Failed to send contact email: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);

                // Email failed, but we logged the message, so tell user it was received
                return response()->json([
                    'success' => true,
                    'message' => 'Your message has been received. We\'ll get back to you soon!',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully. We\'ll get back to you soon!',
            ]);
        } catch (\Exception $e) {
            \Log::error('Contact form submission failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later or contact us directly at +254 759 814 390.',
            ], 500);
        }
    }
}
