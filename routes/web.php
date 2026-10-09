<?php

use App\Http\Controllers\Access\DenyManagement;
use App\Http\Controllers\Access\EffectivePermissionExplorer;
use App\Http\Controllers\Access\RoleAssignment;
use App\Http\Controllers\Access\RolePermissionManagement;
use App\Http\Controllers\Akses\IndexGrant;
use App\Http\Controllers\Akses\RevokeGrant;
use App\Http\Controllers\Akses\SearchGrantUsers;
use App\Http\Controllers\Akses\StoreGrant;
use App\Http\Controllers\Auth\KeycloakCallback;
use App\Http\Controllers\Auth\PendingAccount;
use App\Http\Controllers\Auth\ProcessLogout;
use App\Http\Controllers\Auth\ProcessSsoLogout;
use App\Http\Controllers\Auth\RedirectToKeycloak;
use App\Http\Controllers\Auth\UserActivation;
use App\Http\Controllers\Dashboard\IndexDashboard;
use App\Http\Controllers\Indikator\IndikatorKomponenController;
use App\Http\Controllers\Jadwal\JadwalController;
use App\Http\Controllers\JenisBerkas\JenisBerkasController;
use App\Http\Controllers\PenanggungJawab\PenanggungJawabController;
use App\Http\Controllers\Pengaturan\IndexPengaturan;
use App\Http\Controllers\Pengaturan\StoragePolicyController;
use App\Http\Controllers\Pengaturan\UpdatePengaturan;
use App\Http\Controllers\Pengukuran\DownloadBuktiKlaimPengukuran;
use App\Http\Controllers\Pengukuran\DownloadBuktiPengukuran;
use App\Http\Controllers\Pengukuran\EditPengukuran;
use App\Http\Controllers\Pengukuran\IndexPengukuran;
use App\Http\Controllers\Pengukuran\PreviewPengukuran;
use App\Http\Controllers\Pengukuran\UpdatePengukuran;
use App\Http\Controllers\Perencanaan\ChangeIndicatorFormula;
use App\Http\Controllers\Perencanaan\DestroyIndikator;
use App\Http\Controllers\Perencanaan\DestroySasaran;
use App\Http\Controllers\Perencanaan\IndexSasaranIndikator;
use App\Http\Controllers\Perencanaan\PindahUnitIndikator;
use App\Http\Controllers\Perencanaan\PreviewIndicatorFormula;
use App\Http\Controllers\Perencanaan\ShowIndicatorEditor;
use App\Http\Controllers\Perencanaan\StoreIndikator;
use App\Http\Controllers\Perencanaan\StoreSasaran;
use App\Http\Controllers\Perencanaan\UpdateIndikator;
use App\Http\Controllers\Perencanaan\UpdateSasaran;
use App\Http\Controllers\Periode\PeriodeController;
use App\Http\Controllers\PerjanjianKinerja\PerjanjianKinerjaController;
use App\Http\Controllers\RencanaAksi\DownloadBuktiRencanaAksiController;
use App\Http\Controllers\RencanaAksi\RencanaAksiEvidenceController;
use App\Http\Controllers\Regulasi\CreateRegulasi;
use App\Http\Controllers\Regulasi\DestroyBerkasRegulasi;
use App\Http\Controllers\Regulasi\DestroyRegulasi;
use App\Http\Controllers\Regulasi\DownloadBerkasRegulasi;
use App\Http\Controllers\Regulasi\EditRegulasi;
use App\Http\Controllers\Regulasi\IndexRegulasi;
use App\Http\Controllers\Regulasi\ShowRegulasi;
use App\Http\Controllers\Regulasi\StoreRegulasi;
use App\Http\Controllers\Regulasi\UpdateRegulasi;
use App\Http\Controllers\RencanaAksi\DaftarRencanaAksi;
use App\Http\Controllers\RencanaAksi\PreviewRencanaAksiTarget;
use App\Http\Controllers\RencanaAksi\ShowRencanaAksi;
use App\Http\Controllers\RencanaAksi\StoreRencanaAksiDraft;
use App\Http\Controllers\RencanaAksi\UpdateRencanaAksiTarget;
use App\Http\Controllers\Renstra\ChangeRenstraStatusController;
use App\Http\Controllers\Renstra\CreateRenstra;
use App\Http\Controllers\Renstra\DestroyBerkasRenstra;
use App\Http\Controllers\Renstra\DestroyRenstra;
use App\Http\Controllers\Renstra\DownloadBerkasRenstra;
use App\Http\Controllers\Renstra\EditRenstra;
use App\Http\Controllers\Renstra\IndexRenstra;
use App\Http\Controllers\Renstra\ShowRenstra;
use App\Http\Controllers\Renstra\StoreRenstra;
use App\Http\Controllers\Renstra\UpdateRenstra;
use App\Http\Controllers\TargetTahunan\TargetTahunanController;
use App\Http\Controllers\Unit\DestroyUnit;
use App\Http\Controllers\Unit\IndexUnit;
use App\Http\Controllers\Unit\StoreUnit;
use App\Http\Controllers\Unit\UpdateUnit;
use App\Http\Controllers\Verifikasi\IndexVerifikasi;
use App\Http\Controllers\Verifikasi\KembalikanPengukuran;
use App\Http\Controllers\Verifikasi\SahkanPengukuran;
use App\Http\Controllers\Verifikasi\ShowVerifikasi;
use App\Http\Controllers\Verifikasi\VerifyPengukuran;
use App\Http\Middleware\EnsurePenanggungJawabActorIsActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/login', RedirectToKeycloak::class)->name('login')->block();
// Lock mencakup empat request provider yang masing-masing dibatasi sepuluh detik.
Route::get('/auth/keycloak/callback', KeycloakCallback::class)->name('auth.callback')->block(60, 10);
Route::get('/auth/error', fn (Request $request) => Inertia::render('Auth/Error', ['recoveryRetry' => $request->session()->pull('auth_recovery_retry') === true]))->name('auth.error');
Route::get('/auth/logged-out', function () {
    Inertia::clearHistory();

    return Inertia::render('Auth/LoggedOut');
})->name('auth.logged-out');
Route::post('/logout', ProcessLogout::class)->name('logout')->block();
Route::post('/logout/sso', ProcessSsoLogout::class)->name('logout.sso')->block();
Route::get('/auth/pending', PendingAccount::class)->middleware('auth')->name('auth.pending');

