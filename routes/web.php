<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GateDashboardController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [GateDashboardController::class, 'index'])->name('gate.dashboard');
Route::post('/import', [GateDashboardController::class, 'import'])->name('gate.import');
