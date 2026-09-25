<?php

use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenstraPestTestCase extends TestCase
{
    public User $perencanaan;

    public User $pembaca;
}

uses(RenstraPestTestCase::class, RefreshDatabase::class);

function pasangPresetRoleUntukTestRenstra(string $roleName): void
{
    $role = Role::query()->where('kode', $roleName)->firstOrFail();
    $permissionIds = Permission::query()
        ->whereIn('kode', RolePermissionPresets::forRole($roleName))
        ->pluck('id');

    $role->permissions()->syncWithoutDetaching($permissionIds
        ->mapWithKeys(fn (string $permissionId): array => [$permissionId => ['id' => (string) Str::uuid(), 'created_at' => now()]])
        ->all());
}

function userDenganRoleRenstra(string $roleName, string $email): User
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

function buatRenstra(User $pembuat, array $overrides = []): Renstra
{
    return Renstra::query()->create(array_merge([
        'nama' => 'Rencana Strategis LLDIKTI XVI 2025-2029',
        'kode' => 'RENSTRA-2025-2029',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'status' => Renstra::STATUS_DRAFT,
        'is_aktif' => false,
        'deskripsi' => 'Dokumen induk perencanaan strategis.',
        'dasar_hukum' => 'Permendikbudristek terkait SAKIP.',
        'created_by' => $pembuat->id,
    ], $overrides));
}

beforeEach(function (): void {
    $this->seed(RegulasiPermissionSeeder::class);
    pasangPresetRoleUntukTestRenstra('perencanaan');
    pasangPresetRoleUntukTestRenstra('pegawai');
    $this->perencanaan = userDenganRoleRenstra('perencanaan', 'perencanaan-renstra@example.test');
    $this->pembaca = userDenganRoleRenstra('pegawai', 'pembaca-renstra@example.test');
});

test('AC-1: Data Renstra valid tersimpan dengan status awal draft', function (): void {
    $payload = [
        'nama' => 'Rencana Strategis LLDIKTI XVI 2025-2029',
        'kode' => 'RENSTRA-2025-2029',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'deskripsi' => 'Penyusunan awal Renstra LLDIKTI Wilayah XVI.',
        'dasar_hukum' => 'Kepmendikbudristek penetapan Renstra.',
    ];

    $response = $this->actingAs($this->perencanaan)->post('/renstra', $payload);

    $response->assertRedirect('/renstra');

    $renstra = Renstra::query()->where('kode', 'RENSTRA-2025-2029')->firstOrFail();

    expect($renstra->status)->toBe('draft');
    expect($renstra->is_aktif)->toBeFalse();
    expect($renstra->nama)->toBe('Rencana Strategis LLDIKTI XVI 2025-2029');
    expect($renstra->created_by)->toBe($this->perencanaan->id);

    $this->assertDatabaseHas('renstras', [
        'id' => $renstra->id,
        'kode' => 'RENSTRA-2025-2029',
        'status' => 'draft',
        'is_aktif' => false,
    ]);

    $audit = AuditLog::query()
        ->where('tindakan', 'renstra.buat')
        ->where('objek_id', $renstra->id)
        ->firstOrFail();

    expect($audit->dasar_izin['keputusan'])->toBe('diizinkan');
});