Route::middleware(['auth', EnsurePenanggungJawabActorIsActive::class])->group(function () {
    Route::post('/perencanaan/indikator/{indikator}/penanggung-jawab', [PenanggungJawabController::class, 'store'])->whereUuid('indikator')->name('penanggung-jawab.store');
    Route::post('/perencanaan/indikator/{indikator}/penanggung-jawab/pergantian', [PenanggungJawabController::class, 'change'])->whereUuid('indikator')->name('penanggung-jawab.change');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/penanggung-jawab', [PenanggungJawabController::class, 'index'])->name('penanggung-jawab.index');
    Route::get('/penanggung-jawab/opsi/pengguna', [PenanggungJawabController::class, 'users'])->name('penanggung-jawab.users');
    Route::get('/perencanaan/indikator/{indikator}/penanggung-jawab', [PenanggungJawabController::class, 'show'])->whereUuid('indikator')->name('penanggung-jawab.show');
    Route::get('/perencanaan/indikator/{indikator}/penanggung-jawab/hak-kerja', [PenanggungJawabController::class, 'readiness'])->whereUuid('indikator')->name('penanggung-jawab.readiness');

    Route::get('/perencanaan/indikator/{indikator}/target-tahunan/{tahun}/editor', [TargetTahunanController::class, 'editor'])->whereUuid('indikator')->where('tahun', '[0-9]{1,4}')->name('target-tahunan.editor');
    Route::put('/perencanaan/indikator/{indikator}/target-tahunan/{tahun}', [TargetTahunanController::class, 'update'])->whereUuid('indikator')->where('tahun', '[0-9]{1,4}')->name('target-tahunan.update');
    Route::get('/jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
    Route::get('/jadwal/create', [JadwalController::class, 'create'])->name('jadwal.create');
    Route::get('/jadwal/opsi/{jenis}', [JadwalController::class, 'options'])->whereIn('jenis', ['renstra', 'periode'])->name('jadwal.options');
    Route::get('/jadwal/{jadwal}', [JadwalController::class, 'show'])->whereUuid('jadwal')->name('jadwal.show');
    Route::post('/jadwal', [JadwalController::class, 'store'])->name('jadwal.store');
    Route::put('/jadwal/{jadwal}', [JadwalController::class, 'update'])->whereUuid('jadwal')->name('jadwal.update');
    Route::get('/jadwal/{jadwal}/kesiapan-aktivasi', [JadwalController::class, 'readiness'])->whereUuid('jadwal')->name('jadwal.activation-readiness');
    Route::post('/jadwal/{jadwal}/aktivasi', [JadwalController::class, 'activate'])->whereUuid('jadwal')->name('jadwal.activate');
    Route::get('/periode', [PeriodeController::class, 'index'])->name('periode.index');
    Route::post('/periode', [PeriodeController::class, 'store'])->name('periode.store');
    Route::post('/periode/ganti-nilai-akhir', [PeriodeController::class, 'replaceFinal'])->name('periode.replace-final');
    Route::put('/periode/{periode}', [PeriodeController::class, 'update'])->whereUuid('periode')->name('periode.update');
    Route::get('/auth/recovered', fn () => Inertia::render('Auth/Recovered'))->name('auth.recovered');
    Route::get('/akses/izin-peran', [RolePermissionManagement::class, 'index'])->name('role-permission.index');
    Route::get('/akses/jelaskan-izin', [EffectivePermissionExplorer::class, 'index'])->name('effective-permission.index');
    Route::get('/akses/jelaskan-izin/opsi/pengguna', [EffectivePermissionExplorer::class, 'users'])->name('effective-permission.users');
    Route::get('/akses/jelaskan-izin/opsi/unit', [EffectivePermissionExplorer::class, 'units'])->name('effective-permission.units');
    Route::get('/akses/deny', [DenyManagement::class, 'index'])->name('deny.index');
    Route::get('/akses/deny/opsi/pengguna', [DenyManagement::class, 'users'])->name('deny.users');
    Route::get('/akses/deny/opsi/unit', [DenyManagement::class, 'units'])->name('deny.units');
    Route::get('/akses/deny/opsi/izin', [DenyManagement::class, 'permissions'])->name('deny.permissions');
    Route::get('/akses/deny/hasil', [DenyManagement::class, 'result'])->name('deny.result');
    Route::post('/akses/deny', [DenyManagement::class, 'store'])->name('deny.store');
    Route::post('/akses/deny/{deny}/cabut', [DenyManagement::class, 'revoke'])->whereUuid('deny')->name('deny.revoke');
    Route::get('/akses/peran', [RoleAssignment::class, 'index'])->name('role-assignment.index');
    Route::get('/akses/peran/hasil', [RoleAssignment::class, 'result'])->name('role-assignment.result');
    Route::post('/akses/peran/{user}', [RoleAssignment::class, 'store'])->whereUuid('user')->name('role-assignment.store');
    Route::get('/dashboard', IndexDashboard::class)->name('dashboard');
    Route::get('/akses/aktivasi', [UserActivation::class, 'index'])->name('activation.index');
    Route::post('/akses/aktivasi/{user}', [UserActivation::class, 'store'])->whereUuid('user')->name('activation.store');

    // Regulasi
    Route::get('/regulasi', IndexRegulasi::class)->name('regulasi.index');
    Route::get('/regulasi/create', CreateRegulasi::class)->name('regulasi.create');
    Route::post('/regulasi', StoreRegulasi::class)->name('regulasi.store');
    Route::get('/regulasi/{regulasi}/edit', EditRegulasi::class)->whereUuid('regulasi')->name('regulasi.edit');
    Route::put('/regulasi/{regulasi}', UpdateRegulasi::class)->whereUuid('regulasi')->name('regulasi.update');
    Route::delete('/regulasi/{regulasi}', DestroyRegulasi::class)->whereUuid('regulasi')->name('regulasi.destroy');
    Route::delete('/regulasi/{regulasi}/berkas/{berkas}', DestroyBerkasRegulasi::class)->whereUuid('regulasi')->whereUuid('berkas')->name('regulasi.berkas.destroy');
    Route::get('/regulasi/{regulasi}/berkas/{berkas}/download', DownloadBerkasRegulasi::class)->whereUuid('regulasi')->whereUuid('berkas')->name('regulasi.berkas.download');
    Route::get('/regulasi/{regulasi}', ShowRegulasi::class)->whereUuid('regulasi')->name('regulasi.show');

    // Master Renstra
    Route::post('/renstra/{renstra}/aktifkan', [ChangeRenstraStatusController::class, 'activate'])->whereUuid('renstra')->name('renstra.activate');
    Route::post('/renstra/{renstra}/nonaktifkan', [ChangeRenstraStatusController::class, 'deactivate'])->whereUuid('renstra')->name('renstra.deactivate');
    Route::post('/renstra/{renstra}/arsipkan', [ChangeRenstraStatusController::class, 'archive'])->whereUuid('renstra')->name('renstra.archive');
    Route::get('/renstra', IndexRenstra::class)->name('renstra.index');
    Route::get('/renstra/create', CreateRenstra::class)->name('renstra.create');
    Route::post('/renstra', StoreRenstra::class)->name('renstra.store');
    Route::get('/renstra/{renstra}/edit', EditRenstra::class)->whereUuid('renstra')->name('renstra.edit');
    Route::put('/renstra/{renstra}', UpdateRenstra::class)->whereUuid('renstra')->name('renstra.update');
    Route::delete('/renstra/{renstra}', DestroyRenstra::class)->whereUuid('renstra')->name('renstra.destroy');
    Route::delete('/renstra/{renstra}/berkas/{berkas}', DestroyBerkasRenstra::class)->whereUuid('renstra')->whereUuid('berkas')->name('renstra.berkas.destroy');
    Route::get('/renstra/{renstra}/berkas/{berkas}/download', DownloadBerkasRenstra::class)->whereUuid('renstra')->whereUuid('berkas')->name('renstra.berkas.download');
    Route::get('/renstra/{renstra}', ShowRenstra::class)->whereUuid('renstra')->name('renstra.show');

    // Rencana Aksi - Pemenuhan Bukti Dukung (ISS-05.02)
    Route::get('/rencana-aksi/{rencana_aksi}/bukti', [RencanaAksiEvidenceController::class, 'index'])->whereUuid('rencana_aksi')->name('rencana-aksi.bukti.index');
    Route::post('/rencana-aksi/{rencana_aksi}/bukti', [RencanaAksiEvidenceController::class, 'store'])->whereUuid('rencana_aksi')->name('rencana-aksi.bukti.store');
    Route::delete('/rencana-aksi/{rencana_aksi}/bukti/{bukti}', [RencanaAksiEvidenceController::class, 'destroy'])->whereUuid('rencana_aksi')->whereUuid('bukti')->name('rencana-aksi.bukti.destroy');
    Route::get('/rencana-aksi/{rencana_aksi}/bukti/{bukti}/unduh', [DownloadBuktiRencanaAksiController::class, '__invoke'])->whereUuid('rencana_aksi')->whereUuid('bukti')->name('rencana-aksi.bukti.download');

    // Pengukuran Kinerja
    Route::get('/pengukuran', IndexPengukuran::class)->name('pengukuran.index');
    Route::get('/pengukuran/{id}/edit', EditPengukuran::class)->whereUuid('id')->name('pengukuran.edit');
    Route::post('/pengukuran/{id}', UpdatePengukuran::class)->whereUuid('id')->name('pengukuran.update');
    Route::post('/pengukuran/{id}/pratinjau', PreviewPengukuran::class)->whereUuid('id')->name('pengukuran.preview');
    Route::get('/pengukuran/{id}/bukti/{buktiId}', [DownloadBuktiPengukuran::class, '__invoke'])->whereUuid('id')->whereUuid('buktiId')->name('pengukuran.bukti');
    Route::get('/pengukuran/{id}/bukti-klaim/{buktiId}', DownloadBuktiKlaimPengukuran::class)->whereUuid('id')->whereUuid('buktiId')->name('pengukuran.bukti-klaim');

    // Verifikasi & Pengesahan Kinerja
    Route::get('/verifikasi', IndexVerifikasi::class)->name('verifikasi.index');
    Route::get('/verifikasi/{id}', ShowVerifikasi::class)->whereUuid('id')->name('verifikasi.show');
    Route::post('/verifikasi/{id}/verifikasi', [VerifyPengukuran::class, '__invoke'])->whereUuid('id')->name('verifikasi.verify');
    Route::post('/verifikasi/{id}/kembalikan', KembalikanPengukuran::class)->whereUuid('id')->name('verifikasi.kembalikan');
    Route::post('/verifikasi/{id}/sahkan', SahkanPengukuran::class)->whereUuid('id')->name('verifikasi.sahkan');
    Route::get('/pengaturan', IndexPengaturan::class)->name('pengaturan.index');
    Route::put('/pengaturan', UpdatePengaturan::class)->name('pengaturan.update');

    // Master Unit Organisasi
    Route::get('/unit', IndexUnit::class)->name('unit.index');
    Route::post('/unit', StoreUnit::class)->name('unit.store');
    Route::post('/unit/{id}', UpdateUnit::class)->whereUuid('id')->name('unit.update');
    Route::delete('/unit/{id}', DestroyUnit::class)->whereUuid('id')->name('unit.destroy');

    // Manajemen Hak Akses: Grant Izin per Unit
    Route::get('/akses/grant', IndexGrant::class)->name('akses.grant.index');
    Route::get('/akses/grant/opsi/pengguna', SearchGrantUsers::class)->name('akses.grant.users');
    Route::post('/akses/grant', StoreGrant::class)->name('akses.grant.store');
    Route::delete('/akses/grant/{id}', RevokeGrant::class)->whereUuid('id')->name('akses.grant.destroy');

    // Konfigurasi Persyaratan Jenis Berkas (Alur Tim Perencanaan)
    Route::get('/jenis-berkas', [JenisBerkasController::class, 'index'])->name('jenis-berkas.index');
    Route::post('/jenis-berkas', [JenisBerkasController::class, 'store'])->name('jenis-berkas.store');
    Route::put('/jenis-berkas/{id}', [JenisBerkasController::class, 'update'])->whereUuid('id')->name('jenis-berkas.update');
    Route::patch('/jenis-berkas/{id}/batas-teknis', [JenisBerkasController::class, 'updateBatasTeknis'])->whereUuid('id')->name('jenis-berkas.update-batas-teknis');
    Route::delete('/jenis-berkas/{id}', [JenisBerkasController::class, 'destroy'])->whereUuid('id')->name('jenis-berkas.destroy');

    // Kebijakan Storage & Saklar Unggah Berkas
    Route::get('/pengaturan/storage', [StoragePolicyController::class, 'index'])->name('pengaturan.storage.index');
    Route::put('/pengaturan/storage', [StoragePolicyController::class, 'update'])->name('pengaturan.storage.update');

    // Sasaran Strategis & Indikator Kinerja (ISS-02.04)
    Route::get('/perencanaan/sasaran-indikator', IndexSasaranIndikator::class)->name('perencanaan.sasaran-indikator.index');
    Route::post('/perencanaan/sasaran', StoreSasaran::class)->name('perencanaan.sasaran.store');
    Route::put('/perencanaan/sasaran/{sasaran}', UpdateSasaran::class)->whereUuid('sasaran')->name('perencanaan.sasaran.update');
    Route::delete('/perencanaan/sasaran/{sasaran}', DestroySasaran::class)->whereUuid('sasaran')->name('perencanaan.sasaran.destroy');

    Route::post('/perencanaan/indikator', StoreIndikator::class)->name('perencanaan.indikator.store');
    Route::put('/perencanaan/indikator/{indikator}', UpdateIndikator::class)->whereUuid('indikator')->name('perencanaan.indikator.update');
    Route::patch('/perencanaan/indikator/{indikator}/pindah-unit', PindahUnitIndikator::class)->whereUuid('indikator')->name('perencanaan.indikator.pindah-unit');
    Route::get('/perencanaan/indikator/{indikator}/editor', ShowIndicatorEditor::class)->whereUuid('indikator')->name('perencanaan.indikator.editor');
    Route::post('/perencanaan/indikator/{indikator}/komponen/preview', PreviewIndicatorFormula::class)->whereUuid('indikator')->name('perencanaan.indikator.preview');
    Route::patch('/perencanaan/indikator/{indikator}/formula', ChangeIndicatorFormula::class)->whereUuid('indikator')->name('perencanaan.indikator.formula');
    Route::delete('/perencanaan/indikator/{indikator}', DestroyIndikator::class)->whereUuid('indikator')->name('perencanaan.indikator.destroy');

    // Konfigurasi Komponen Indikator Kinerja (Data-Driven)
    Route::get('/indikator/{indikator}/komponen', [IndikatorKomponenController::class, 'index'])->whereUuid('indikator')->name('indikator.komponen.index');

    // Perjanjian Kinerja (PK) & Lampiran Legal
    Route::get('/perjanjian-kinerja', [PerjanjianKinerjaController::class, 'index'])->name('perjanjian-kinerja.index');
    Route::get('/perjanjian-kinerja/create', [PerjanjianKinerjaController::class, 'create'])->name('perjanjian-kinerja.create');
    Route::post('/perjanjian-kinerja', [PerjanjianKinerjaController::class, 'store'])->name('perjanjian-kinerja.store');
    Route::get('/perjanjian-kinerja/{perjanjian_kinerja}', [PerjanjianKinerjaController::class, 'show'])->whereUuid('perjanjian_kinerja')->name('perjanjian-kinerja.show');
    Route::get('/perjanjian-kinerja/{perjanjian_kinerja}/edit', [PerjanjianKinerjaController::class, 'edit'])->whereUuid('perjanjian_kinerja')->name('perjanjian-kinerja.edit');
    Route::put('/perjanjian-kinerja/{perjanjian_kinerja}', [PerjanjianKinerjaController::class, 'update'])->whereUuid('perjanjian_kinerja')->name('perjanjian-kinerja.update');
    Route::delete('/perjanjian-kinerja/{perjanjian_kinerja}/berkas/{berkas}', [PerjanjianKinerjaController::class, 'destroyBerkas'])->whereUuid('perjanjian_kinerja')->whereUuid('berkas')->name('perjanjian-kinerja.berkas.destroy');
    Route::get('/perjanjian-kinerja/{perjanjian_kinerja}/berkas/{berkas}/unduh', [PerjanjianKinerjaController::class, 'downloadBerkas'])->whereUuid('perjanjian_kinerja')->whereUuid('berkas')->name('perjanjian-kinerja.berkas.download');

    // Rencana Aksi — penyusunan target per periode
    Route::get('/rencana-aksi', DaftarRencanaAksi::class)->name('rencana-aksi.index');
    Route::get('/rencana-aksi/{rencanaAksi}', ShowRencanaAksi::class)->whereUuid('rencanaAksi')->name('rencana-aksi.show');
    // Tulis RA menambah audit append-only (matriks penuh); batasi per pengguna.
    // Pratinjau tidak menulis audit dan dipanggil tiap ketikan, jadi dikecualikan.
    Route::middleware('throttle:30,1,rencana-aksi-tulis')->group(function (): void {
        Route::post('/rencana-aksi/ensure-draft', StoreRencanaAksiDraft::class)->name('rencana-aksi.ensure-draft');
        Route::post('/rencana-aksi/{rencanaAksi}/target', UpdateRencanaAksiTarget::class)->whereUuid('rencanaAksi')->name('rencana-aksi.target.update');
    });
    Route::post('/rencana-aksi/{rencanaAksi}/preview', PreviewRencanaAksiTarget::class)->whereUuid('rencanaAksi')->name('rencana-aksi.preview');
});
