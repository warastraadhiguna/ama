<?php

namespace Modules\Activities\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ActivitiesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../Presentation/routes.php');
    }
}
