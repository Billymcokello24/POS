<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Subscription;

class RealtimeController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['error' => 'Unauthorized'], 401);

        $businessId = $user->current_business_id;
        if (! $businessId) return response()->json(['error' => 'No business context'], 400);

        // Get latest updated timestamps (as unix timestamps) and counts
        $productsLast = Product::where('business_id', $businessId)->max('updated_at');
        $subscriptionsLast = Subscription::where('business_id', $businessId)->max('updated_at');

        // Only consider "final" subscription updates (not 'pending') for auto-reload decisions.
        $subscriptionsLastFinal = Subscription::where('business_id', $businessId)
            ->where('status', '!=', 'pending')
            ->max('updated_at');

        return response()->json([
            'products_last' => $productsLast ? strtotime($productsLast) : 0,
            'products_count' => Product::where('business_id', $businessId)->count(),
            'subscriptions_last' => $subscriptionsLast ? strtotime($subscriptionsLast) : 0,
            'subscriptions_last_final' => $subscriptionsLastFinal ? strtotime($subscriptionsLastFinal) : 0,
            'subscriptions_count' => Subscription::where('business_id', $businessId)->count(),
            'timestamp' => time(),
        ]);
    }
}
