<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Presentation\Controllers\WebAuthController;
use Modules\Identity\Presentation\Controllers\WebUserController;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [WebAuthController::class, 'login'])->middleware('throttle:web-login');
});

Route::middleware('auth')->post('logout', [WebAuthController::class, 'logout'])->name('logout');

// docs section 29.2 — User Management, gated by users.manage (ADMIN/SUPER_ADMIN).
Route::middleware(['auth', 'permission:users.manage'])->prefix('users')->group(function (): void {
    Route::get('/', [WebUserController::class, 'index'])->name('web.users.index');
    Route::get('create', [WebUserController::class, 'create'])->name('web.users.create');
    Route::post('/', [WebUserController::class, 'store'])->name('web.users.store');
    Route::get('{id}/edit', [WebUserController::class, 'edit'])->whereNumber('id')->name('web.users.edit');
    Route::put('{id}', [WebUserController::class, 'update'])->whereNumber('id')->name('web.users.update');
    Route::post('{id}/devices/{deviceId}/revoke', [WebUserController::class, 'revokeDevice'])
        ->whereNumber(['id', 'deviceId'])->name('web.users.devices.revoke');
});
