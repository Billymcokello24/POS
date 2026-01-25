<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeature
{
    /**
     * Allow request through by default. If feature gates are configured, implement checks here.
     */
    public function handle(Request $request, Closure $next, $feature = null)
    {
        // If no feature provided, allow through
        if (!$feature) {
            return $next($request);
        }

        $user = $request->user();

        // If there's no authenticated user or no current business selected, deny access
        if (!$user || !$user->current_business_id) {
            abort(403, 'Business context required to check feature access.');
        }

        $business = \App\Models\Business::find($user->current_business_id);
        if (!$business) {
            abort(403, 'Business not found.');
        }

        // Business::hasFeature expects the feature key (e.g., 'categories')
        if (! $business->hasFeature($feature)) {
            abort(403, 'This feature is not available for your business.');
        }

        return $next($request);
    }
}
