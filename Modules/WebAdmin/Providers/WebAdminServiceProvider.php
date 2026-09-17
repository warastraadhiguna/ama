<?php

namespace Modules\WebAdmin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WebAdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../Presentation/routes-web.php');
    }
}
