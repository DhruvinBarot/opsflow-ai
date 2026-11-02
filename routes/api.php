<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OperationalRequestController;
use App\Http\Controllers\Api\RequestAssignmentController;
use App\Http\Controllers\Api\RequestWorkflowController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::apiResource('requests', OperationalRequestController::class)
        ->parameters(['requests' => 'operationalRequest'])
        ->except('destroy');

    Route::prefix('requests/{operationalRequest}')->name('requests.')->group(function () {
        Route::get('/history', [OperationalRequestController::class, 'history'])->name('history');
        Route::post('/assign', [RequestAssignmentController::class, 'store'])->name('assign');

        Route::controller(RequestWorkflowController::class)->group(function () {
            Route::post('/submit', 'submit')->name('submit');
            Route::post('/review', 'startReview')->name('review');
            Route::post('/approve', 'approve')->name('approve');
            Route::post('/reject', 'reject')->name('reject');
            Route::post('/start', 'start')->name('start');
            Route::post('/complete', 'complete')->name('complete');
        });
    });
});
