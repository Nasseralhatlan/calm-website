<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Public / unauthenticated ────────────────────────────────────────────────
Route::middleware('throttle:public')->group(function (): void {
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:otp-request');

    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify');
});

// ─── Authenticated (any role) ────────────────────────────────────────────────
Route::middleware(['auth:api', 'throttle:authenticated'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::get('/user', [UserController::class, 'me']);
    Route::patch('/user', [UserController::class, 'update']);
});

// ─── Admin-only ──────────────────────────────────────────────────────────────
Route::middleware(['auth:api', 'admin', 'throttle:authenticated'])
    ->prefix('admin')
    ->group(function (): void {
        // future admin endpoints land here
    });
