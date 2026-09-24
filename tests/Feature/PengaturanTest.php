<?php

use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use App\Services\PengaturanService;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PengaturanPestTestCase extends TestCase
{
    public User $admin;

    public User $perencanaan;

    public User $pegawai;
}

uses(PengaturanPestTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessCatalogSeeder::class);
    $this->seed(PengaturanSeeder::class);

    pasangRoleDanPermission('admin');
    pasangRoleDanPermission('perencanaan');
    pasangRoleDanPermission('pegawai');

    $this->admin = buatUserDenganRole('admin', 'admin-pengaturan@example.test');
    $this->perencanaan = buatUserDenganRole('perencanaan', 'perencanaan-pengaturan@example.test');
    $this->pegawai = buatUserDenganRole('pegawai', 'pegawai-pengaturan@example.test');
});

function pasangRoleDanPermission(string $roleName): void
{
    $role = Role::query()->where('kode', $roleName)->firstOrFail();
    $codes = RolePermissionPresets::forRole($roleName);
    $permissionIds = Permission::query()
        ->whereIn('kode', $codes)
        ->pluck('id');

    $role->permissions()->syncWithoutDetaching(
        $permissionIds->mapWithKeys(fn (string $id) => [
            $id => ['id' => (string) Str::uuid(), 'created_at' => now()],
        ])->all()
    );
}

function buatUserDenganRole(string $roleName, string $email): User
{
    $role = Role::query()->where('kode', $roleName)->firstOrFail();
    $user = User::factory()->create(['email' => $email, 'is_active' => true]);
    $user->roles()->attach($role->id, [
        'id' => (string) Str::uuid(),
        'sumber_pemberian' => 'manual',
        'diberikan_oleh' => $user->id,
        'created_at' => now(),
    ]);

    return $user;
}

test('AC-1: admin dapat mengakses halaman pengaturan dan melihat grup konfigurasi', function (): void {
    $response = $this->actingAs($this->admin)->get('/pengaturan');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Pengaturan/Index')
        ->has('grouped.instansi')
        ->has('grouped.aplikasi')
        ->has('grouped.tampilan')
        ->has('grouped.laporan')
        ->has('values')
    );

    /** @var array<string, mixed> $props */
    $props = $response->original->getData()['page']['props'];
    expect($props['values']['instansi.nama'])->toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');
});

test('AC-1 & AC-2: admin dapat memperbarui pengaturan dan menghasilkan pencatatan audit log lengkap', function (): void {
    $seeded = Pengaturan::query()->pluck('updated_at', 'kunci')->all();
    $expectedUpdatedAt = [];
    foreach ($seeded as $key => $ts) {
        $expectedUpdatedAt[$key] = Carbon::parse($ts)->toISOString();
    }

    $response = $this->actingAs($this->admin)->put('/pengaturan', [
        'instansi.nama' => 'LLDIKTI Wilayah XVI Baru',
        'instansi.alamat' => 'Jl. Baru Kampus Barat, Gorontalo',
        'instansi.telepon' => '(0435) 899999',
        'instansi.surel' => 'info@lldikti16.kemdikbud.go.id',
        'instansi.laman' => 'https://lldikti16.kemdikbud.go.id',
        'instansi.logo' => '/img/logo-baru.png',
        'aplikasi.nama' => 'SAKIP LLDIKTI XVI 2026',
        'aplikasi.label_unit' => 'Satuan Kerja',
        'tampilan.zona_waktu' => 'Asia/Makassar',
        'tampilan.format_tanggal' => 'd F Y',
        'tampilan.format_angka' => 'id_ID',
        'laporan.header' => 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI',
        'laporan.footer' => 'Dicetak dari SAKIP Resmi',
        'alasan' => 'Pembaruan identitas dan kontak institusi periode 2026.',
        'expected_updated_at' => $expectedUpdatedAt,
    ]);

    $response->assertRedirect(route('pengaturan.index'));
    $response->assertSessionHas('success');

    // Pastikan database terupdate
    $this->assertDatabaseHas('pengaturan', [
        'kunci' => 'instansi.nama',
        'nilai' => 'LLDIKTI Wilayah XVI Baru',
        'updated_by' => $this->admin->id,
    ]);

    $this->assertDatabaseHas('pengaturan', [
        'kunci' => 'aplikasi.nama',
        'nilai' => 'SAKIP LLDIKTI XVI 2026',
        'updated_by' => $this->admin->id,
    ]);

    // Pastikan audit log tercatat untuk perubahan
    $setting = Pengaturan::query()->where('kunci', 'instansi.nama')->firstOrFail();
    $audit = AuditLog::query()
        ->where('tindakan', 'pengaturan:update')
        ->where('objek_tipe', 'pengaturan')
        ->where('objek_id', (string) $setting->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->actor_id)->toBe($this->admin->id);
    expect($audit->alasan)->toBe('Pembaruan identitas dan kontak institusi periode 2026.');
    expect($audit->nilai_lama['nilai'])->toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');
    expect($audit->nilai_baru['nilai'])->toBe('LLDIKTI Wilayah XVI Baru');
    expect($audit->dasar_izin['permission'])->toBe('pengaturan:update');
    expect($audit->dasar_izin['keputusan'])->toBe('diizinkan');
});

