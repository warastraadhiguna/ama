<?php

namespace Modules\Planning\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../Presentation/routes.php');
    }
}
