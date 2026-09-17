<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Domain\Repositories\DeviceRepositoryInterface;
use Modules\Identity\Domain\Repositories\RefreshTokenRepositoryInterface;
use Modules\Identity\Infrastructure\Persistence\EloquentDeviceRepository;
use Modules\Identity\Infrastructure\Persistence\EloquentRefreshTokenRepository;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RefreshTokenRepositoryInterface::class, EloquentRefreshTokenRepository::class);
        $this->app->bind(DeviceRepositoryInterface::class, EloquentDeviceRepository::class);
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../Presentation/routes.php');
    }
}