test('AC-1: cache pengaturan bekerja dan di-invalidasi ketika nilai diperbarui', function (): void {
    /** @var PengaturanService $service */
    $service = app(PengaturanService::class);

    // Initial read (mengisi cache)
    $val1 = $service->get('instansi.nama');
    expect($val1)->toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');

    $token = Pengaturan::query()->where('kunci', 'instansi.nama')->value('updated_at')?->toISOString();

    // Update via service
    $service->update(
        $this->admin,
        ['instansi.nama' => 'LLDIKTI Wilayah XVI Terverifikasi Cache'],
        'Uji invalidasi cache',
        ['instansi.nama' => $token]
    );

    // Read kembali harus mengembalikan nilai baru yang sudah diinvalidasi
    $val2 = $service->get('instansi.nama');
    expect($val2)->toBe('LLDIKTI Wilayah XVI Terverifikasi Cache');
});

test('AC-3: peran non-administratif (perencanaan, pegawai) ditolak dengan HTTP 403 dan dicatat dalam audit trail', function (): void {
    // Role perencanaan mencoba mengakses GET /pengaturan
    $resPerencanaanGet = $this->actingAs($this->perencanaan)->get('/pengaturan');
    $resPerencanaanGet->assertForbidden();

    // Role perencanaan mencoba mutasi PUT /pengaturan
    $resPerencanaanPut = $this->actingAs($this->perencanaan)->put('/pengaturan', [
        'instansi.nama' => 'Pembobolan Oleh Perencanaan',
        'alasan' => 'Mencoba ubah tanpa hak akses',
    ]);
    $resPerencanaanPut->assertForbidden();

    // Pastikan percobaan mutasi yang ditolak dicatat dalam audit log
    $this->assertDatabaseHas('audit_log', [
        'actor_id' => $this->perencanaan->id,
        'tindakan' => 'pengaturan.ubah_ditolak',
        'objek_tipe' => 'pengaturan',
        'alasan' => 'Mencoba ubah tanpa hak akses',
    ]);

    // Role pegawai mencoba mengakses GET /pengaturan
    $resPegawaiGet = $this->actingAs($this->pegawai)->get('/pengaturan');
    $resPegawaiGet->assertForbidden();

    // Role pegawai mencoba mutasi PUT /pengaturan
    $resPegawaiPut = $this->actingAs($this->pegawai)->put('/pengaturan', [
        'instansi.nama' => 'Pembobolan Oleh Pegawai',
    ]);
    $resPegawaiPut->assertForbidden();

    $this->assertDatabaseHas('audit_log', [
        'actor_id' => $this->pegawai->id,
        'tindakan' => 'pengaturan.ubah_ditolak',
        'objek_tipe' => 'pengaturan',
    ]);

    // Role non-administratif mengirim alasan masif (> 255 karakter) dipotong ke 255 karakter
    $longAlasan = str_repeat('A', 500);
    $resLongAlasan = $this->actingAs($this->perencanaan)->put('/pengaturan', [
        'instansi.nama' => 'Pembobolan Alasan Masif',
        'alasan' => $longAlasan,
    ]);
    $resLongAlasan->assertForbidden();

    $this->assertDatabaseHas('audit_log', [
        'actor_id' => $this->perencanaan->id,
        'tindakan' => 'pengaturan.ubah_ditolak',
        'objek_tipe' => 'pengaturan',
        'alasan' => str_repeat('A', 255),
    ]);
});

test('AC-3: pengguna tamu (unauthenticated) diarahkan ke login', function (): void {
    $response = $this->get('/pengaturan');
    $response->assertRedirect('/login');

    $responsePut = $this->put('/pengaturan', [
        'instansi.nama' => 'Tamu Mengubah',
    ]);
    $responsePut->assertRedirect('/login');
});

test('AC-4: strict server-side whitelist guard menolak kunci di luar whitelist dengan HTTP 422', function (): void {
    $response = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'alasan' => 'Uji validasi whitelist sistem',
        'instansi.nama' => 'LLDIKTI XVI Valid',
        'status_alur_kerja' => 'bypass_approval', // Kunci berbahaya di luar whitelist
        'roles.superadmin' => 'semua_akses',       // Kunci berbahaya lain
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['status_alur_kerja', 'roles.superadmin']);

    // Pastikan tidak ada data ilegal tersimpan di database
    $this->assertDatabaseMissing('pengaturan', [
        'kunci' => 'status_alur_kerja',
    ]);

    // Pastikan tidak ada catatan audit palsu yang tercipta
    $this->assertDatabaseMissing('audit_log', [
        'tindakan' => 'pengaturan:update',
        'alasan' => 'bypass_approval',
    ]);
});

