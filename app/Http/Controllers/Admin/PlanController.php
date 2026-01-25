<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::with('features')->orderBy('price_monthly')->get(),
            'features' => Feature::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'size_category' => 'required|string|in:Small,Medium,Large,Enterprise',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_users' => 'required|integer|min:0',
            'max_employees' => 'required|integer|min:0',
            'max_products' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'feature_ids' => 'array',
            'feature_ids.*' => 'exists:features,id',
        ]);

        $plan = Plan::create(array_merge($validated, [
            'slug' => Str::slug($validated['name']),
        ]));

        if ($request->has('feature_ids')) {
            $plan->features()->sync($request->feature_ids);
        }

        return back()->with('success', 'Subscription plan created successfully.');
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'size_category' => 'required|string|in:Small,Medium,Large,Enterprise',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_users' => 'required|integer|min:0',
            'max_employees' => 'required|integer|min:0',
            'max_products' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
            'feature_ids' => 'array',
            'feature_ids.*' => 'exists:features,id',
        ]);

        $plan->update(array_merge($validated, [
            'slug' => Str::slug($validated['name']),
        ]));

        if ($request->has('feature_ids')) {
            $plan->features()->sync($request->feature_ids);
        }

        return back()->with('success', 'Subscription plan updated successfully.');
    }

    public function destroy(Plan $plan)
    {
        // Check if plan is in use
        if ($plan->businesses()->exists()) {
            return back()->with('error', 'Cannot delete plan because it is assigned to businesses. Deactivate it instead.');
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted successfully.');
    }
}
