<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrity\Presentation\Controllers\IntegrityController;

Route::middleware('auth:sanctum')->post('integrity/play', [IntegrityController::class, 'verifyPlay']);
