<?php

use App\Actions\Audit\WriteAuditLog;
use App\Actions\Regulasi\CreateRegulasiAction;
use App\Actions\Regulasi\DeleteRegulasiAction;
use App\Actions\Regulasi\DeleteRegulasiAttachmentAction;
use App\Actions\Regulasi\UpdateRegulasiAction;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegulasiPestTestCase extends TestCase
{
    public User $perencanaan;

    public User $pembaca;
}

uses(RegulasiPestTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RegulasiPermissionSeeder::class);
    $this->perencanaan = userDenganRole('perencanaan', 'perencanaan-regulasi@example.test');
    $this->pembaca = userDenganRole('pegawai', 'pembaca-regulasi@example.test');
});

test('create regulasi ditolak mencatat satu audit aman dengan dasar izin awal', function (bool $explicitDeny, mixed $reason, ?string $expectedReason): void {
    $actor = $explicitDeny ? $this->perencanaan : $this->pembaca;
    if ($explicitDeny) {
        tolakIzin($actor, PermissionCodes::REGULASI_CREATE);
    }
    $decision = app(PermissionResolver::class)->resolve($actor, PermissionCodes::REGULASI_CREATE);
    $auditCount = AuditLog::count();

    $this->actingAs($actor)->post('/regulasi', [
        'jenis' => 'kepmen', 'nomor' => 'CREATE-DITOLAK', 'tahun' => 2026,
        'tentang' => 'Regulasi yang tidak boleh tersimpan', 'aktif' => true,
        'alasan' => $reason,
        'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Isi lampiran tidak boleh masuk audit penolakan']],
    ])->assertForbidden();

    $audit = AuditLog::where('tindakan', 'regulasi.buat_ditolak')->sole();
    expect($audit->actor_id)->toBe($actor->id)
        ->and($audit->actor_type)->toBe('user')
        ->and($audit->sumber)->toBe('manual')
        ->and($audit->objek_tipe)->toBe('regulasi')
        ->and(Str::isUuid($audit->objek_id))->toBeTrue()
        ->and($audit->dasar_izin)->toEqual($decision->toAuditBasis())
        ->and($audit->alasan)->toBe($expectedReason ?? 'Pembuatan regulasi ditolak karena izin efektif tidak mengizinkan tindakan ini.')
        ->and(mb_check_encoding($audit->alasan, 'UTF-8'))->toBeTrue()
        ->and($audit->nilai_lama)->toBeNull()
        ->and($audit->nilai_baru)->toBeNull()
        ->and(AuditLog::count())->toBe($auditCount + 1);
    $this->assertDatabaseCount('regulasi', 0);
    $this->assertDatabaseCount('berkas', 0);
})->with([
    'tanpa allow dan alasan bukan teks' => [false, ['nilai' => 'bukan teks'], null],
    'explicit deny dan karakter kontrol' => [true, "\x01\x00", null],
    'explicit deny dan alasan multibyte panjang' => [true, "\x01 ".str_repeat('é', 1200)." \x01", str_repeat('é', 1000)],
]);

test('akses halaman create regulasi yang ditolak tidak mencatat audit mutasi', function (): void {
    $auditCount = AuditLog::count();

    $this->actingAs($this->pembaca)->get('/regulasi/create')->assertForbidden();

    $this->assertDatabaseCount('audit_log', $auditCount);
});

test('create regulasi menyimpan tiga mode lampiran dan audit', function (): void {
    Storage::fake('local');
    tolakIzin($this->perencanaan, PermissionCodes::BERKAS_UPLOAD);

    $response = $this->actingAs($this->perencanaan)->post('/regulasi', [
        'jenis' => 'kepmen',
        'nomor' => '358/M/KEP/2025',
        'tahun' => 2025,
        'tentang' => 'Indikator Kinerja Utama Perguruan Tinggi dan LLDIKTI',
        'tanggal' => '2025-08-01',
        'tautan_sumber' => 'https://jdih.example.test/kepmen-358',
        'catatan' => 'Dokumen sumber untuk pengujian.',
        'aktif' => true,
        'lampiran' => [
            [
                'mode' => 'file',
                'file' => UploadedFile::fake()->create('kepmen-358.pdf', 250, 'application/pdf'),
            ],
            [
                'mode' => 'tautan',
                'tautan' => 'https://jdih.example.test/kepmen-358/dokumen',
            ],
            [
                'mode' => 'teks',
                'isi_teks' => 'Salinan fisik tersedia pada arsip Tim Perencanaan.',
            ],
        ],
    ]);

    $response->assertRedirect(route('regulasi.index'));

    $regulasi = Regulasi::query()->where('nomor', '358/M/KEP/2025')->firstOrFail();
    expect($regulasi->berkas)->toHaveCount(3);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => $regulasi->getMorphClass(),
        'berkasable_id' => $regulasi->id,
        'jenis_berkas_id' => null,
        'mode' => 'file',
    ]);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => $regulasi->getMorphClass(),
        'berkasable_id' => $regulasi->id,
        'mode' => 'tautan',
    ]);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => $regulasi->getMorphClass(),
        'berkasable_id' => $regulasi->id,
        'mode' => 'teks',
    ]);
    Storage::disk('local')->assertExists($regulasi->berkas->firstWhere('mode', 'file')->path);

    $audit = AuditLog::query()
        ->where('tindakan', 'regulasi.buat')
        ->where('objek_id', $regulasi->id)
        ->firstOrFail();

    expect($audit->dasar_izin['keputusan'])->toBe('diizinkan')
        ->and($audit->dasar_izin['permission'])->toBe(PermissionCodes::REGULASI_CREATE);

    $auditTeks = AuditLog::query()
        ->where('tindakan', 'berkas.unggah')
        ->get()
        ->first(fn (AuditLog $item): bool => ($item->nilai_baru['mode'] ?? null) === 'teks');

    expect($auditTeks)->not->toBeNull()
        ->and($auditTeks->nilai_baru)->toHaveKey('panjang_teks')
        ->and($auditTeks->nilai_baru)->not->toHaveKey('isi_teks');
});

