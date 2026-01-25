<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Feature;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeatureController extends Controller
{
    public function index()
    {
        $features = Feature::all();
        $businesses = Business::with('features')->paginate(15);

        return Inertia::render('Admin/Features/Index', [
            'features' => $features,
            'businesses' => $businesses,
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'feature_id' => 'required|exists:features,id',
            'is_enabled' => 'required|boolean',
        ]);

        $business = Business::findOrFail($request->business_id);

        $business->features()->syncWithoutDetaching([
            $request->feature_id => ['is_enabled' => $request->is_enabled],
        ]);

        return back()->with('success', 'Feature visibility updated.');
    }
}
