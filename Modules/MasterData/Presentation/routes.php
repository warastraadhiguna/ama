<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterData\Presentation\Controllers\ActivityTypeController;
use Modules\MasterData\Presentation\Controllers\PositionController;
use Modules\MasterData\Presentation\Controllers\ProductCategoryController;
use Modules\MasterData\Presentation\Controllers\ProductController;
use Modules\MasterData\Presentation\Controllers\WorkLocationController;

/**
 * Registers list/show (any authenticated user — mobile syncs these for
 * offline caching per docs section 27) plus write endpoints gated behind
 * the master_data.manage permission (docs section 26).
 */
$masterDataResource = function (string $path, string $controller): void {
    Route::get($path, [$controller, 'index']);
    Route::get("{$path}/{id}", [$controller, 'show']);

    Route::middleware('permission:master_data.manage')->group(function () use ($path, $controller): void {
        Route::post($path, [$controller, 'store']);
        Route::put("{$path}/{id}", [$controller, 'update']);
        Route::delete("{$path}/{id}", [$controller, 'destroy']);
    });
};

Route::middleware('auth:sanctum')->prefix('master')->group(function () use ($masterDataResource): void {
    $masterDataResource('activity-types', ActivityTypeController::class);
    $masterDataResource('product-categories', ProductCategoryController::class);
    $masterDataResource('products', ProductController::class);
    $masterDataResource('positions', PositionController::class);
    $masterDataResource('work-locations', WorkLocationController::class);
});
