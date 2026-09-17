<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Controllers\AdminDeviceController;
use Modules\Identity\Presentation\Controllers\AuthController;
use Modules\Identity\Presentation\Controllers\DeviceController;
use Modules\Identity\Presentation\Controllers\MeController;

Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [MeController::class, 'show']);
    Route::post('devices/register', [DeviceController::class, 'register']);

    // docs section 29.2: admin-facing device management, distinct from the
    // mobile app registering its own device above.
    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('admin/devices', [AdminDeviceController::class, 'index']);
        Route::post('admin/devices/{id}/revoke', [AdminDeviceController::class, 'revoke']);
    });
});