test('validasi lampiran mengabaikan nilai dari mode yang tidak aktif', function (): void {
    $response = $this->actingAs($this->perencanaan)->post('/regulasi', [
        'jenis' => 'kepmen',
        'nomor' => 'LAMPIRAN-MODE-2026',
        'tahun' => 2026,
        'tentang' => 'Regulasi dengan nilai lampiran mode lama.',
        'aktif' => true,
        'lampiran' => [[
            'mode' => 'teks',
            'tautan' => 'bukan-url-yang-valid',
            'isi_teks' => 'Keterangan yang tetap dipakai.',
        ]],
    ]);

    $response->assertRedirect(route('regulasi.index'));
    $this->assertDatabaseHas('berkas', ['mode' => 'teks', 'isi_teks' => 'Keterangan yang tetap dipakai.']);
});

test('kombinasi jenis nomor tahun duplikat ditolak', function (): void {
    Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => '10/2026',
        'tahun' => 2026,
        'tentang' => 'Regulasi awal',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);

    $response = $this->actingAs($this->perencanaan)->post('/regulasi', [
        'jenis' => 'permen',
        'nomor' => '10/2026',
        'tahun' => 2026,
        'tentang' => 'Duplikat regulasi',
        'aktif' => true,
    ]);

    $response->assertSessionHasErrors('nomor');
    $this->assertDatabaseCount('regulasi', 1);
});

test('konflik unique dari action diterjemahkan menjadi error validasi nomor', function (): void {
    $data = [
        'jenis' => 'perpres',
        'nomor' => 'RACE-UNIQUE-2026',
        'tahun' => 2026,
        'tentang' => 'Dokumen awal untuk menguji konflik unique di database.',
        'aktif' => true,
    ];
    $action = app(CreateRegulasiAction::class);

    $action->handle($this->perencanaan, $data);

    $exception = null;

    try {
        $action->handle($this->perencanaan, [
            ...$data,
            'tentang' => 'Dokumen kedua yang tiba setelah validasi awal berhasil.',
        ]);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors())->toHaveKey('nomor');
    $this->assertDatabaseCount('regulasi', 1);
});

test('action menghentikan semua mutasi saat resolver izin menolak', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Lampiran yang tidak boleh berubah setelah izin dicabut.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_UPDATE);
    expect(fn () => app(UpdateRegulasiAction::class)->handle($this->perencanaan, $regulasi, [
        'jenis' => $regulasi->jenis,
        'nomor' => $regulasi->nomor,
        'tahun' => $regulasi->tahun,
        'tentang' => 'Perubahan yang tidak boleh tersimpan.',
        'aktif' => true,
        'versi' => $regulasi->versi,
        'alasan' => 'Izin dicabut tepat sebelum mutasi memulai perubahan.',
    ]))->toThrow(AuthorizationException::class);
    $this->assertDatabaseMissing('regulasi', [
        'id' => $regulasi->id,
        'tentang' => 'Perubahan yang tidak boleh tersimpan.',
    ]);

    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_DELETE);
    expect(fn () => app(DeleteRegulasiAction::class)->handle(
        $this->perencanaan,
        $regulasi,
        'Izin penghapusan dicabut sebelum mutasi memulai transaksi.',
    ))->toThrow(AuthorizationException::class);
    $this->assertDatabaseHas('regulasi', ['id' => $regulasi->id]);

    tolakIzin($this->perencanaan, PermissionCodes::BERKAS_DELETE);
    expect(fn () => app(DeleteRegulasiAttachmentAction::class)->handle(
        $this->perencanaan,
        $regulasi,
        $berkas,
        'Izin penghapusan lampiran dicabut sebelum mutasi memulai transaksi.',
    ))->toThrow(AuthorizationException::class);
    expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeFalse();

    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_CREATE);
    expect(fn () => app(CreateRegulasiAction::class)->handle($this->perencanaan, [
        'jenis' => 'keputusan_lainnya',
        'nomor' => 'CREATE-DENIED-2026',
        'tahun' => 2026,
        'tentang' => 'Regulasi ini tidak boleh dibuat setelah izin dicabut.',
        'aktif' => true,
    ]))->toThrow(AuthorizationException::class);
    $this->assertDatabaseMissing('regulasi', ['nomor' => 'CREATE-DENIED-2026']);

    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'regulasi.ubah_ditolak',
        'objek_id' => $regulasi->id,
    ]);
    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'regulasi.hapus_ditolak',
        'objek_id' => $regulasi->id,
    ]);
    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'berkas.hapus_ditolak',
        'objek_id' => $berkas->id,
    ]);
});

