<?php

use Illuminate\Support\Facades\Route;
use Modules\WebAdmin\Presentation\Controllers\ActivityMonitoringController;
use Modules\WebAdmin\Presentation\Controllers\DashboardController;

// activities.view: the monitoring permission (ADMIN/MANAGER/SUPERVISOR/
// SUPER_ADMIN) — matches who can see everyone's activities via the API
// (see Modules/Activities). AGRONOMIST doesn't have it and isn't meant to
// use Web Admin.
Route::middleware(['auth', 'permission:activities.view'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('activities', [ActivityMonitoringController::class, 'index'])->name('web.activities.index');
    Route::get('activities/{id}', [ActivityMonitoringController::class, 'show'])->name('web.activities.show');
});
