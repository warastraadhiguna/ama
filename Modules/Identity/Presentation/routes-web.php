<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Controllers\WebAuthController;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [WebAuthController::class, 'login']);
});

Route::middleware('auth')->post('logout', [WebAuthController::class, 'logout'])->name('logout');
