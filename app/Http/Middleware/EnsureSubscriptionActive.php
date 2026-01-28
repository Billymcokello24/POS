<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login');

        $business = $user->currentBusiness;
        if (! $business) {
            return redirect()->route('dashboard')->with('error', 'No business selected');
        }

        // If business has no active subscription, deny access to protected routes
        if (! $business->activeSubscription()->exists()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Subscription required'], 403);
            }

            return redirect()->route('subscription.index')->with('error', 'Your subscription has expired or is not active.');
        }

        return $next($request);
    }
}