test('update regulasi mencatat nilai dan dasar izin audit', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);

    $response = $this->actingAs($this->perencanaan)->put("/regulasi/{$regulasi->id}", [
        'jenis' => $regulasi->jenis,
        'nomor' => $regulasi->nomor,
        'tahun' => $regulasi->tahun,
        'tentang' => 'Indikator Kinerja Utama hasil pemutakhiran',
        'aktif' => true,
        'versi' => $regulasi->versi,
        'alasan' => 'Menyesuaikan judul dengan dokumen sumber terbaru.',
    ]);

    $response->assertRedirect(route('regulasi.index'));
    $this->assertDatabaseHas('regulasi', [
        'id' => $regulasi->id,
        'tentang' => 'Indikator Kinerja Utama hasil pemutakhiran',
    ]);

    $audit = AuditLog::query()
        ->where('tindakan', 'regulasi.ubah')
        ->where('objek_id', $regulasi->id)
        ->firstOrFail();

    expect($audit->nilai_lama['tentang'])->toBe('Indikator Kinerja Utama')
        ->and($audit->nilai_baru['tentang'])->toBe('Indikator Kinerja Utama hasil pemutakhiran')
        ->and($audit->alasan)->toBe('Menyesuaikan judul dengan dokumen sumber terbaru.')
        ->and($audit->dasar_izin['keputusan'])->toBe('diizinkan')
        ->and($audit->dasar_izin['permission'])->toBe(PermissionCodes::REGULASI_UPDATE);
});

test('update regulasi menolak versi usang dan mengaudit kondisi terbaru', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $versiAwal = $regulasi->versi;

    $this->actingAs($this->perencanaan)->put("/regulasi/{$regulasi->id}", [
        'jenis' => $regulasi->jenis,
        'nomor' => $regulasi->nomor,
        'tahun' => $regulasi->tahun,
        'tentang' => 'Perubahan pertama yang sah',
        'aktif' => true,
        'versi' => $versiAwal,
        'alasan' => 'Menyelaraskan judul dengan naskah regulasi terbaru.',
    ])->assertRedirect(route('regulasi.index'));

    $this->actingAs($this->perencanaan)->put("/regulasi/{$regulasi->id}", [
        'jenis' => $regulasi->jenis,
        'nomor' => $regulasi->nomor,
        'tahun' => $regulasi->tahun,
        'tentang' => 'Perubahan kedua dengan data lama',
        'aktif' => true,
        'versi' => $versiAwal,
        'alasan' => 'Mencoba menyimpan formulir yang dibuka sebelum perubahan pertama.',
    ])->assertStatus(409);

    $regulasi->refresh();
    expect($regulasi->tentang)->toBe('Perubahan pertama yang sah')
        ->and($regulasi->versi)->toBe($versiAwal + 1);

    $audit = AuditLog::query()
        ->where('tindakan', 'regulasi.ubah_ditolak')
        ->where('objek_id', $regulasi->id)
        ->firstOrFail();

    expect($audit->nilai_lama['tentang'])->toBe('Perubahan pertama yang sah')
        ->and($audit->nilai_baru['alasan_penolakan'])->toBe('versi_usang')
        ->and($audit->nilai_baru['versi_dikirim'])->toBe($versiAwal)
        ->and($audit->nilai_baru['versi_saat_ini'])->toBe($versiAwal + 1);
});

test('delete regulasi tanpa rujukan aktif berhasil dan diaudit', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Lampiran yang ikut dihapus bersama regulasi.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $response = $this->actingAs($this->perencanaan)->delete("/regulasi/{$regulasi->id}", [
        'alasan' => 'Regulasi dinyatakan tidak berlaku dan tidak lagi dirujuk.',
    ]);

    $response->assertRedirect(route('regulasi.index'));
    $this->assertDatabaseMissing('regulasi', ['id' => $regulasi->id]);

    $audit = AuditLog::query()
        ->where('tindakan', 'regulasi.hapus')
        ->where('objek_id', $regulasi->id)
        ->firstOrFail();

    expect($audit->alasan)->toBe('Regulasi dinyatakan tidak berlaku dan tidak lagi dirujuk.')
        ->and($audit->dasar_izin['keputusan'])->toBe('diizinkan')
        ->and($audit->dasar_izin['permission'])->toBe(PermissionCodes::REGULASI_DELETE);

    $auditBerkas = AuditLog::query()
        ->where('tindakan', 'berkas.hapus')
        ->where('objek_tipe', 'berkas')
        ->where('objek_id', $berkas->id)
        ->firstOrFail();

    expect($auditBerkas->nilai_lama['id'])->toBe($berkas->id)
        ->and($auditBerkas->alasan)->toBe('Regulasi dinyatakan tidak berlaku dan tidak lagi dirujuk.')
        ->and($auditBerkas->dasar_izin['permission'])->toBe(PermissionCodes::REGULASI_DELETE);
});

