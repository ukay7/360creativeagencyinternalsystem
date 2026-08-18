<?php

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect(agency_url('dashboard')) : redirect()->route('login'));
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/{module}/{extra?}', [AgencyController::class, 'show'])->where('module', '[a-z_]+')->name('agency.show');
    Route::post('/{module}/{extra?}', [AgencyController::class, 'action'])->where('module', '[a-z_]+')->name('agency.action');
});
