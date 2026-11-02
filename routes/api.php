<?php

use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\AIAnalysisController;
use App\Http\Controllers\Api\AssignableUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OperationalRequestController;
use App\Http\Controllers\Api\RequestActivityController;
use App\Http\Controllers\Api\RequestAssignmentController;
use App\Http\Controllers\Api\RequestCommentController;
use App\Http\Controllers\Api\RequestWorkflowController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/activity', [ActivityFeedController::class, 'index'])->name('activity.index');
    Route::get('/users/assignable', [AssignableUserController::class, 'index'])->name('users.assignable');

    Route::apiResource('requests', OperationalRequestController::class)
        ->parameters(['requests' => 'operationalRequest'])
        ->except('destroy');

    Route::prefix('requests/{operationalRequest}')->name('requests.')->group(function () {
        Route::get('/history', [OperationalRequestController::class, 'history'])->name('history');
        Route::get('/activity', [RequestActivityController::class, 'index'])->name('activity');
        Route::get('/ai-analysis', [AIAnalysisController::class, 'show'])->name('ai-analysis');
        Route::get('/comments', [RequestCommentController::class, 'index'])->name('comments.index');
        Route::post('/comments', [RequestCommentController::class, 'store'])->name('comments.store');
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
