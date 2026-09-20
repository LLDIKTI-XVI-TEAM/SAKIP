<?php

use App\Http\Controllers\Akses\IndexGrant;
use App\Http\Controllers\Akses\RevokeGrant;
use App\Http\Controllers\Akses\StoreGrant;
use App\Http\Controllers\Auth\ProcessLogin;
use App\Http\Controllers\Auth\ProcessLogout;
use App\Http\Controllers\Auth\ShowLoginPage;
use App\Http\Controllers\Auth\SwitchRole;
use App\Http\Controllers\Dashboard\IndexDashboard;
use App\Http\Controllers\Pengukuran\EditPengukuran;
use App\Http\Controllers\Pengukuran\IndexPengukuran;
use App\Http\Controllers\Pengukuran\UpdatePengukuran;
use App\Http\Controllers\Unit\DestroyUnit;
use App\Http\Controllers\Unit\IndexUnit;
use App\Http\Controllers\Unit\StoreUnit;
use App\Http\Controllers\Unit\UpdateUnit;
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

    // Master Unit Organisasi (Admin & Superadmin)
    Route::get('/unit', IndexUnit::class)->name('unit.index');
    Route::post('/unit', StoreUnit::class)->name('unit.store');
    Route::post('/unit/{id}', UpdateUnit::class)->name('unit.update');
    Route::delete('/unit/{id}', DestroyUnit::class)->name('unit.destroy');

    // Manajemen Hak Akses: Grant Izin per Unit (Admin & Superadmin)
    Route::get('/akses/grant', IndexGrant::class)->name('akses.grant.index');
    Route::post('/akses/grant', StoreGrant::class)->name('akses.grant.store');
    Route::delete('/akses/grant/{id}', RevokeGrant::class)->name('akses.grant.destroy');
});
