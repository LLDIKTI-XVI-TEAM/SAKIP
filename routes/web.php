<?php

use App\Http\Controllers\Auth\DevAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PengukuranController;
use App\Http\Controllers\VerifikasiController;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard or login
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Authentication Routes
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [DevAuthController::class, 'logout'])->name('logout');

// Quick Switcher Route for Local Development
if (app()->environment('local')) {
    Route::post('/dev/switch-role/{id}', [DevAuthController::class, 'switchRole'])->name('dev.switch-role');
}

// Protected Application Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Pengukuran Kinerja (Alur PIC)
    Route::get('/pengukuran', [PengukuranController::class, 'index'])->name('pengukuran.index');
    Route::get('/pengukuran/{id}/edit', [PengukuranController::class, 'edit'])->name('pengukuran.edit');
    Route::post('/pengukuran/{id}', [PengukuranController::class, 'update'])->name('pengukuran.update');

    // Verifikasi & Pengesahan Kinerja (Alur Tim Perencanaan)
    Route::get('/verifikasi', [VerifikasiController::class, 'index'])->name('verifikasi.index');
    Route::get('/verifikasi/{id}', [VerifikasiController::class, 'show'])->name('verifikasi.show');
    Route::post('/verifikasi/{id}/kembalikan', [VerifikasiController::class, 'kembalikan'])->name('verifikasi.kembalikan');
    Route::post('/verifikasi/{id}/sahkan', [VerifikasiController::class, 'sahkan'])->name('verifikasi.sahkan');
});
