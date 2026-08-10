<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if ($this->app->isProduction()) {
            $applicationUrl = (string) config('app.url');

            if ($applicationUrl !== '') {
                URL::forceRootUrl($applicationUrl);

                if (parse_url($applicationUrl, PHP_URL_SCHEME) === 'https') {
                    URL::forceScheme('https');
                }
            }
        }
    }
}
