<?php

namespace Modules\Notifications\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Domain\Contracts\PushNotifierInterface;
use Modules\Notifications\Infrastructure\NullPushNotifier;
use Modules\Notifications\Presentation\Commands\SendPlanReminders;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this binding for a real FCM client once a Firebase project
        // exists (see NullPushNotifier's docblock).
        $this->app->bind(PushNotifierInterface::class, NullPushNotifier::class);
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../Presentation/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([SendPlanReminders::class]);
        }
    }
}
