<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GateDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

// ---- Belum login ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ---- Sudah login ----
Route::middleware('auth')->group(function () {

    // Semua peran (viewer, admin, superadmin): lihat dashboard + export
    Route::get('/', [GateDashboardController::class, 'index'])->name('gate.dashboard');   // ganti path kalau beda
    Route::get('/gate/export/{format}', [GateDashboardController::class, 'export'])->name('gate.export');

    // Admin + superadmin: impor dan hapus data
    Route::middleware(EnsureRole::class . ':admin,superadmin')->group(function () {
        Route::post('/gate/import', [GateDashboardController::class, 'import'])->name('gate.import');   // ganti path kalau beda
        Route::post('/gate/clear', [GateDashboardController::class, 'clear'])->name('gate.clear');
    });

    // Superadmin saja: kelola akun
    Route::middleware(EnsureRole::class . ':superadmin')->prefix('akun')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::patch('/{user}/role', [UserController::class, 'updateRole'])->name('role');
        Route::patch('/{user}/password', [UserController::class, 'updatePassword'])->name('password');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});