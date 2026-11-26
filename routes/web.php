<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GateDashboardController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [GateDashboardController::class, 'index'])->name('gate.dashboard');
Route::post('/import', [GateDashboardController::class, 'import'])->name('gate.import');

Route::get('/gate/export/{format}', [GateDashboardController::class, 'export'])->name('gate.export');
Route::post('/gate/clear', [GateDashboardController::class, 'clear'])->name('gate.clear');
