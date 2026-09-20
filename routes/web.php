<?php

use App\Http\Controllers\Auth\ProcessLogin;
use App\Http\Controllers\Auth\ProcessLogout;
use App\Http\Controllers\Auth\ShowLoginPage;
use App\Http\Controllers\Auth\SwitchRole;
use App\Http\Controllers\Dashboard\IndexDashboard;
use App\Http\Controllers\Pengukuran\EditPengukuran;
use App\Http\Controllers\Pengukuran\IndexPengukuran;
use App\Http\Controllers\Pengukuran\UpdatePengukuran;
use App\Http\Controllers\Regulasi\CreateRegulasi;
use App\Http\Controllers\Regulasi\DestroyBerkasRegulasi;
use App\Http\Controllers\Regulasi\DestroyRegulasi;
use App\Http\Controllers\Regulasi\DownloadBerkasRegulasi;
use App\Http\Controllers\Regulasi\EditRegulasi;
use App\Http\Controllers\Regulasi\IndexRegulasi;
use App\Http\Controllers\Regulasi\ShowRegulasi;
use App\Http\Controllers\Regulasi\StoreRegulasi;
use App\Http\Controllers\Regulasi\UpdateRegulasi;
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

    // Dasar Aturan / Regulasi
    Route::get('/regulasi', IndexRegulasi::class)->name('regulasi.index');
    Route::get('/regulasi/create', CreateRegulasi::class)->name('regulasi.create');
    Route::post('/regulasi', StoreRegulasi::class)->name('regulasi.store');
    Route::get('/regulasi/{regulasi}/edit', EditRegulasi::class)->name('regulasi.edit');
    Route::put('/regulasi/{regulasi}', UpdateRegulasi::class)->name('regulasi.update');
    Route::delete('/regulasi/{regulasi}', DestroyRegulasi::class)->name('regulasi.destroy');
    Route::delete('/regulasi/{regulasi}/berkas/{berkas}', DestroyBerkasRegulasi::class)
        ->name('regulasi.berkas.destroy');
    Route::get(
        '/regulasi/{regulasi}/berkas/{berkas}/download',
        DownloadBerkasRegulasi::class,
    )->name('regulasi.berkas.download');
    Route::get('/regulasi/{regulasi}', ShowRegulasi::class)->name('regulasi.show');

    // Pengukuran Kinerja (Alur PIC)
    Route::get('/pengukuran', IndexPengukuran::class)->name('pengukuran.index');
    Route::get('/pengukuran/{id}/edit', EditPengukuran::class)->name('pengukuran.edit');
    Route::post('/pengukuran/{id}', UpdatePengukuran::class)->name('pengukuran.update');

    // Verifikasi & Pengesahan Kinerja (Alur Tim Perencanaan)
    Route::get('/verifikasi', IndexVerifikasi::class)->name('verifikasi.index');
    Route::get('/verifikasi/{id}', ShowVerifikasi::class)->name('verifikasi.show');
    Route::post('/verifikasi/{id}/kembalikan', KembalikanPengukuran::class)->name('verifikasi.kembalikan');
    Route::post('/verifikasi/{id}/sahkan', SahkanPengukuran::class)->name('verifikasi.sahkan');
});