test('AC-2: Naskah Renstra dilampirkan via relasi polimorfik berkas dengan 3 mode', function (): void {
    Storage::fake('local');

    $payload = [
        'nama' => 'Renstra Berkas LLDIKTI XVI',
        'kode' => 'RENSTRA-LAMPIRAN',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'lampiran' => [
            [
                'mode' => 'file',
                'file' => UploadedFile::fake()->create('naskah-renstra.pdf', 300, 'application/pdf'),
            ],
            [
                'mode' => 'tautan',
                'tautan' => 'https://jdih.kemdikbud.go.id/dokumen/renstra-2025',
            ],
            [
                'mode' => 'teks',
                'isi_teks' => 'Kutipan substansi arah kebijakan naskah Renstra.',
            ],
        ],
    ];

    $response = $this->actingAs($this->perencanaan)->post('/renstra', $payload);

    $response->assertRedirect('/renstra');

    $renstra = Renstra::query()->where('kode', 'RENSTRA-LAMPIRAN')->firstOrFail();
    expect($renstra->berkas)->toHaveCount(3);

    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => $renstra->getMorphClass(),
        'berkasable_id' => $renstra->id,
        'jenis_berkas_id' => null,
        'mode' => 'file',
    ]);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => $renstra->getMorphClass(),
        'berkasable_id' => $renstra->id,
        'mode' => 'tautan',
        'tautan' => 'https://jdih.kemdikbud.go.id/dokumen/renstra-2025',
    ]);
    $this->assertDatabaseHas('berkas', [
        'berkasable_type' => $renstra->getMorphClass(),
        'berkasable_id' => $renstra->id,
        'mode' => 'teks',
    ]);

    $fileBerkas = $renstra->berkas->firstWhere('mode', 'file');
    Storage::disk('local')->assertExists($fileBerkas->path);
});

test('AC-3: Rentang tahun validasi: tahun_selesai >= tahun_mulai, input tidak valid ditolak', function (): void {
    $payloadInvalid = [
        'nama' => 'Renstra Tahun Terbalik',
        'kode' => 'RENSTRA-INVALID-YEAR',
        'tahun_mulai' => 2030,
        'tahun_selesai' => 2025,
    ];

    $response = $this->actingAs($this->perencanaan)
        ->from('/renstra/create')
        ->post('/renstra', $payloadInvalid);

    $response->assertSessionHasErrors(['tahun_selesai']);
    $this->assertDatabaseMissing('renstras', ['kode' => 'RENSTRA-INVALID-YEAR']);
});

test('AC-4: Imutabilitas lampiran: percobaan menghapus lampiran pada Renstra aktif ditolak', function (): void {
    Storage::fake('local');

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-AKTIF-LOCK',
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ]);

    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'tautan',
        'tautan' => 'https://example.test/naskah-final.pdf',
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->from("/renstra/{$renstra->id}")
        ->delete("/renstra/{$renstra->id}/berkas/{$berkas->id}", [
            'alasan' => 'Mencoba menghapus dokumen naskah yang sudah aktif.',
        ]);

    $response->assertSessionHasErrors(['berkas']);
    $this->assertDatabaseHas('berkas', ['id' => $berkas->id]);
    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'berkas.hapus_ditolak',
        'objek_id' => $berkas->id,
    ]);
});

test('AC-4: Penghapusan lampiran pada Renstra draft diizinkan dan tercatat di audit log', function (): void {
    Storage::fake('local');

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-DRAFT-HAPUS',
        'status' => Renstra::STATUS_DRAFT,
        'is_aktif' => false,
    ]);

    $file = UploadedFile::fake()->create('draft-naskah.pdf', 100, 'application/pdf');
    $path = $file->store('berkas/renstra', 'local');

    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'file',
        'nama_asli' => 'draft-naskah.pdf',
        'path' => $path,
        'disk' => 'local',
        'mime' => 'application/pdf',
        'ukuran_bytes' => 102400,
    ]);

    Storage::disk('local')->assertExists($path);

    $response = $this->actingAs($this->perencanaan)
        ->from("/renstra/{$renstra->id}")
        ->delete("/renstra/{$renstra->id}/berkas/{$berkas->id}", [
            'alasan' => 'Revisi dokumen draf naskah sebelum disahkan.',
        ]);

    $response->assertRedirect("/renstra/{$renstra->id}");
    expect(Berkas::query()->where('id', $berkas->id)->exists())->toBeFalse();
    expect(Berkas::withTrashed()->where('id', $berkas->id)->first()->dihapus_pada)->not->toBeNull();
    Storage::disk('local')->assertMissing($path);

    $audit = AuditLog::query()
        ->where('tindakan', 'berkas.hapus')
        ->where('objek_id', $berkas->id)
        ->firstOrFail();

    expect($audit->alasan)->toBe('Revisi dokumen draf naskah sebelum disahkan.');
    expect($audit->dasar_izin['keputusan'])->toBe('diizinkan');
});

