<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

// add observer import
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Product;
use App\Models\Category;
use App\Observers\SubscriptionObserver;
use App\Observers\SupportTicketObserver;
use App\Observers\ProductObserver;
use App\Observers\CategoryObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the subscription observer
        Subscription::observe(SubscriptionObserver::class);

        // Register the support ticket observer
        SupportTicket::observe(SupportTicketObserver::class);

        // Register product observer
        Product::observe(ProductObserver::class);

        // Register category observer
        Category::observe(CategoryObserver::class);

        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
