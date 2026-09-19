<?php

use Illuminate\Support\Facades\Route;
use Modules\WebAdmin\Presentation\Controllers\ActivityMonitoringController;
use Modules\WebAdmin\Presentation\Controllers\AnnouncementController;
use Modules\WebAdmin\Presentation\Controllers\DashboardController;
use Modules\WebAdmin\Presentation\Controllers\MasterDataPageController;
use Modules\WebAdmin\Presentation\Controllers\ReportPageController;

// activities.view: the monitoring permission (ADMIN/MANAGER/SUPERVISOR/
// SUPER_ADMIN) — matches who can see everyone's activities via the API
// (see Modules/Activities). AGRONOMIST doesn't have it and isn't meant to
// use Web Admin.
Route::middleware(['auth', 'permission:activities.view'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('activities', [ActivityMonitoringController::class, 'index'])->name('web.activities.index');
    Route::get('activities/{id}', [ActivityMonitoringController::class, 'show'])->name('web.activities.show');
});

// docs section 29.3 — same permission as the master-data write API.
Route::middleware(['auth', 'permission:master_data.manage'])->prefix('master-data')->group(function (): void {
    Route::get('/', fn () => redirect('/master-data/activity-types'));
    Route::get('{resource}', [MasterDataPageController::class, 'index'])->name('web.master-data.index');
    Route::post('{resource}', [MasterDataPageController::class, 'store'])->name('web.master-data.store');
    Route::put('{resource}/{id}', [MasterDataPageController::class, 'update'])->whereNumber('id')->name('web.master-data.update');
});

// docs section 28 — announcements need their own permission (SUPER_ADMIN/ADMIN).
Route::middleware(['auth', 'permission:announcements.send'])->prefix('announcements')->group(function (): void {
    Route::get('/', [AnnouncementController::class, 'create'])->name('web.announcements.create');
    Route::post('/', [AnnouncementController::class, 'store'])->name('web.announcements.store');
});

// docs section 29.6 — same permission as the reports API.
Route::middleware(['auth', 'permission:reports.view'])->prefix('reports')->group(function (): void {
    Route::get('/', [ReportPageController::class, 'index'])->name('web.reports.index');
    Route::get('export', [ReportPageController::class, 'export'])->name('web.reports.export');
});