test('RBAC: Pegawai tanpa renstra:create ditolak 403 saat mencoba membuat Renstra', function (): void {
    $response = $this->actingAs($this->pembaca)->post('/renstra', [
        'nama' => 'Renstra Tidak Berhak',
        'kode' => 'RENSTRA-403',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('renstras', ['kode' => 'RENSTRA-403']);
});

test('RBAC: Renstra index dan show dapat diakses oleh perencanaan dan ditolak untuk role tanpa renstra:read', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-BACA']);

    // Perencanaan memiliki izin renstra:read
    $responseIndex = $this->actingAs($this->perencanaan)->get('/renstra');
    $responseIndex->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->has('renstra.data')
            ->has('regulasiPilihan')
        );

    $responseShow = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}");
    $responseShow->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('renstra.kode', 'RENSTRA-BACA')
        );

    // Pegawai tanpa renstra:read ditolak 403
    $this->actingAs($this->pembaca)->get('/renstra')->assertForbidden();
    $this->actingAs($this->pembaca)->get("/renstra/{$renstra->id}")->assertForbidden();
});

test('Renstra dapat ditautkan ke regulasi rujukan', function (): void {
    $regulasi = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 123/2024',
        'tahun' => 2024,
        'tentang' => 'Standar Akuntabilitas',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);

    $payload = [
        'nama' => 'Renstra Berdasar Regulasi',
        'kode' => 'RENSTRA-REGULASI',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'regulasi_id' => $regulasi->id,
    ];

    $this->actingAs($this->perencanaan)->post('/renstra', $payload);

    $renstra = Renstra::query()->where('kode', 'RENSTRA-REGULASI')->firstOrFail();
    expect($renstra->regulasi_id)->toBe($regulasi->id);
    expect($renstra->regulasi->nomor)->toBe('Permen 123/2024');
});

test('Update Renstra aktif mewajibkan alasan audit', function (): void {
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-UPDATE-AKTIF',
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ]);

    $responseTanpaAlasan = $this->actingAs($this->perencanaan)
        ->from("/renstra/{$renstra->id}/edit")
        ->put("/renstra/{$renstra->id}", [
            'nama' => 'Nama Baru Tanpa Alasan',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'alasan' => '',
        ]);

    $responseTanpaAlasan->assertSessionHasErrors(['alasan']);

    $responseDenganAlasan = $this->actingAs($this->perencanaan)
        ->put("/renstra/{$renstra->id}", [
            'nama' => 'Renstra Nama Telah Diperbarui',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'alasan' => 'Penyesuaian redaksional nama dokumen Renstra.',
        ]);

    $responseDenganAlasan->assertRedirect("/renstra/{$renstra->id}");
    expect($renstra->fresh()->nama)->toBe('Renstra Nama Telah Diperbarui');

    $audit = AuditLog::query()
        ->where('tindakan', 'renstra.ubah')
        ->where('objek_id', $renstra->id)
        ->firstOrFail();

    expect($audit->alasan)->toBe('Penyesuaian redaksional nama dokumen Renstra.');
});

test('Penghapusan Renstra berstatus selain draft ditolak', function (): void {
    $renstraNonaktif = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-NONAKTIF',
        'status' => Renstra::STATUS_NONAKTIF,
        'is_aktif' => false,
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->from('/renstra')
        ->delete("/renstra/{$renstraNonaktif->id}", [
            'alasan' => 'Mencoba menghapus renstra nonaktif.',
        ]);

    $response->assertSessionHasErrors(['renstra']);
    $this->assertDatabaseHas('renstras', ['id' => $renstraNonaktif->id]);
});

