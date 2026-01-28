<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogoutController extends Controller
{
    public function destroy(Request $request)
    {
        try {
            Log::info('Custom logout called', [
                'session_id' => $request->session()->getId(),
                'request_token' => $request->input('_token'),
                'session_token' => $request->session()->token(),
                'cookies' => $request->cookies->all(),
                'headers_cookie' => $request->headers->get('cookie'),
                'headers' => $request->headers->all(),
                'url' => $request->fullUrl(),
            ]);
        } catch (\Throwable $e) {
            // ensure logging won't break logout
            Log::error('Failed to log logout debug info: ' . $e->getMessage());
        }

        // Perform standard logout
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Determine safe redirect
        $redirectTo = $request->input('redirect_to', '/');

        // Only allow internal relative paths
        if (!is_string($redirectTo) || strlen($redirectTo) === 0 || strpos($redirectTo, '/') !== 0) {
            $redirectTo = '/';
        }

        return redirect($redirectTo);
    }
}