test('delete ditolak saat regulasi dirujuk data aktif', function (bool $renstraAktif): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $renstra = Renstra::query()->create([
        'regulasi_id' => $regulasi->id,
        'kode' => 'RENSTRA-REGULASI',
        'created_by' => $this->perencanaan->id,
        'nama' => 'Renstra dengan regulasi aktif',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'is_aktif' => $renstraAktif,
    ]);
    $sasaran = SasaranStrategis::query()->create([
        'renstra_id' => $renstra->id,
        'kode' => 'SS-REGULASI',
        'deskripsi' => 'Sasaran uji delete guard',
    ]);
    IndikatorKinerja::query()->create([
        'sasaran_strategis_id' => $sasaran->id,
        'regulasi_id' => $regulasi->id,
        'unit_id' => Unit::query()->create([
            'nama' => 'Unit regulasi pengujian',
            'created_by' => $this->perencanaan->id,
        ])->id,
        'kode' => 'IKU-REGULASI',
        'nama' => 'Indikator uji regulasi',
        'satuan' => '%',
        'is_aktif' => ! $renstraAktif,
    ]);

    DB::enableQueryLog();

    $response = $this->actingAs($this->perencanaan)->delete("/regulasi/{$regulasi->id}", [
        'alasan' => 'Menghapus regulasi yang sudah tidak digunakan.',
    ]);

    $response->assertSessionHasErrors('regulasi');
    $this->assertDatabaseHas('regulasi', ['id' => $regulasi->id]);
    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'regulasi.hapus_ditolak',
        'objek_id' => $regulasi->id,
    ]);

    $lockQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains(strtolower($query), 'for update'));

    expect($lockQueries)->not->toBeEmpty();
    DB::disableQueryLog();
})->with(['rujukan renstra' => true, 'rujukan indikator' => false]);

test('explicit deny menang dan dasar izin penolakan diaudit', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $permission = Permission::query()->where('kode', PermissionCodes::REGULASI_UPDATE)->firstOrFail();

    UserPermissionDenial::query()->create([
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'unit_id' => null,
        'alasan' => 'Akses perubahan dicabut selama reviu kepatuhan.',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $response = $this->actingAs($this->perencanaan)->put("/regulasi/{$regulasi->id}", [
        'jenis' => $regulasi->jenis,
        'nomor' => $regulasi->nomor,
        'tahun' => $regulasi->tahun,
        'tentang' => 'Perubahan yang harus ditolak',
        'aktif' => true,
        'versi' => $regulasi->versi,
        'alasan' => 'Memperbarui judul berdasarkan dokumen terbaru.',
    ]);

    $response->assertForbidden();
    $regulasi->refresh();
    expect($regulasi->tentang)->not->toBe('Perubahan yang harus ditolak');

    $audit = AuditLog::query()
        ->where('tindakan', 'regulasi.ubah_ditolak')
        ->where('objek_id', $regulasi->id)
        ->firstOrFail();

    expect($audit->dasar_izin['keputusan'])->toBe('ditolak')
        ->and($audit->dasar_izin['alasan'])->toBe('explicit_deny')
        ->and($audit->dasar_izin['permission'])->toBe(PermissionCodes::REGULASI_UPDATE);
});

test('pengguna read only mendapat 403 untuk create update dan delete', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);

    $this->actingAs($this->pembaca)
        ->post('/regulasi', [
            'jenis' => 'permen',
            'nomor' => '1/2026',
            'tahun' => 2026,
            'tentang' => 'Percobaan pembuatan tanpa izin',
            'aktif' => true,
        ])
        ->assertForbidden();

    $this->actingAs($this->pembaca)
        ->put("/regulasi/{$regulasi->id}", [
            'jenis' => $regulasi->jenis,
            'nomor' => $regulasi->nomor,
            'tahun' => $regulasi->tahun,
            'tentang' => 'Perubahan tanpa izin',
            'aktif' => true,
            'versi' => $regulasi->versi,
            'alasan' => 'Percobaan perubahan dari pengguna read only.',
        ])
        ->assertForbidden();

    $this->actingAs($this->pembaca)
        ->delete("/regulasi/{$regulasi->id}", [
            'alasan' => 'Percobaan penghapusan dari pengguna read only.',
        ])
        ->assertForbidden();

    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Lampiran yang digunakan untuk menguji larangan penghapusan.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->pembaca)
        ->delete("/regulasi/{$regulasi->id}/berkas/{$berkas->id}", [
            'alasan' => 'Percobaan menghapus lampiran dari pengguna read only.',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('regulasi', ['id' => $regulasi->id]);
    expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeFalse();
});