test('Penghapusan Renstra draft menghapus berkas lampiran dengan dihapus_oleh dan mencatat audit log berkas.hapus', function (): void {
    Storage::fake('local');

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-DRAFT-CASCADE',
        'status' => Renstra::STATUS_DRAFT,
        'is_aktif' => false,
    ]);

    $file = UploadedFile::fake()->create('lampiran-cascade.pdf', 50, 'application/pdf');
    $path = $file->store("berkas/renstra/{$renstra->id}", 'local');

    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'file',
        'nama_asli' => 'lampiran-cascade.pdf',
        'path' => $path,
        'disk' => 'local',
        'mime' => 'application/pdf',
        'ukuran_bytes' => 51200,
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->from('/renstra')
        ->delete("/renstra/{$renstra->id}", [
            'alasan' => 'Menghapus draf renstra beserta seluruh lampirannya.',
        ]);

    $response->assertRedirect('/renstra');
    $this->assertDatabaseMissing('renstras', ['id' => $renstra->id]);
    expect(Berkas::query()->where('id', $berkas->id)->exists())->toBeFalse();
    expect(Berkas::withTrashed()->where('id', $berkas->id)->first()->dihapus_pada)->not->toBeNull();
    expect(Berkas::withTrashed()->where('id', $berkas->id)->first()->dihapus_oleh)->toBe($this->perencanaan->id);

    $this->assertDatabaseHas('audit_log', [
        'tindakan' => 'berkas.hapus',
        'objek_id' => $berkas->id,
    ]);
});

test('Sinkronisasi status dan is_aktif bekerja dua arah pada model Renstra', function (): void {
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-SYNC',
        'status' => Renstra::STATUS_DRAFT,
        'is_aktif' => false,
    ]);

    // draft + is_aktif=true -> status menjadi aktif
    $renstra->is_aktif = true;
    expect($renstra->status)->toBe(Renstra::STATUS_AKTIF);
    expect($renstra->is_aktif)->toBeTrue();

    // aktif + is_aktif=false -> status menjadi nonaktif
    $renstra->is_aktif = false;
    expect($renstra->status)->toBe(Renstra::STATUS_NONAKTIF);
    expect($renstra->is_aktif)->toBeFalse();

    // nonaktif + is_aktif=true -> status menjadi aktif (tidak boleh ambigu nonaktif + true)
    $renstra->is_aktif = true;
    expect($renstra->status)->toBe(Renstra::STATUS_AKTIF);
    expect($renstra->is_aktif)->toBeTrue();

    // status diarsipkan tidak dapat diaktifkan kembali lewat is_aktif
    $renstra->status = Renstra::STATUS_DIARSIPKAN;
    expect($renstra->is_aktif)->toBeFalse();
    $renstra->is_aktif = true;
    expect($renstra->status)->toBe(Renstra::STATUS_DIARSIPKAN);
    expect($renstra->is_aktif)->toBeFalse();

    // set status langsung menyelaraskan is_aktif
    $renstra->status = Renstra::STATUS_AKTIF;
    expect($renstra->is_aktif)->toBeTrue();

    $renstra->status = Renstra::STATUS_DRAFT;
    expect($renstra->is_aktif)->toBeFalse();

    $renstra->status = Renstra::STATUS_NONAKTIF;
    expect($renstra->is_aktif)->toBeFalse();
});

test('Download lampiran Renstra memerlukan otorisasi viewAttachment', function (): void {
    Storage::fake('local');

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-DOWNLOAD',
    ]);

    $file = UploadedFile::fake()->create('dokumen-unduh.pdf', 30, 'application/pdf');
    $path = $file->store("berkas/renstra/{$renstra->id}", 'local');

    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'file',
        'nama_asli' => 'dokumen-unduh.pdf',
        'path' => $path,
        'disk' => 'local',
        'mime' => 'application/pdf',
        'ukuran_bytes' => 30720,
    ]);

    $responsePerencanaan = $this->actingAs($this->perencanaan)
        ->get("/renstra/{$renstra->id}/berkas/{$berkas->id}/download");
    $responsePerencanaan->assertOk();

    $responsePembaca = $this->actingAs($this->pembaca)
        ->get("/renstra/{$renstra->id}/berkas/{$berkas->id}/download");
    $responsePembaca->assertForbidden();
});

