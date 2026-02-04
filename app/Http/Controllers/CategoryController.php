<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasPermission('view_categories')) {
            abort(403, 'Unauthorized');
        }
        $businessId = auth()->user()->current_business_id;

        $categories = Category::where('business_id', $businessId)
            ->with(['parent', 'products'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('create_categories')) {
            abort(403, 'Unauthorized');
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $businessId = auth()->user()->current_business_id;

        // If user doesn't have a business, assign them to the first business or create one
        if (!$businessId) {
            $business = \App\Models\Business::first();
            if (!$business) {
                $business = \App\Models\Business::create([
                    'name' => 'Default Business',
                    'business_type' => 'retail',
                    'address' => 'Default Address',
                    'phone' => '0000000000',
                    'email' => 'business@example.com',
                    'receipt_prefix' => 'REC',
                    'currency' => 'USD',
                ]);
            }
            $businessId = $business->id;
            auth()->user()->update(['current_business_id' => $businessId]);
        }

        $validated['business_id'] = $businessId;
        $validated['slug'] = Str::slug($validated['name']);

        // Ensure slug is unique within business
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (Category::where('business_id', $validated['business_id'])
            ->where('slug', $validated['slug'])
            ->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        $category = Category::create($validated);

        // Notify all users in the business
        $business = \App\Models\Business::find($businessId);
        if ($business) {
            foreach ($business->users as $user) {
                $user->notify(new \App\Notifications\CategoryCreatedNotification($category));
            }
        }

        return back()->with('success', 'Category created successfully');
    }

    public function update(Request $request, Category $category)
    {
        if (!auth()->user()->hasPermission('edit_categories')) {
            abort(403, 'Unauthorized');
        }
        // Check authorization
        if ($category->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Update slug if name changed
        if ($validated['name'] !== $category->name) {
            $validated['slug'] = Str::slug($validated['name']);

            // Ensure slug is unique within business
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (Category::where('business_id', $category->business_id)
                ->where('slug', $validated['slug'])
                ->where('id', '!=', $category->id)
                ->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $category->update($validated);

        return back()->with('success', 'Category updated successfully');
    }

    public function destroy(Category $category)
    {
        if (!auth()->user()->hasPermission('delete_categories')) {
            abort(403, 'Unauthorized');
        }
        // Check authorization
        if ($category->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized');
        }

        // Check if category has products
        if ($category->products()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete category with products. Please reassign or delete products first.']);
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully');
    }
}