test('pengguna read only dapat melihat detail regulasi beserta lampiran', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Dokumen sumber tersedia pada arsip Tim Perencanaan.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->pembaca)
        ->get("/regulasi/{$regulasi->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Regulasi/Show')
            ->where('regulasi.id', $regulasi->id)
            ->has('regulasi.berkas', 1)
            ->where('regulasi.berkas.0.id', $berkas->id)
            ->where('regulasi.berkas.0.isi_teks', 'Dokumen sumber tersedia pada arsip Tim Perencanaan.')
        );
});

test('penolakan hapus lampiran diaudit terhadap lampiran yang dituju', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Lampiran yang tidak boleh dihapus oleh pengguna read only.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->pembaca)
        ->delete("/regulasi/{$regulasi->id}/berkas/{$berkas->id}", [
            'alasan' => 'Percobaan penghapusan lampiran dari pengguna read only.',
        ])
        ->assertForbidden();

    $audit = AuditLog::query()
        ->where('tindakan', 'berkas.hapus_ditolak')
        ->where('objek_tipe', 'berkas')
        ->where('objek_id', $berkas->id)
        ->firstOrFail();

    expect($audit->nilai_lama['id'])->toBe($berkas->id)
        ->and($audit->dasar_izin['keputusan'])->toBe('ditolak')
        ->and($audit->dasar_izin['permission'])->toBe(PermissionCodes::BERKAS_DELETE);
});

test('pengguna tanpa peran tidak menerima permission regulasi', function (): void {
    $tanpaPeran = User::factory()->create(['status' => 'aktif']);

    $this->actingAs($tanpaPeran)
        ->get('/regulasi')
        ->assertForbidden();
});

test('download file privat memerlukan permission read', function (): void {
    Storage::fake('local');
    $regulasi = buatRegulasi($this->perencanaan);
    $path = "berkas/regulasi/{$regulasi->id}/aturan.pdf";
    Storage::disk('local')->put($path, 'isi-pdf-uji');
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'file',
        'nama_asli' => 'aturan.pdf',
        'path' => $path,
        'mime' => 'application/pdf',
        'ukuran_bytes' => 12,
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $this->get("/regulasi/{$regulasi->id}/berkas/{$berkas->id}/download")
        ->assertRedirect('/login');

    $this->actingAs($this->pembaca)
        ->get("/regulasi/{$regulasi->id}/berkas/{$berkas->id}/download")
        ->assertOk()
        ->assertDownload('aturan.pdf');
});

test('hapus lampiran menggunakan permission berkas dan mencatat audit', function (): void {
    Storage::fake('local');
    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_DELETE);
    $regulasi = buatRegulasi($this->perencanaan);
    $path = "berkas/regulasi/{$regulasi->id}/aturan.pdf";
    Storage::disk('local')->put($path, 'isi-pdf-uji');
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'file',
        'nama_asli' => 'aturan.pdf',
        'path' => $path,
        'mime' => 'application/pdf',
        'ukuran_bytes' => 12,
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->delete("/regulasi/{$regulasi->id}/berkas/{$berkas->id}", [
            'alasan' => 'Lampiran duplikat digantikan oleh salinan yang lebih lengkap.',
        ]);

    $response->assertRedirect();
    $dihapus = Berkas::withTrashed()->findOrFail($berkas->id);
    expect($dihapus->trashed())->toBeTrue()
        ->and($dihapus->dihapus_oleh)->toBe($this->perencanaan->id);

    $audit = AuditLog::query()
        ->where('tindakan', 'berkas.hapus')
        ->where('objek_id', $berkas->id)
        ->firstOrFail();

    expect($audit->dasar_izin['keputusan'])->toBe('diizinkan')
        ->and($audit->dasar_izin['permission'])->toBe(PermissionCodes::BERKAS_DELETE);
});

test('hapus lampiran ditolak saat regulasi dirujuk data aktif', function (bool $renstraAktif): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $renstra = Renstra::query()->create([
        'regulasi_id' => $regulasi->id,
        'kode' => 'RENSTRA-LAMPIRAN',
        'created_by' => $this->perencanaan->id,
        'nama' => 'Renstra dengan regulasi aktif',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'is_aktif' => $renstraAktif,
    ]);
    if (! $renstraAktif) {
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-LAMPIRAN', 'deskripsi' => 'Sasaran fixture lampiran']);
        IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'regulasi_id' => $regulasi->id,
            'unit_id' => Unit::create(['nama' => 'Unit fixture lampiran', 'created_by' => $this->perencanaan->id])->id,
            'kode' => 'IK-LAMPIRAN', 'nama' => 'Indikator fixture lampiran', 'satuan' => '%', 'is_aktif' => true,
        ]);
    }
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Keterangan lampiran yang akan diuji guard penghapusannya.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->delete("/regulasi/{$regulasi->id}/berkas/{$berkas->id}", [
            'alasan' => 'Lampiran dianggap tidak lagi diperlukan pada regulasi ini.',
        ]);

    $response->assertSessionHasErrors('berkas');
    expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeFalse();
    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'berkas.hapus_ditolak',
        'objek_id' => $berkas->id,
    ]);
})->with(['rujukan renstra' => true, 'rujukan indikator' => false]);

