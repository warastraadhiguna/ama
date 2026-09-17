<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
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
        // Keep API resource responses flat/consistent whether a resource is
        // the top-level response or embedded in a larger payload (e.g. the
        // `user` key in the login response) — avoids an inconsistent extra
        // "data" wrapper depending on how a given endpoint returns it.
        JsonResource::withoutWrapping();
    }
}