test('Halaman Edit Renstra tidak memuat relasi berkas ke props Inertia', function (): void {
    Storage::fake('local');
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-EDIT-NO-BERKAS']);
    $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'tautan',
        'tautan' => 'https://example.test/private-naskah.pdf',
    ]);

    $response = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}/edit");
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Edit')
            ->where('renstra.kode', 'RENSTRA-EDIT-NO-BERKAS')
            ->missing('renstra.berkas')
        );
});

test('Pengecekan capability UI di ShowRenstra tidak mencatat audit denial palsu', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-SHOW-PURE']);

    $viewerRole = Role::query()->create([
        'id' => (string) Str::uuid(),
        'kode' => 'viewer_renstra',
        'nama' => 'Viewer Renstra',
        'urutan' => 99,
    ]);
    $readPerm = Permission::query()->where('kode', 'renstra:read')->firstOrFail();
    $viewerRole->permissions()->attach($readPerm->id, ['id' => (string) Str::uuid(), 'created_at' => now()]);

    $viewer = User::factory()->create(['email' => 'viewer-only@example.test', 'is_active' => true]);
    $viewer->roles()->attach($viewerRole->id, [
        'id' => (string) Str::uuid(),
        'sumber_pemberian' => 'manual',
        'diberikan_oleh' => $viewer->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($viewer)->get("/renstra/{$renstra->id}");
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('can.update', false)
            ->where('can.delete', false)
        );

    expect(AuditLog::query()
        ->whereIn('tindakan', ['renstra.ubah_ditolak', 'renstra.hapus_ditolak'])
        ->where('objek_id', $renstra->id)
        ->exists())->toBeFalse();
});

test('Halaman Show Renstra tidak memuat relasi sasaranStrategis atau indikator ke props Inertia', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-NO-INDICATOR-LEAK']);

    $response = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}");
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->missing('renstra.sasaran_strategis')
            ->missing('renstra.sasaranStrategis')
        );
});

test('Imutabilitas lampiran: percobaan menghapus lampiran pada Renstra nonaktif dan diarsipkan ditolak', function (): void {
    Storage::fake('local');

    foreach ([Renstra::STATUS_NONAKTIF, Renstra::STATUS_DIARSIPKAN] as $status) {
        $renstra = buatRenstra($this->perencanaan, [
            'kode' => "RENSTRA-LOCK-{$status}",
            'status' => $status,
            'is_aktif' => false,
        ]);

        $berkas = $renstra->berkas()->create([
            'uploaded_by' => $this->perencanaan->id,
            'mode' => 'tautan',
            'tautan' => "https://example.test/naskah-{$status}.pdf",
        ]);

        $response = $this->actingAs($this->perencanaan)
            ->from("/renstra/{$renstra->id}")
            ->delete("/renstra/{$renstra->id}/berkas/{$berkas->id}", [
                'alasan' => 'Mencoba menghapus lampiran pada status non-draft.',
            ]);

        $response->assertSessionHasErrors(['berkas']);
        $this->assertDatabaseHas('berkas', ['id' => $berkas->id]);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'berkas.hapus_ditolak',
            'objek_id' => $berkas->id,
        ]);
    }
});

test('Percobaan menghapus Renstra dengan dependensi sasaran mencatat audit renstra.hapus_ditolak', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-DEP-CHECK']);
    $renstra->sasaranStrategis()->create([
        'kode' => 'SS-DEP-01',
        'deskripsi' => 'Sasaran Terkait',
        'urutan' => 1,
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->from('/renstra')
        ->delete("/renstra/{$renstra->id}", [
            'alasan' => 'Menghapus renstra yang masih memiliki sasaran.',
        ]);

    $response->assertSessionHasErrors(['renstra']);
    $this->assertDatabaseHas('renstras', ['id' => $renstra->id]);

    $audit = AuditLog::query()
        ->where('tindakan', 'renstra.hapus_ditolak')
        ->where('objek_id', $renstra->id)
        ->latest('waktu')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->nilai_baru['alasan_penolakan'])->toBe('memiliki_dependensi');
});