test('penolakan regulasi dengan alasan kontrol tetap 403 dan mempertahankan dasar izin', function (string $operation, string $permission, string $event): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip awal', 'uploaded_by' => $this->perencanaan->id]);
    tolakIzin($this->perencanaan, $permission);
    $path = '/regulasi/'.$regulasi->id.($operation === 'attachment' ? '/berkas/'.$berkas->id : '');
    $response = $this->actingAs($this->perencanaan)->call($operation === 'update' ? 'PUT' : 'DELETE', $path, ['alasan' => str_repeat("\x01", 12)]);
    $response->assertForbidden();
    $audit = AuditLog::where('tindakan', $event)->sole();
    expect($audit->dasar_izin['keputusan'])->toBe('ditolak')
        ->and($audit->dasar_izin['permission'])->toBe($permission)
        ->and(trim($audit->alasan))->not->toBe('');
    expect($regulasi->fresh()->versi)->toBe(1);
    expect($berkas->fresh()->trashed())->toBeFalse();
})->with([
    ['update', PermissionCodes::REGULASI_UPDATE, 'regulasi.ubah_ditolak'],
    ['delete', PermissionCodes::REGULASI_DELETE, 'regulasi.hapus_ditolak'],
    ['attachment', PermissionCodes::BERKAS_DELETE, 'berkas.hapus_ditolak'],
]);

test('audit penolakan regulasi membatasi alasan multibyte sebelum validasi', function (string $operation, string $permission, string $event): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip awal', 'uploaded_by' => $this->perencanaan->id]);
    $regulasiBefore = $regulasi->fresh()->getAttributes();
    $berkasBefore = $berkas->fresh()->getAttributes();
    tolakIzin($this->perencanaan, $permission);
    $auditCount = AuditLog::count();
    $path = '/regulasi/'.$regulasi->id.($operation === 'attachment' ? '/berkas/'.$berkas->id : '');

    $this->actingAs($this->perencanaan)->call($operation === 'update' ? 'PUT' : 'DELETE', $path, [
        'alasan' => "\x01 ".str_repeat('é', 1200)." \x01",
    ])->assertForbidden();

    $audit = AuditLog::where('tindakan', $event)->sole();
    expect($audit->alasan)->toBe(str_repeat('é', 1000))
        ->and(mb_check_encoding($audit->alasan, 'UTF-8'))->toBeTrue()
        ->and($audit->dasar_izin['keputusan'])->toBe('ditolak')
        ->and($audit->dasar_izin['permission'])->toBe($permission)
        ->and(AuditLog::count())->toBe($auditCount + 1)
        ->and($regulasi->fresh()->getAttributes())->toBe($regulasiBefore)
        ->and($berkas->fresh()->getAttributes())->toBe($berkasBefore);
})->with([
    ['update', PermissionCodes::REGULASI_UPDATE, 'regulasi.ubah_ditolak'],
    ['delete', PermissionCodes::REGULASI_DELETE, 'regulasi.hapus_ditolak'],
    ['attachment', PermissionCodes::BERKAS_DELETE, 'berkas.hapus_ditolak'],
]);

test('mutasi regulasi memeriksa status aktor segar', function (string $operation): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip awal', 'uploaded_by' => $this->perencanaan->id]);
    User::whereKey($this->perencanaan->id)->update(['status' => 'nonaktif']);

    expect(fn () => mutasiRegulasiLangsung($operation, $this->perencanaan, $regulasi, $berkas))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('regulasi', 1);
    expect($regulasi->fresh()->versi)->toBe(1);
    expect($berkas->fresh()->trashed())->toBeFalse();
})->with(['create', 'update', 'delete', 'attachment']);

test('hapus lampiran menolak induk berbeda pada request dan batas mutasi', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $other = $regulasi->replicate();
    $other->nomor = 'INDUK-LAIN';
    $other->save();
    $berkas = $other->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip induk lain', 'uploaded_by' => $this->perencanaan->id]);

    $this->actingAs($this->perencanaan)->delete('/regulasi/'.$regulasi->id.'/berkas/'.$berkas->id, ['alasan' => 'Tidak boleh menghapus arsip induk lain'])->assertForbidden();
    expect(fn () => mutasiRegulasiLangsung('attachment', $this->perencanaan, $regulasi, $berkas))->toThrow(AuthorizationException::class);
    expect($berkas->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseMissing('audit_log', ['tindakan' => 'berkas.hapus', 'objek_id' => $berkas->id]);
});