test('AC-4: validasi menolak format input tidak valid dengan HTTP 422', function (): void {
    $seeded = Pengaturan::query()->whereIn('kunci', [
        'instansi.nama',
        'aplikasi.nama',
        'aplikasi.label_unit',
        'tampilan.zona_waktu',
        'tampilan.format_tanggal',
        'tampilan.format_angka',
        'instansi.surel',
        'instansi.laman',
    ])->pluck('updated_at', 'kunci')->all();

    $expectedUpdatedAt = [];
    foreach ($seeded as $key => $ts) {
        $expectedUpdatedAt[$key] = Carbon::parse($ts)->toISOString();
    }

    $response = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'alasan' => 'Uji validasi format input',
        'instansi.nama' => 'LLDIKTI Wilayah XVI',
        'aplikasi.nama' => 'SAKIP LLDIKTI XVI',
        'aplikasi.label_unit' => 'Unit Kerja',
        'tampilan.zona_waktu' => 'Asia/Kucing', // Zona waktu ilegal
        'tampilan.format_tanggal' => 'd F Y',
        'tampilan.format_angka' => 'id_ID',
        'instansi.surel' => 'bukan-email-valid',
        'instansi.laman' => 'bukan-url-valid',
        'expected_updated_at' => $expectedUpdatedAt,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['instansi.surel', 'instansi.laman', 'tampilan.zona_waktu']);
});

test('server mewajibkan alasan perubahan minimal 5 karakter untuk catatan audit', function (): void {
    $token = Pengaturan::query()->where('kunci', 'instansi.nama')->value('updated_at')?->toISOString();

    // Tanpa alasan
    $resNoReason = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'Nama Baru',
        'expected_updated_at' => [
            'instansi.nama' => $token,
        ],
    ]);
    $resNoReason->assertUnprocessable();
    $resNoReason->assertJsonValidationErrors(['alasan']);

    // Alasan terlalu pendek (< 5 karakter)
    $resShortReason = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'Nama Baru',
        'alasan' => 'test',
        'expected_updated_at' => [
            'instansi.nama' => $token,
        ],
    ]);
    $resShortReason->assertUnprocessable();
    $resShortReason->assertJsonValidationErrors(['alasan']);
});

test('nilai null yang disengaja dipertahankan dan tidak kembali ke nilai default seeder', function (): void {
    $token = Pengaturan::query()->where('kunci', 'instansi.alamat')->value('updated_at')?->toISOString();

    // Admin mengosongkan alamat instansi (opsional)
    $response = $this->actingAs($this->admin)->put('/pengaturan', [
        'instansi.alamat' => null,
        'alasan' => 'Mengosongkan alamat instansi sementara',
        'expected_updated_at' => [
            'instansi.alamat' => $token,
        ],
    ]);
    $response->assertRedirect(route('pengaturan.index'));

    $this->assertDatabaseHas('pengaturan', [
        'kunci' => 'instansi.alamat',
        'nilai' => null,
    ]);

    /** @var PengaturanService $service */
    $service = app(PengaturanService::class);
    $all = $service->allGrouped();

    expect($all['values']['instansi.alamat'])->toBeNull();

    $itemAlamat = collect($all['grouped']['instansi'])->firstWhere('kunci', 'instansi.alamat');
    expect($itemAlamat['nilai'])->toBeNull();
});

test('deteksi konflik konkurensi (optimistic locking) menolak stale update', function (): void {
    $now = now();
    $setting = Pengaturan::query()->where('kunci', 'instansi.nama')->firstOrFail();
    $setting->updated_at = $now;
    $setting->save();

    // Admin B mencoba mengupdate dengan expected_updated_at yang lebih lama (stale)
    $staleTimestamp = $now->subMinutes(5)->toIso8601String();

    $response = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'LLDIKTI Konflik',
        'alasan' => 'Pembaruan oleh admin lain yang terlambat',
        'expected_updated_at' => [
            'instansi.nama' => $staleTimestamp,
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['instansi.nama']);
});

