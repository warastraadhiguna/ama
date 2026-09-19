<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
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

        // Every authenticated API call: generous enough for a field app that
        // syncs a queue in a burst, low enough to stop a runaway client or a
        // stolen token being used to scrape. Keyed per user (falling back to
        // IP) so one office behind a shared NAT does not share a budget.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user('sanctum')?->id ?: $request->ip()));

        // Web Admin login: keyed by email AND ip, so one attacker cannot
        // lock a real user out from elsewhere by guessing their email, and
        // cannot spray one password across accounts from one address either.
        RateLimiter::for('web-login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email'))),
            Limit::perMinute(30)->by($request->ip()),
        ]);
    }
}