test('kegagalan audit menggulung mutasi regulasi dan hanya menghapus unggahan baru', function (string $operation): void {
    Storage::fake('local');
    $regulasi = buatRegulasi($this->perencanaan);
    $path = 'berkas/regulasi/'.$regulasi->id.'/existing.pdf';
    Storage::disk('local')->put($path, 'Arsip existing');
    $berkas = $regulasi->berkas()->create(['mode' => 'file', 'path' => $path, 'nama_asli' => 'existing.pdf', 'uploaded_by' => $this->perencanaan->id]);
    $before = $regulasi->fresh()->getAttributes();
    $auditCount = AuditLog::count();
    $writer = app(WriteAuditLog::class);
    $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andReturnUsing(function (array $attributes) use ($writer): AuditLog {
        if ($attributes['tindakan'] !== 'berkas.unggah') {
            throw new RuntimeException('Kegagalan audit fixture');
        }

        return $writer->handle($attributes);
    });

    expect(fn () => mutasiRegulasiLangsung($operation, $this->perencanaan, $regulasi, $berkas, [
        'lampiran' => [['mode' => 'file', 'file' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')]],
    ]))->toThrow(RuntimeException::class, 'Kegagalan audit fixture');

    $this->assertDatabaseCount('regulasi', 1);
    $this->assertDatabaseCount('berkas', 1);
    $this->assertDatabaseCount('audit_log', $auditCount);
    expect($regulasi->fresh()->getAttributes())->toBe($before);
    expect($berkas->fresh()->trashed())->toBeFalse();
    expect(Storage::disk('local')->allFiles('berkas/regulasi'))->toBe([$path]);
})->with(['create', 'update', 'delete', 'attachment']);

test('kegagalan kompensasi unggahan dicatat aman tanpa menutupi error utama', function (bool $throws): void {
    $disk = Storage::fake('local');
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip awal', 'uploaded_by' => $this->perencanaan->id]);
    $failingDisk = Mockery::mock($disk)->makePartial();
    $delete = $failingDisk->shouldReceive('delete')->once();
    if ($throws) {
        $delete->andThrow(new RuntimeException('Pesan storage privat tidak boleh disalin'));
    } else {
        $delete->andReturn(false);
    }
    Storage::set('local', $failingDisk);
    Log::spy();
    $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('Kegagalan utama fixture'));

    expect(fn () => mutasiRegulasiLangsung('update', $this->perencanaan, $regulasi, $berkas, [
        'lampiran' => [['mode' => 'file', 'file' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')]],
    ]))->toThrow(RuntimeException::class, 'Kegagalan utama fixture');
    $this->assertDatabaseCount('berkas', 1);
    expect($regulasi->fresh()->versi)->toBe(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        return $context['operasi'] === 'regulasi.kompensasi_unggahan'
            && $context['jumlah_file'] === 1
            && array_intersect(['path', 'exception', 'message'], array_keys($context)) === [];
    });
})->with([false, true]);

test('update tanpa perubahan tetap menaikkan versi dan mencatat audit', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip awal', 'uploaded_by' => $this->perencanaan->id]);
    mutasiRegulasiLangsung('update', $this->perencanaan, $regulasi, $berkas);
    expect($regulasi->fresh()->versi)->toBe(2);
    expect(AuditLog::where('tindakan', 'regulasi.ubah')->sole()->nilai_baru['versi'])->toBe(2);
});

test('kegagalan penghapusan fisik sesudah commit tidak menggulung data', function (bool $throws): void {
    $disk = Storage::fake('local');
    $regulasi = buatRegulasi($this->perencanaan);
    $path = 'berkas/regulasi/'.$regulasi->id.'/existing.pdf';
    $disk->put($path, 'Arsip existing');
    $berkas = $regulasi->berkas()->create(['mode' => 'file', 'path' => $path, 'nama_asli' => 'existing.pdf', 'uploaded_by' => $this->perencanaan->id]);
    $failingDisk = Mockery::mock($disk)->makePartial();
    $delete = $failingDisk->shouldReceive('delete')->once()->with([$path]);
    if ($throws) {
        $delete->andThrow(new RuntimeException('Pesan storage privat tidak boleh disalin'));
    } else {
        $delete->andReturn(false);
    }
    Storage::set('local', $failingDisk);
    Log::spy();

    mutasiRegulasiLangsung('attachment', $this->perencanaan, $regulasi, $berkas);

    expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeTrue();
    expect(AuditLog::where('tindakan', 'berkas.hapus')->count())->toBe(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['operasi'] === 'regulasi.hapus_lampiran' && $context['jumlah_file'] === 1 && array_intersect(['path', 'exception', 'message'], array_keys($context)) === []);
})->with([false, true]);

test('gagal unggah kedua menggulung data dan file baru tanpa menghapus arsip existing', function (string $operation): void {
    Storage::fake('local');
    $regulasi = buatRegulasi($this->perencanaan);
    $path = 'berkas/regulasi/'.$regulasi->id.'/existing.pdf';
    Storage::disk('local')->put($path, 'Arsip existing');
    $berkas = $regulasi->berkas()->create(['mode' => 'file', 'path' => $path, 'nama_asli' => 'existing.pdf', 'uploaded_by' => $this->perencanaan->id]);
    $file = Mockery::mock(UploadedFile::fake()->create('gagal.pdf', 10, 'application/pdf'))->makePartial();
    $file->shouldReceive('store')->once()->andReturn(false);
    $auditCount = AuditLog::count();

    expect(fn () => mutasiRegulasiLangsung($operation, $this->perencanaan, $regulasi, $berkas, ['lampiran' => [
        ['mode' => 'file', 'file' => UploadedFile::fake()->create('pertama.pdf', 10, 'application/pdf')],
        ['mode' => 'file', 'file' => $file],
    ]]))->toThrow(RuntimeException::class, 'Lampiran gagal disimpan ke private storage.');

    $this->assertDatabaseCount('regulasi', 1);
    $this->assertDatabaseCount('berkas', 1);
    $this->assertDatabaseCount('audit_log', $auditCount);
    expect($regulasi->fresh()->versi)->toBe(1);
    expect(Storage::disk('local')->allFiles('berkas/regulasi'))->toBe([$path]);
})->with(['create', 'update']);

test('file existing baru dihapus sesudah audit mutasi berhasil', function (string $operation): void {
    Storage::fake('local');
    $regulasi = buatRegulasi($this->perencanaan);
    $path = 'berkas/regulasi/'.$regulasi->id.'/existing.pdf';
    Storage::disk('local')->put($path, 'Arsip existing');
    $berkas = $regulasi->berkas()->create(['mode' => 'file', 'path' => $path, 'nama_asli' => 'existing.pdf', 'uploaded_by' => $this->perencanaan->id]);
    $writer = app(WriteAuditLog::class);
    $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andReturnUsing(function (array $attributes) use ($writer, $path): AuditLog {
        Storage::disk('local')->assertExists($path);
        expect(DB::transactionLevel())->toBeGreaterThan(1);

        return $writer->handle($attributes);
    });

    mutasiRegulasiLangsung($operation, $this->perencanaan, $regulasi, $berkas);

    Storage::disk('local')->assertMissing($path);
    expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeTrue();
    expect(AuditLog::where('tindakan', 'berkas.hapus')->count())->toBe(1);
})->with(['delete', 'attachment']);

test('alasan mutasi yang habis setelah sanitasi tidak menyimpan perubahan parsial', function (string $operation): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Arsip awal', 'uploaded_by' => $this->perencanaan->id]);
    $auditCount = AuditLog::count();

    try {
        mutasiRegulasiLangsung($operation, $this->perencanaan, $regulasi, $berkas, ['alasan' => str_repeat("\x01", 12)]);
        test()->fail('Mutasi memerlukan alasan yang dapat dibaca.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('alasan');
    }

    expect($regulasi->fresh()->versi)->toBe(1);
    expect($berkas->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseCount('audit_log', $auditCount);
})->with(['update', 'delete', 'attachment']);

test('capability GET tidak mengaudit sementara GET edit ditolak tetap dicatat sekali', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $auditCount = AuditLog::count();
    $this->actingAs($this->pembaca)->get('/regulasi')->assertOk();
    $this->get('/regulasi/'.$regulasi->id)->assertOk();
    $this->assertDatabaseCount('audit_log', $auditCount);
    $this->get('/regulasi/'.$regulasi->id.'/edit')->assertForbidden();
    expect(AuditLog::where('tindakan', 'regulasi.ubah_ditolak')->sole()->dasar_izin['keputusan'])->toBe('ditolak');
});

/** Fixture langsung membuktikan invariant mutasi tanpa bergantung pada validasi HTTP. */
function mutasiRegulasiLangsung(string $operation, User $actor, Regulasi $regulasi, Berkas $berkas, array $overrides = []): mixed
{
    $data = ['jenis' => $regulasi->jenis, 'nomor' => $regulasi->nomor, 'tahun' => $regulasi->tahun, 'tentang' => $regulasi->tentang, 'aktif' => true, 'alasan' => 'Alasan fixture mutasi langsung', ...$overrides];

    return match ($operation) {
        'create' => app(CreateRegulasiAction::class)->handle($actor, [...$data, 'nomor' => 'REGULASI-BARU']),
        'update' => app(UpdateRegulasiAction::class)->handle($actor, $regulasi, [...$data, 'versi' => $regulasi->versi]),
        'delete' => app(DeleteRegulasiAction::class)->handle($actor, $regulasi, $data['alasan']),
        'attachment' => app(DeleteRegulasiAttachmentAction::class)->handle($actor, $regulasi, $berkas, $data['alasan']),
    };
}

function userDenganRole(string $roleName, string $email): User
{
    $role = Role::query()->where('kode', $roleName)->firstOrFail();
    $user = User::factory()->create(['email' => $email, 'status' => 'aktif']);
    $user->roles()->attach($role->id, [
        'id' => (string) Str::uuid(),
        'sumber_pemberian' => 'manual',
        'diberikan_oleh' => $user->id,
        'created_at' => now(),
    ]);

    return $user;
}

function buatRegulasi(User $pembuat): Regulasi
{
    return Regulasi::query()->create([
        'jenis' => 'kepmen',
        'nomor' => '358/M/KEP/2025',
        'tahun' => 2025,
        'tentang' => 'Indikator Kinerja Utama',
        'aktif' => true,
        'created_by' => $pembuat->id,
    ]);
}

function tolakIzin(User $user, string $permissionCode): void
{
    $permission = Permission::query()->where('kode', $permissionCode)->firstOrFail();

    UserPermissionDenial::query()->create([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'unit_id' => null,
        'alasan' => 'Izin dicabut untuk memastikan mutasi gagal tertutup.',
        'ditetapkan_oleh' => $user->id,
    ]);
}
