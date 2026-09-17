<?php

use Illuminate\Support\Facades\Route;
use Modules\Evidence\Presentation\Controllers\EvidenceController;

Route::middleware(['auth:sanctum', 'permission:activities.create'])
    ->prefix('activities/{activityId}')
    ->whereNumber('activityId')
    ->group(function (): void {
        Route::post('location', [EvidenceController::class, 'submitLocation']);
        Route::post('photos', [EvidenceController::class, 'uploadPhoto']);
        Route::post('complete', [EvidenceController::class, 'complete']);
    });
