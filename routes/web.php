<?php

use App\Http\Controllers\Access\RoleAssignment;
use App\Http\Controllers\Auth\KeycloakCallback;
use App\Http\Controllers\Auth\ProcessLogout;
use App\Http\Controllers\Auth\RedirectToKeycloak;
use App\Http\Controllers\Auth\UserActivation;
use App\Http\Controllers\Dashboard\IndexDashboard;
use App\Http\Controllers\JenisBerkas\JenisBerkasController;
use App\Http\Controllers\Pengukuran\DownloadBuktiKlaimPengukuran;
use App\Http\Controllers\Pengukuran\DownloadBuktiPengukuran;
use App\Http\Controllers\Pengukuran\EditPengukuran;
use App\Http\Controllers\Pengukuran\IndexPengukuran;
use App\Http\Controllers\Pengukuran\PreviewPengukuran;
use App\Http\Controllers\Pengukuran\UpdatePengukuran;
use App\Http\Controllers\Verifikasi\IndexVerifikasi;
use App\Http\Controllers\Verifikasi\KembalikanPengukuran;
use App\Http\Controllers\Verifikasi\SahkanPengukuran;
use App\Http\Controllers\Verifikasi\ShowVerifikasi;
use App\Http\Controllers\Verifikasi\VerifyPengukuran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/login', RedirectToKeycloak::class)->name('login')->block();
// Lock mencakup empat request provider yang masing-masing dibatasi sepuluh detik.
Route::get('/auth/keycloak/callback', KeycloakCallback::class)->name('auth.callback')->block(60, 10);
Route::get('/auth/error', fn () => Inertia::render('Auth/Error'))->name('auth.error');
Route::get('/auth/logged-out', function () {
    Inertia::clearHistory();

    return Inertia::render('Auth/LoggedOut');
})->name('auth.logged-out');
Route::post('/logout', ProcessLogout::class)->name('logout')->block();
Route::get('/auth/pending', function (Request $request) {
    return $request->user()->fresh()->is_active ? redirect()->route('dashboard') : Inertia::render('Auth/Pending');
})->middleware('auth')->name('auth.pending');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/akses/peran', [RoleAssignment::class, 'index'])->name('role-assignment.index');
    Route::get('/akses/peran/hasil', [RoleAssignment::class, 'result'])->name('role-assignment.result');
    Route::post('/akses/peran/{user}', [RoleAssignment::class, 'store'])->whereUuid('user')->name('role-assignment.store');
    Route::get('/dashboard', IndexDashboard::class)->name('dashboard');
    Route::get('/akses/aktivasi', [UserActivation::class, 'index'])->name('activation.index');
    Route::post('/akses/aktivasi/{user}', [UserActivation::class, 'store'])->whereUuid('user')->name('activation.store');
    Route::get('/pengukuran', IndexPengukuran::class)->name('pengukuran.index');
    Route::get('/pengukuran/{id}/edit', EditPengukuran::class)->whereUuid('id')->name('pengukuran.edit');
    Route::post('/pengukuran/{id}', UpdatePengukuran::class)->whereUuid('id')->name('pengukuran.update');
    Route::post('/pengukuran/{id}/pratinjau', PreviewPengukuran::class)->whereUuid('id')->name('pengukuran.preview');
    Route::get('/pengukuran/{id}/bukti/{buktiId}', [DownloadBuktiPengukuran::class, '__invoke'])->whereUuid('id')->whereUuid('buktiId')->name('pengukuran.bukti');
    Route::get('/pengukuran/{id}/bukti-klaim/{buktiId}', DownloadBuktiKlaimPengukuran::class)->whereUuid('id')->whereUuid('buktiId')->name('pengukuran.bukti-klaim');
    Route::get('/verifikasi', IndexVerifikasi::class)->name('verifikasi.index');
    Route::get('/verifikasi/{id}', ShowVerifikasi::class)->whereUuid('id')->name('verifikasi.show');
    Route::post('/verifikasi/{id}/verifikasi', [VerifyPengukuran::class, '__invoke'])->whereUuid('id')->name('verifikasi.verify');
    Route::post('/verifikasi/{id}/kembalikan', KembalikanPengukuran::class)->whereUuid('id')->name('verifikasi.kembalikan');
    Route::post('/verifikasi/{id}/sahkan', SahkanPengukuran::class)->whereUuid('id')->name('verifikasi.sahkan');

    // Konfigurasi Persyaratan Jenis Berkas (Alur Tim Perencanaan)
    Route::get('/jenis-berkas', [JenisBerkasController::class, 'index'])->name('jenis-berkas.index');
    Route::post('/jenis-berkas', [JenisBerkasController::class, 'store'])->name('jenis-berkas.store');
    Route::put('/jenis-berkas/{id}', [JenisBerkasController::class, 'update'])->whereUuid('id')->name('jenis-berkas.update');
    Route::delete('/jenis-berkas/{id}', [JenisBerkasController::class, 'destroy'])->whereUuid('id')->name('jenis-berkas.destroy');
});
