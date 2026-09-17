<?php

use Illuminate\Support\Facades\Route;
use Modules\Activities\Presentation\Controllers\ActivityController;

Route::middleware(['auth:sanctum', 'permission:activities.view|activities.create'])
    ->prefix('activities')
    ->group(function (): void {
        Route::get('/', [ActivityController::class, 'index']);
        Route::get('{id}', [ActivityController::class, 'show']);

        Route::middleware('permission:activities.create')->group(function (): void {
            Route::post('/', [ActivityController::class, 'store']);
        });
    });

// /activities/{id}/location, /photos, /complete are Milestone F (Evidence).
// /activities/{id}/verify (activities.verify) is a future review action —
// see docs section 23, not forced for V1.
