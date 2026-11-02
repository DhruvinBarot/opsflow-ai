<?php

use App\Http\Controllers\Web\SessionController;
use App\Models\OperationalRequest;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

/*
 * App pages are thin shells; their data comes from /api/* under the same session.
 * Page-level gates mirror the API's rules so users never land on a screen they cannot use.
 */
Route::middleware('auth')->prefix('app')->name('app.')->group(function () {
    Route::view('/', 'app.overview')->name('overview');
    Route::view('/requests', 'app.requests.index', ['scope' => 'mine'])->name('requests.mine');
    Route::view('/requests/new', 'app.requests.form')->name('requests.create');

    Route::get('/requests/{operationalRequest}', fn (OperationalRequest $operationalRequest) => view('app.requests.show', ['requestId' => $operationalRequest->id]))
        ->can('view', 'operationalRequest')
        ->whereNumber('operationalRequest')
        ->name('requests.show');

    Route::get('/requests/{operationalRequest}/edit', fn (OperationalRequest $operationalRequest) => view('app.requests.form', ['requestId' => $operationalRequest->id]))
        ->can('update', 'operationalRequest')
        ->whereNumber('operationalRequest')
        ->name('requests.edit');

    Route::middleware('can:operations')->group(function () {
        Route::view('/all-requests', 'app.requests.index', ['scope' => 'all'])->name('requests.all');
        Route::view('/review', 'app.review')->name('review');
    });

    Route::view('/admin', 'app.admin')->middleware('can:admin')->name('admin');
});
