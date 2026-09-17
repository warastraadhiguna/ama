<?php

use Illuminate\Support\Facades\Route;
use Modules\Planning\Presentation\Controllers\PlanController;

Route::middleware(['auth:sanctum', 'permission:plans.view|plans.create'])
    ->prefix('plans')
    ->group(function (): void {
        Route::get('/', [PlanController::class, 'index']);
        Route::get('{id}', [PlanController::class, 'show']);

        Route::middleware('permission:plans.create')->group(function (): void {
            Route::post('/', [PlanController::class, 'store']);
            Route::put('{id}', [PlanController::class, 'update']);
        });
    });
