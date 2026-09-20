<?php

use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\RegulasiService;
use App\Support\PermissionCodes;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
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

test('create regulasi menyimpan tiga mode lampiran dan audit', function (): void {
    Storage::fake('local');

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
        'berkasable_type' => 'regulasi',
        'berkasable_id' => $regulasi->id,
        'jenis_berkas_id' => null,
        'mode' => 'file',
    ]);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => 'regulasi',
        'berkasable_id' => $regulasi->id,
        'mode' => 'tautan',
    ]);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => 'regulasi',
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

test('konflik unique dari service diterjemahkan menjadi error validasi nomor', function (): void {
    $data = [
        'jenis' => 'perpres',
        'nomor' => 'RACE-UNIQUE-2026',
        'tahun' => 2026,
        'tentang' => 'Dokumen awal untuk menguji konflik unique di database.',
        'aktif' => true,
    ];
    $service = app(RegulasiService::class);

    $service->create($data, $this->perencanaan);

    $exception = null;

    try {
        $service->create([
            ...$data,
            'tentang' => 'Dokumen kedua yang tiba setelah validasi awal berhasil.',
        ], $this->perencanaan);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors())->toHaveKey('nomor');
    $this->assertDatabaseCount('regulasi', 1);
});

test('service menghentikan semua mutasi saat resolver izin menolak', function (): void {
    $service = app(RegulasiService::class);
    $regulasi = buatRegulasi($this->perencanaan);
    $berkas = $regulasi->berkas()->create([
        'jenis_berkas_id' => null,
        'mode' => 'teks',
        'isi_teks' => 'Lampiran yang tidak boleh berubah setelah izin dicabut.',
        'uploaded_by' => $this->perencanaan->id,
    ]);

    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_UPDATE);
    expect(fn () => $service->update($regulasi, [
        'jenis' => $regulasi->jenis,
        'nomor' => $regulasi->nomor,
        'tahun' => $regulasi->tahun,
        'tentang' => 'Perubahan yang tidak boleh tersimpan.',
        'aktif' => true,
        'versi' => $regulasi->versi,
        'alasan' => 'Izin dicabut tepat sebelum service memulai perubahan.',
    ], $this->perencanaan))->toThrow(AuthorizationException::class);
    $this->assertDatabaseMissing('regulasi', [
        'id' => $regulasi->id,
        'tentang' => 'Perubahan yang tidak boleh tersimpan.',
    ]);

    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_DELETE);
    expect(fn () => $service->delete(
        $regulasi,
        'Izin penghapusan dicabut sebelum service memulai transaksi.',
        $this->perencanaan,
    ))->toThrow(AuthorizationException::class);
    $this->assertDatabaseHas('regulasi', ['id' => $regulasi->id]);

    tolakIzin($this->perencanaan, PermissionCodes::BERKAS_DELETE);
    expect(fn () => $service->deleteAttachment(
        $regulasi,
        $berkas,
        'Izin penghapusan lampiran dicabut sebelum service memulai transaksi.',
        $this->perencanaan,
    ))->toThrow(AuthorizationException::class);
    expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeFalse();

    tolakIzin($this->perencanaan, PermissionCodes::REGULASI_CREATE);
    expect(fn () => $service->create([
        'jenis' => 'keputusan_lainnya',
        'nomor' => 'CREATE-DENIED-2026',
        'tahun' => 2026,
        'tentang' => 'Regulasi ini tidak boleh dibuat setelah izin dicabut.',
        'aktif' => true,
    ], $this->perencanaan))->toThrow(AuthorizationException::class);
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

test('delete ditolak saat regulasi dirujuk data aktif', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $renstra = Renstra::query()->create([
        'regulasi_id' => $regulasi->id,
        'kode' => 'RENSTRA-REGULASI',
        'nama' => 'Renstra dengan regulasi aktif',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'is_aktif' => true,
    ]);
    $sasaran = SasaranStrategis::query()->create([
        'renstra_id' => $renstra->id,
        'kode' => 'SS-REGULASI',
        'deskripsi' => 'Sasaran uji delete guard',
    ]);
    IndikatorKinerja::query()->create([
        'sasaran_strategis_id' => $sasaran->id,
        'regulasi_id' => $regulasi->id,
        'kode' => 'IKU-REGULASI',
        'nama' => 'Indikator uji regulasi',
        'satuan' => '%',
        'is_aktif' => true,
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
});

test('explicit deny menang dan dasar izin penolakan diaudit', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    $permission = Permission::findByName(PermissionCodes::REGULASI_UPDATE, 'web');

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

test('pic tidak menerima permission regulasi sampai preset resmi ditetapkan', function (): void {
    $pic = userDenganRole('pic', 'pic-regulasi@example.test');

    $this->actingAs($pic)
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

test('hapus lampiran ditolak saat regulasi dirujuk data aktif', function (): void {
    $regulasi = buatRegulasi($this->perencanaan);
    Renstra::query()->create([
        'regulasi_id' => $regulasi->id,
        'kode' => 'RENSTRA-LAMPIRAN',
        'nama' => 'Renstra dengan regulasi aktif',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'is_aktif' => true,
    ]);
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
});

function userDenganRole(string $roleName, string $email): User
{
    $role = Role::findByName($roleName, 'web');
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole($role);

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
    $permission = Permission::findByName($permissionCode, 'web');

    UserPermissionDenial::query()->create([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'unit_id' => null,
        'alasan' => 'Izin dicabut untuk memastikan service gagal tertutup.',
        'ditetapkan_oleh' => $user->id,
    ]);
}
