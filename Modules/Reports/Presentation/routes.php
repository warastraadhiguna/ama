<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Presentation\Controllers\ReportController;

Route::middleware(['auth:sanctum', 'permission:reports.view'])->prefix('reports')->group(function (): void {
    Route::get('activities/summary', [ReportController::class, 'summary']);
    Route::get('activities/export', [ReportController::class, 'export']);
});
