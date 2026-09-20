<?php

use App\Http\Controllers\Auth\ProcessLogin;
use App\Http\Controllers\Auth\ProcessLogout;
use App\Http\Controllers\Auth\ShowLoginPage;
use App\Http\Controllers\Auth\SwitchRole;
use App\Http\Controllers\Dashboard\IndexDashboard;
use App\Http\Controllers\JenisBerkas\JenisBerkasController;
use App\Http\Controllers\Pengukuran\EditPengukuran;
use App\Http\Controllers\Pengukuran\IndexPengukuran;
use App\Http\Controllers\Pengukuran\UpdatePengukuran;
use App\Http\Controllers\Verifikasi\IndexVerifikasi;
use App\Http\Controllers\Verifikasi\KembalikanPengukuran;
use App\Http\Controllers\Verifikasi\SahkanPengukuran;
use App\Http\Controllers\Verifikasi\ShowVerifikasi;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard or login
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Authentication Routes
Route::get('/login', ShowLoginPage::class)->name('login');
Route::post('/login', ProcessLogin::class)->name('login.store');
Route::post('/logout', ProcessLogout::class)->name('logout');

// Quick Switcher Route for Local Development
if (app()->environment('local')) {
    Route::post('/dev/switch-role/{id}', SwitchRole::class)->name('dev.switch-role');
}

// Protected Application Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', IndexDashboard::class)->name('dashboard');

    // Pengukuran Kinerja (Alur PIC)
    Route::get('/pengukuran', IndexPengukuran::class)->name('pengukuran.index');
    Route::get('/pengukuran/{id}/edit', EditPengukuran::class)->name('pengukuran.edit');
    Route::post('/pengukuran/{id}', UpdatePengukuran::class)->name('pengukuran.update');

    // Verifikasi & Pengesahan Kinerja (Alur Tim Perencanaan)
    Route::get('/verifikasi', IndexVerifikasi::class)->name('verifikasi.index');
    Route::get('/verifikasi/{id}', ShowVerifikasi::class)->name('verifikasi.show');
    Route::post('/verifikasi/{id}/kembalikan', KembalikanPengukuran::class)->name('verifikasi.kembalikan');
    Route::post('/verifikasi/{id}/sahkan', SahkanPengukuran::class)->name('verifikasi.sahkan');

    // Konfigurasi Persyaratan Jenis Berkas (Alur Tim Perencanaan)
    Route::get('/jenis-berkas', [JenisBerkasController::class, 'index'])->name('jenis-berkas.index');
    Route::post('/jenis-berkas', [JenisBerkasController::class, 'store'])->name('jenis-berkas.store');
    Route::put('/jenis-berkas/{id}', [JenisBerkasController::class, 'update'])->name('jenis-berkas.update');
    Route::delete('/jenis-berkas/{id}', [JenisBerkasController::class, 'destroy'])->name('jenis-berkas.destroy');
});