test('Perubahan regulasi_id pada Renstra mencatat audit renstra.ubah_regulasi', function (): void {
    $regulasi1 = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 01/2024',
        'tahun' => 2024,
        'tentang' => 'Regulasi Pertama',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);

    $regulasi2 = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 02/2024',
        'tahun' => 2024,
        'tentang' => 'Regulasi Kedua',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-UBAH-REG',
        'regulasi_id' => $regulasi1->id,
    ]);

    $response = $this->actingAs($this->perencanaan)->put("/renstra/{$renstra->id}", [
        'nama' => $renstra->nama,
        'tahun_mulai' => $renstra->tahun_mulai,
        'tahun_selesai' => $renstra->tahun_selesai,
        'regulasi_id' => $regulasi2->id,
    ]);

    $response->assertRedirect("/renstra/{$renstra->id}");
    expect($renstra->fresh()->regulasi_id)->toBe($regulasi2->id);

    $audit = AuditLog::query()
        ->where('tindakan', 'renstra.ubah_regulasi')
        ->where('objek_id', $renstra->id)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->nilai_lama['regulasi_id'])->toBe($regulasi1->id);
    expect($audit->nilai_baru['regulasi_id'])->toBe($regulasi2->id);
});

test('Penghapusan lampiran Renstra ditolak jika otorisasi parent Renstra ditolak', function (): void {
    Storage::fake('local');

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-PARENT-AUTH',
        'status' => Renstra::STATUS_DRAFT,
    ]);

    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'tautan',
        'tautan' => 'https://example.test/lampiran-draft.pdf',
    ]);

    $berkasOnlyRole = Role::query()->create([
        'id' => (string) Str::uuid(),
        'kode' => 'berkas_only',
        'nama' => 'Berkas Only',
        'urutan' => 100,
    ]);
    $berkasDeletePerm = Permission::query()->where('kode', 'berkas:delete')->firstOrFail();
    $berkasOnlyRole->permissions()->attach($berkasDeletePerm->id, ['id' => (string) Str::uuid(), 'created_at' => now()]);

    $userBerkasOnly = User::factory()->create(['email' => 'berkas-only@example.test', 'is_active' => true]);
    $userBerkasOnly->roles()->attach($berkasOnlyRole->id, [
        'id' => (string) Str::uuid(),
        'sumber_pemberian' => 'manual',
        'diberikan_oleh' => $userBerkasOnly->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($userBerkasOnly)
        ->delete("/renstra/{$renstra->id}/berkas/{$berkas->id}", [
            'alasan' => 'Mencoba menghapus lampiran tanpa izin parent.',
        ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('berkas', ['id' => $berkas->id]);

    $audit = AuditLog::query()
        ->where('tindakan', 'berkas.hapus_ditolak')
        ->where('objek_id', $berkas->id)
        ->latest('waktu')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->dasar_izin['keputusan'] ?? null)->not->toBe('diizinkan');
});

test('Percobaan membuat Renstra tanpa izin renstra:create mencatat audit renstra.buat_ditolak', function (): void {
    $pembacaRole = Role::query()->where('kode', 'pembaca')->first();
    $userPembaca = User::factory()->create(['email' => 'pembaca-create-fail@example.test', 'is_active' => true]);
    if ($pembacaRole) {
        $userPembaca->roles()->attach($pembacaRole->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $userPembaca->id,
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($userPembaca)->post('/renstra', [
        'nama' => 'Renstra Ilegal',
        'tahun_mulai' => 2026,
        'tahun_selesai' => 2030,
    ]);

    $response->assertForbidden();

    $audit = AuditLog::query()
        ->where('tindakan', 'renstra.buat_ditolak')
        ->latest('waktu')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->actor_id)->toBe($userPembaca->id);
    expect($audit->dasar_izin['keputusan'] ?? null)->not->toBe('diizinkan');
});

test('Renstra yang berstatus diarsipkan tidak dapat diubah dan menu edit ditolak', function (): void {
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-ARSIP-GUARD',
        'status' => Renstra::STATUS_DIARSIPKAN,
        'is_aktif' => false,
    ]);

    // Akses ke halaman edit harus ditolak 403
    $this->actingAs($this->perencanaan)
        ->get("/renstra/{$renstra->id}/edit")
        ->assertForbidden();

    // Permintaan update harus ditolak validasi
    $response = $this->actingAs($this->perencanaan)
        ->from("/renstra/{$renstra->id}")
        ->put("/renstra/{$renstra->id}", [
            'nama' => 'Renstra Berubah Nama',
            'tahun_mulai' => $renstra->tahun_mulai,
            'tahun_selesai' => $renstra->tahun_selesai,
        ]);

    $response->assertSessionHasErrors(['renstra']);
    expect($renstra->fresh()->nama)->toBe($renstra->nama);

    // Pada halaman Show, capability update harus false
    $this->actingAs($this->perencanaan)
        ->get("/renstra/{$renstra->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('can.update', false)
        );
});