test('validasi token konkurensi menolak format tanggal invalid dan kunci non-whitelist', function (): void {
    // 1. Format tanggal tidak valid -> 422, bukan 500
    $responseInvalidDate = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'LLDIKTI Valid',
        'alasan' => 'Pembaruan alasan validasi',
        'expected_updated_at' => [
            'instansi.nama' => 'bukan-tanggal',
        ],
    ]);
    $responseInvalidDate->assertUnprocessable();
    $responseInvalidDate->assertJsonValidationErrors(['expected_updated_at.instansi.nama']);

    // 2. Kunci tidak ada di whitelist -> 422
    $responseInvalidKey = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'LLDIKTI Valid',
        'alasan' => 'Pembaruan alasan validasi',
        'expected_updated_at' => [
            'kunci.ilegal' => now()->toISOString(),
        ],
    ]);
    $responseInvalidKey->assertUnprocessable();
    $responseInvalidKey->assertJsonValidationErrors(['expected_updated_at.kunci.ilegal']);
});

test('pembaruan kunci yang sudah ada tanpa token versi ditolak dengan HTTP 422', function (): void {
    $response = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'LLDIKTI Tanpa Token',
        'alasan' => 'Mencoba bypass optimistic lock tanpa token versi',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['instansi.nama']);
});

test('pembaruan kunci yang sudah ada dengan token null ditolak dengan HTTP 422', function (): void {
    $response = $this->actingAs($this->admin)->putJson('/pengaturan', [
        'instansi.nama' => 'LLDIKTI Token Null',
        'alasan' => 'Mencoba bypass optimistic lock dengan token null',
        'expected_updated_at' => [
            'instansi.nama' => null,
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['instansi.nama']);
});

test('evaluasi ulang izin di dalam batas transaksi mutasi menolak aksi jika izin dicabut atau user dinonaktifkan', function (): void {
    /** @var PengaturanService $service */
    $service = app(PengaturanService::class);
    $token = Pengaturan::query()->where('kunci', 'instansi.nama')->value('updated_at')?->toISOString();

    // 1. Akun dinonaktifkan
    $inactiveAdmin = buatUserDenganRole('admin', 'admin-nonaktif@example.test');
    $inactiveAdmin->is_active = false;
    $inactiveAdmin->save();

    expect(fn () => $service->update(
        $inactiveAdmin,
        ['instansi.nama' => 'Nilai Baru Nonaktif'],
        'Pembaruan oleh admin nonaktif',
        ['instansi.nama' => $token]
    ))->toThrow(AuthorizationException::class);

    // 2. Role admin dicabut
    $revokedAdmin = buatUserDenganRole('admin', 'admin-dicabut@example.test');
    $revokedAdmin->roles()->detach();

    expect(fn () => $service->update(
        $revokedAdmin,
        ['instansi.nama' => 'Nilai Baru Dicabut'],
        'Pembaruan oleh user yang rolenya dicabut',
        ['instansi.nama' => $token]
    ))->toThrow(AuthorizationException::class);
});

test('pembaruan parsial hanya memperbarui kunci yang dikirim dan tidak mengubah kunci lain', function (): void {
    $namaAwal = Pengaturan::query()->where('kunci', 'instansi.nama')->value('nilai');
    $tokenTelepon = Pengaturan::query()->where('kunci', 'instansi.telepon')->value('updated_at')?->toISOString();

    // Hanya kirim instansi.telepon
    $response = $this->actingAs($this->admin)->put('/pengaturan', [
        'instansi.telepon' => '(0435) 999111',
        'alasan' => 'Pembaruan nomor telepon saja',
        'expected_updated_at' => [
            'instansi.telepon' => $tokenTelepon,
        ],
    ]);
    $response->assertRedirect(route('pengaturan.index'));

    $this->assertDatabaseHas('pengaturan', [
        'kunci' => 'instansi.telepon',
        'nilai' => '(0435) 999111',
    ]);
    $this->assertDatabaseHas('pengaturan', [
        'kunci' => 'instansi.nama',
        'nilai' => $namaAwal,
    ]);
});

test('pengaturan dibagikan ke Inertia shared props via allValues', function (): void {
    $response = $this->actingAs($this->admin)->get('/pengaturan');
    $response->assertOk();

    /** @var array<string, mixed> $props */
    $props = $response->original->getData()['page']['props'];
    expect($props)->toHaveKey('pengaturan');
    expect($props['pengaturan']['instansi.nama'])->toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');
    expect($props['pengaturan']['aplikasi.nama'])->toBe('SAKIP LLDIKTI XVI');
});

test('allValues dan get tetap mengembalikan data database jika cache store mengalami kegagalan', function (): void {
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('Cache connection refused'));

    $service = app(PengaturanService::class);
    $values = $service->allValues();

    expect($values)->toBeArray();
    expect($values)->toHaveKey('instansi.nama');
    expect($values['instansi.nama'])->toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');

    $val = $service->get('instansi.nama');
    expect($val)->toBe('Lembaga Layanan Pendidikan Tinggi Wilayah XVI');
});
