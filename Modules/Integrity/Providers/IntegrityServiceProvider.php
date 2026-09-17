<?php

namespace Modules\Integrity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Integrity\Domain\Contracts\PlayIntegrityVerifierInterface;
use Modules\Integrity\Infrastructure\NullPlayIntegrityVerifier;

class IntegrityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this binding for a real Google Play Integrity API client
        // once credentials exist (see NullPlayIntegrityVerifier's docblock).
        $this->app->bind(PlayIntegrityVerifierInterface::class, NullPlayIntegrityVerifier::class);
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../Presentation/routes.php');
    }
}
