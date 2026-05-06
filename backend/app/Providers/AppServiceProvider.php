<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Avoid mixed-content (http images on https frontend) when running behind a proxy/CDN.
        // In production/staging we always generate https URLs for assets.
        if (app()->environment(['production', 'staging'])) {
            URL::forceScheme('https');
        }
    }
}
