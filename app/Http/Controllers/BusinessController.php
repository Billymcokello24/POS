<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\TaxConfiguration;
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
        ]);

        $business->update($validated);

        return back()->with('success', 'Business settings updated successfully');
    }
}
