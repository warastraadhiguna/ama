<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Controllers\AuthController;
use Modules\Identity\Presentation\Controllers\DeviceController;
use Modules\Identity\Presentation\Controllers\MeController;

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [MeController::class, 'show']);
    Route::post('devices/register', [DeviceController::class, 'register']);
});