test('Penghapusan Renstra dengan lampiran berkas menghormati deny berkas:delete', function (): void {
    Storage::fake('local');

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-CASCADE-DENY',
        'status' => Renstra::STATUS_DRAFT,
    ]);

    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'tautan',
        'tautan' => 'https://example.test/lampiran-cascade.pdf',
    ]);

    // Berikan user explicit deny pada berkas:delete
    $berkasDeletePerm = Permission::query()->where('kode', 'berkas:delete')->firstOrFail();
    DB::table('user_permission_denials')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $berkasDeletePerm->id,
        'diberikan_oleh' => $this->perencanaan->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->perencanaan)
        ->from('/renstra')
        ->delete("/renstra/{$renstra->id}", [
            'alasan' => 'Mencoba menghapus induk saat berkas delete di-deny.',
        ]);

    $response->assertSessionHasErrors(['renstra']);
    $this->assertDatabaseHas('renstras', ['id' => $renstra->id]);
    $this->assertDatabaseHas('berkas', ['id' => $berkas->id]);

    $audit = AuditLog::query()
        ->where('tindakan', 'renstra.hapus_ditolak')
        ->where('objek_id', $renstra->id)
        ->latest('waktu')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->nilai_baru['alasan_penolakan'])->toBe('berkas_delete_denied');
});

test('Payload Renstra menghormati izin regulasi:read dan membatasi data pembuat', function (): void {
    $regulasi = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 99/2025',
        'tahun' => 2025,
        'tentang' => 'Regulasi Khusus Pembacaan',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);

    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-PRIVACY-CHECK',
        'regulasi_id' => $regulasi->id,
    ]);

    // Berikan user explicit deny pada regulasi:read
    $regulasiReadPerm = Permission::query()->where('kode', 'regulasi:read')->firstOrFail();
    DB::table('user_permission_denials')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $regulasiReadPerm->id,
        'diberikan_oleh' => $this->perencanaan->id,
        'created_at' => now(),
    ]);

    // Pada halaman Index: regulasiPilihan harus kosong [] dan pembuat tidak diserialisasi
    $responseIndex = $this->actingAs($this->perencanaan)->get('/renstra');
    $responseIndex->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->where('regulasiPilihan', [])
            ->has('renstra.data.0', fn (Assert $item) => $item
                ->missing('pembuat')
                ->etc()
            )
        );

    // Pada halaman Show: pembuat hanya mengekspos id dan nama, serta relasi regulasi disembunyikan
    $responseShow = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}");
    $responseShow->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->missing('renstra.regulasi')
            ->has('renstra.pembuat', fn (Assert $pembuat) => $pembuat
                ->has('id')
                ->has('nama')
                ->missing('email')
                ->missing('no_hp')
                ->missing('nip')
            )
        );
});
