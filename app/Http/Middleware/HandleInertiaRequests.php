<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'business' => $request->user()?->currentBusiness,
                'is_impersonating' => $request->session()->has('impersonating_from'),
                'features' => $request->user()?->currentBusiness
                    ? $request->user()->currentBusiness->getEnabledFeatureKeys()
                    : [],
                'role_level' => $request->user()
                    ? $request->user()->roles()
                        ->wherePivot('business_id', $request->user()->current_business_id)
                        ->first()?->level ?? ($request->user()->is_super_admin ? 1000 : 0)
                    : 0,
                'permissions' => $request->user()
                    ? \App\Models\Permission::whereHas('roles', function($q) use ($request) {
                        $q->whereIn('roles.id', $request->user()->roles()
                            ->wherePivot('business_id', $request->user()->current_business_id)
                            ->pluck('roles.id'));
                    })->pluck('name')->unique()->values()
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'saleId' => fn () => $request->session()->get('saleId'),
            ],
            'currency' => fn () => $request->user()?->currentBusiness?->currency ?? 'USD',
            'cms' => function () {
                try {
                    $page = \App\Models\Page::where('key', 'welcome')->first();
                    return $page?->content ?? null;
                } catch (\Throwable $e) {
                    return null;
                }
            },
         ];
     }
 }
