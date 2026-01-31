<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class CmsController extends Controller
{
    public function index()
    {
        $page = Page::where('key', 'welcome')->first();

        $defaults = [
            'hero_title' => '',
            'hero_subtitle' => '',
            'hero_bg_image' => '',
            'announcement_text' => '',
            'about_title' => '',
            'about_content' => '',
            'seo_site_title' => '',
            'seo_meta_description' => '',
            'media_logo_url' => '',
            'media_favicon_url' => '',
        ];

        $stored = $page?->content ?? [];
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($stored)) {
            $stored = (array) $stored;
        }

        $content = array_merge($defaults, $stored);

        return Inertia::render('Admin/Cms/Index', [
            'cms' => $content,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            // Hero
            'hero_title' => 'nullable|string',
            'hero_subtitle' => 'nullable|string',
            'hero_bg_image' => 'nullable|url',
            'announcement_text' => 'nullable|string',
            // About section
            'about_title' => 'nullable|string',
            'about_content' => 'nullable|string',
            // SEO
            'seo_site_title' => 'nullable|string',
            'seo_meta_description' => 'nullable|string',
            // Media
            'media_logo_url' => 'nullable|url',
            'media_favicon_url' => 'nullable|url',
        ]);

        $page = Page::firstOrCreate(['key' => 'welcome']);
        $existing = $page->content ?? [];
        if (is_string($existing)) {
            $decoded = json_decode($existing, true);
            $existing = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($existing)) {
            $existing = (array) $existing;
        }

        // Merge only CMS validated fields to avoid wiping out MPESA data
        $page->content = array_merge($existing, $validated);
        $page->save();

        \App\Models\AuditLog::log(
            'cms.update',
            "Super Admin updated the Landing Page (CMS) content.",
            $validated
        );

        return back()->with('success', 'Welcome page updated successfully.');
    }
}
