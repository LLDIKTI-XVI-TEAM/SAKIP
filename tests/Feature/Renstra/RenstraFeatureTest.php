<?php

use App\Actions\Audit\WriteAuditLog;
use App\Actions\Renstra\CreateRenstraAction;
use App\Actions\Renstra\DeleteRenstraAction;
use App\Actions\Renstra\DeleteRenstraAttachmentAction;
use App\Actions\Renstra\UpdateRenstraAction;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RolePermissionPresets;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
    $user = User::factory()->create(['email' => $email, 'status' => 'aktif']);
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

test('AC-4: Naskah Renstra dilampirkan via relasi polimorfik berkas dengan 3 mode', function (): void {
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

    $auditUnggah = AuditLog::query()
        ->where('tindakan', 'berkas.unggah')
        ->where('objek_id', $fileBerkas->id)
        ->firstOrFail();
    $auditInduk = AuditLog::query()
        ->where('tindakan', 'renstra.buat')
        ->where('objek_id', $renstra->id)
        ->firstOrFail();

    expect($auditUnggah->nilai_baru)->not->toHaveKey('path');
    expect($auditInduk->nilai_baru['lampiran'][0])->not->toHaveKey('path');
});

test('AC-2: Rentang tahun validasi: tahun_selesai >= tahun_mulai, input tidak valid ditolak', function (): void {
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

test('Imutabilitas lampiran: percobaan menghapus lampiran pada Renstra aktif ditolak', function (): void {
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

test('Penghapusan lampiran pada Renstra draft diizinkan dan tercatat di audit log', function (): void {
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

test('AC-5: Pegawai tanpa renstra:create ditolak 403 saat mencoba membuat Renstra', function (): void {
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

test('AC-3: Rujukan regulasi valid dapat ditampilkan dan dikosongkan tanpa kehilangan dasar hukum', function (): void {
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
        'dasar_hukum' => 'Ringkasan dasar hukum Renstra tetap disimpan.',
    ];

    $this->actingAs($this->perencanaan)->post('/renstra', $payload);

    $renstra = Renstra::query()->where('kode', 'RENSTRA-REGULASI')->firstOrFail();
    expect($renstra->regulasi_id)->toBe($regulasi->id);
    expect($renstra->regulasi->nomor)->toBe('Permen 123/2024');

    $this->actingAs($this->perencanaan)->get('/renstra')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->has('renstra.data.0', fn (Assert $item) => $item
                ->where('regulasi_nomor', 'Permen 123/2024')
                ->missing('regulasi')
                ->missing('sasaran_strategis_count')
                ->etc()
            )
        );

    $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('renstra.regulasi.jenis', 'permen')
            ->where('renstra.regulasi.nomor', 'Permen 123/2024')
            ->where('renstra.regulasi.tahun', 2024)
            ->where('renstra.regulasi.tentang', 'Standar Akuntabilitas')
        );

    $payload['regulasi_id'] = null;
    $payload['expected_state'] = $renstra->stateToken();
    $this->actingAs($this->perencanaan)->put("/renstra/{$renstra->id}", $payload)
        ->assertRedirect("/renstra/{$renstra->id}");

    $renstra = $renstra->fresh();
    expect($renstra->regulasi_id)->toBeNull();
    expect($renstra->dasar_hukum)->toBe('Ringkasan dasar hukum Renstra tetap disimpan.');

    $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('renstra.regulasi', null)
            ->where('renstra.dasar_hukum', 'Ringkasan dasar hukum Renstra tetap disimpan.')
        );
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
            'expected_state' => $renstra->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
            'nama' => 'Nama Baru Tanpa Alasan',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'alasan' => '',
        ]);

    $responseTanpaAlasan->assertSessionHasErrors(['alasan']);

    $responseDenganAlasan = $this->actingAs($this->perencanaan)
        ->put("/renstra/{$renstra->id}", [
            'expected_state' => $renstra->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
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

    expect($audit->alasan)->toBe("Penyesuaian redaksional nama dokumen Renstra.\nRujukan: 123/M/2026 tanggal 2026-09-20");
});

test('Pembaruan Renstra aktif menolak rentang yang beririsan dengan Renstra aktif lain', function (): void {
    buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-AKTIF-2025',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ]);
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-AKTIF-2030',
        'tahun_mulai' => 2030,
        'tahun_selesai' => 2034,
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ]);

    $this->actingAs($this->perencanaan)
        ->from("/renstra/{$renstra->id}/edit")
        ->put("/renstra/{$renstra->id}", [
            'expected_state' => $renstra->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
            'nama' => $renstra->nama,
            'tahun_mulai' => 2029,
            'tahun_selesai' => 2034,
            'dasar_hukum' => $renstra->dasar_hukum,
            'alasan' => 'Penyesuaian periode dokumen Renstra aktif.',
        ])
        ->assertSessionHasErrors(['tahun_mulai']);

    expect($renstra->fresh()->tahun_mulai)->toBe(2030);
    $this->assertDatabaseHas('audit_log', [
        'objek_id' => $renstra->id,
        'tindakan' => 'renstra.ubah_ditolak',
    ]);
});

test('Basis data mewajibkan penyusun Renstra', function (): void {
    expect(fn () => DB::transaction(fn () => buatRenstra($this->perencanaan, [
        'created_by' => null,
    ])))->toThrow(QueryException::class);
});

test('Basis data mempertahankan penyusun selama masih dirujuk Renstra', function (): void {
    $penyusun = User::factory()->create();
    $renstra = buatRenstra($penyusun);

    expect(fn () => DB::transaction(fn () => DB::table('users')
        ->where('id', $penyusun->id)
        ->delete()))->toThrow(QueryException::class);

    expect($renstra->fresh()->created_by)->toBe($penyusun->id);
    $this->assertDatabaseHas('users', ['id' => $penyusun->id]);
});

test('Migrasi penyusun wajib mempertahankan data dan menghentikan rilis bila penyusun belum diketahui', function (): void {
    $migration = require database_path('migrations/2026_09_28_000001_require_renstra_creator.php');
    $renstra = buatRenstra($this->perencanaan);

    $migration->down();
    $tanpaPenyusun = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-PENYUSUN-BELUM-DIKETAHUI',
        'created_by' => null,
    ]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Renstra lama belum memiliki penyusun');
    expect($tanpaPenyusun->fresh()->created_by)->toBeNull();
    expect($renstra->fresh()->created_by)->toBe($this->perencanaan->id);

    // Identitas lama baru boleh diisi setelah ditemukan bukti penyusun yang sah.
    $tanpaPenyusun->update(['created_by' => $this->perencanaan->id]);
    $migration->up();

    expect($tanpaPenyusun->fresh()->created_by)->toBe($this->perencanaan->id);
    expect($renstra->fresh()->status)->toBe(Renstra::STATUS_DRAFT);
    expect(fn () => DB::transaction(fn () => $tanpaPenyusun->update(['created_by' => null])))
        ->toThrow(QueryException::class);
});

test('Basis data menolak dua Renstra aktif dengan rentang tahun beririsan', function (): void {
    buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-CONSTRAINT-1',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ]);

    expect(fn () => DB::transaction(fn () => buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-CONSTRAINT-2',
        'tahun_mulai' => 2029,
        'tahun_selesai' => 2033,
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ])))->toThrow(QueryException::class);
});

test('Migrasi mempertahankan Renstra lama nonaktif sebagai nonaktif', function (): void {
    $migration = require database_path('migrations/2026_09_25_000001_enhance_renstras_table_for_master_domain.php');
    $migration->down();

    $id = (string) Str::uuid();
    DB::table('renstras')->insert([
        'id' => $id,
        'kode' => 'RENSTRA-LEGACY-NONAKTIF',
        'nama' => 'Renstra historis nonaktif',
        'tahun_mulai' => 2020,
        'tahun_selesai' => 2024,
        'is_aktif' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(DB::table('renstras')->where('id', $id)->value('status'))->toBe(Renstra::STATUS_NONAKTIF);
});

test('Migrasi memberi diagnosis sebelum mengubah skema bila Renstra aktif lama beririsan', function (): void {
    $migration = require database_path('migrations/2026_09_25_000001_enhance_renstras_table_for_master_domain.php');
    $migration->down();

    foreach ([['kode' => 'LEGACY-2025', 'mulai' => 2025, 'selesai' => 2029], ['kode' => 'LEGACY-2029', 'mulai' => 2029, 'selesai' => 2033]] as $item) {
        DB::table('renstras')->insert([
            'id' => (string) Str::uuid(),
            'kode' => $item['kode'],
            'nama' => $item['kode'],
            'tahun_mulai' => $item['mulai'],
            'tahun_selesai' => $item['selesai'],
            'is_aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Renstra aktif lama memiliki rentang tahun beririsan');
    expect(Schema::hasColumn('renstras', 'status'))->toBeFalse();
});

test('Migrasi menolak rentang tahun Renstra aktif lama yang terbalik sebelum mengubah skema', function (): void {
    $migration = require database_path('migrations/2026_09_25_000001_enhance_renstras_table_for_master_domain.php');
    $migration->down();

    DB::table('renstras')->insert([
        'id' => (string) Str::uuid(),
        'kode' => 'LEGACY-RENTANG-TERBALIK',
        'nama' => 'Renstra aktif lama dengan rentang tidak valid',
        'tahun_mulai' => 2030,
        'tahun_selesai' => 2025,
        'is_aktif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'rentang tahun tidak valid');
    expect(Schema::hasColumn('renstras', 'status'))->toBeFalse();
});

test('Pembaruan Renstra aktif tidak boleh mengosongkan dasar hukum', function (): void {
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-AKTIF-DASAR-HUKUM',
        'status' => Renstra::STATUS_AKTIF,
        'is_aktif' => true,
    ]);

    $this->actingAs($this->perencanaan)
        ->from("/renstra/{$renstra->id}/edit")
        ->put("/renstra/{$renstra->id}", [
            'expected_state' => $renstra->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
            'nama' => $renstra->nama,
            'tahun_mulai' => $renstra->tahun_mulai,
            'tahun_selesai' => $renstra->tahun_selesai,
            'dasar_hukum' => '',
            'alasan' => 'Mencoba menghapus dasar hukum Renstra aktif.',
        ])
        ->assertSessionHasErrors(['dasar_hukum']);

    expect($renstra->fresh()->dasar_hukum)->toBe('Permendikbudristek terkait SAKIP.');
    $this->assertDatabaseHas('audit_log', [
        'objek_id' => $renstra->id,
        'tindakan' => 'renstra.ubah_ditolak',
    ]);
});

test('Lampiran baru hanya dapat ditambahkan pada Renstra draft', function (): void {
    foreach ([Renstra::STATUS_AKTIF, Renstra::STATUS_NONAKTIF] as $status) {
        $renstra = buatRenstra($this->perencanaan, [
            'kode' => "RENSTRA-TAMBAH-LAMPIRAN-{$status}",
            'status' => $status,
            'is_aktif' => $status === Renstra::STATUS_AKTIF,
        ]);

        $this->actingAs($this->perencanaan)
            ->from("/renstra/{$renstra->id}/edit")
            ->put("/renstra/{$renstra->id}", [
                'expected_state' => $renstra->stateToken(),
                'nomor_kebijakan' => '123/M/2026',
                'tanggal_kebijakan' => '2026-09-20',
                'nama' => $renstra->nama,
                'tahun_mulai' => $renstra->tahun_mulai,
                'tahun_selesai' => $renstra->tahun_selesai,
                'dasar_hukum' => $renstra->dasar_hukum,
                'alasan' => 'Mencoba menambah lampiran setelah status draft.',
                'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Lampiran baru yang harus ditolak.']],
            ])
            ->assertSessionHasErrors($status === Renstra::STATUS_NONAKTIF ? ['renstra'] : ['lampiran']);

        expect($renstra->berkas()->exists())->toBeFalse();
        $this->assertDatabaseHas('audit_log', [
            'objek_id' => $renstra->id,
            'tindakan' => 'renstra.ubah_ditolak',
        ]);
        $response = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}/edit");
        if ($status === Renstra::STATUS_NONAKTIF) {
            $response->assertForbidden();
        } else {
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Renstra/Edit')->where('can.uploadAttachment', false));
        }
    }

    $draft = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-TAMBAH-LAMPIRAN-DRAFT']);
    $this->actingAs($this->perencanaan)->put("/renstra/{$draft->id}", [
        'expected_state' => $draft->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'nama' => $draft->nama,
        'tahun_mulai' => $draft->tahun_mulai,
        'tahun_selesai' => $draft->tahun_selesai,
        'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Lampiran baru pada status draft.']],
    ])->assertRedirect(route('renstra.show', $draft->id));
    expect($draft->berkas()->count())->toBe(1);
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

test('AC-6: Penghapusan Renstra draft menghapus berkas lampiran dengan dihapus_oleh dan mencatat audit log berkas.hapus', function (): void {
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
    $viewer = $this->perencanaan;

    foreach ([PermissionCodes::RENSTRA_UPDATE, PermissionCodes::RENSTRA_DELETE] as $kode) {
        $permission = Permission::query()->where('kode', $kode)->firstOrFail();
        UserPermissionDeny::create([
            'id' => (string) Str::uuid(),
            'user_id' => $viewer->id,
            'permission_id' => $permission->id,
            'alasan' => 'Pengujian capability tanpa audit penolakan',
            'ditetapkan_oleh' => $viewer->id,
        ]);
    }

    expect($viewer->can('view', $renstra))->toBeTrue();

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
        'expected_state' => $renstra->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
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

    $userBerkasOnly = $this->perencanaan;
    foreach ([PermissionCodes::RENSTRA_UPDATE, PermissionCodes::RENSTRA_DELETE] as $kode) {
        UserPermissionDeny::create([
            'user_id' => $userBerkasOnly->id,
            'permission_id' => Permission::query()->where('kode', $kode)->firstOrFail()->id,
            'alasan' => 'Pengujian izin berkas tanpa izin mutasi parent Renstra',
            'ditetapkan_oleh' => $userBerkasOnly->id,
        ]);
    }

    $resolver = app(PermissionResolver::class);
    expect($resolver->resolve($userBerkasOnly, PermissionCodes::BERKAS_DELETE)->allowed)->toBeTrue();
    expect($resolver->resolve($userBerkasOnly, PermissionCodes::RENSTRA_DELETE)->allowed)->toBeFalse();
    expect($resolver->resolve($userBerkasOnly, PermissionCodes::RENSTRA_UPDATE)->allowed)->toBeFalse();

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
    expect($audit->dasar_izin['keputusan'])->toBe('ditolak');
    expect($audit->dasar_izin['permission'])->toBe(PermissionCodes::RENSTRA_DELETE);
});

test('Percobaan membuat Renstra tanpa izin renstra:create mencatat audit renstra.buat_ditolak', function (): void {
    $pembacaRole = Role::query()->where('kode', 'pembaca')->first();
    $userPembaca = User::factory()->create(['email' => 'pembaca-create-fail@example.test', 'status' => 'aktif']);
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
            'expected_state' => $renstra->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
            'nama' => 'Renstra Berubah Nama',
            'tahun_mulai' => $renstra->tahun_mulai,
            'tahun_selesai' => $renstra->tahun_selesai,
        ]);

    $response->assertSessionHasErrors(['renstra']);
    expect($renstra->fresh()->nama)->toBe($renstra->nama);
    $this->assertDatabaseHas('audit_log', [
        'objek_id' => $renstra->id,
        'tindakan' => 'renstra.ubah_ditolak',
    ]);

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
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $berkasDeletePerm->id,
        'alasan' => 'Deny berkas:delete untuk pengujian',
        'ditetapkan_oleh' => $this->perencanaan->id,
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

    // Pada halaman Index dan Show, kemampuan delete disesuaikan saat izin berkas ditolak
    $responseIndex = $this->actingAs($this->perencanaan)->get('/renstra');
    $responseIndex->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->where('can.berkas:delete', false)
        );

    $responseShow = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}");
    $responseShow->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('can.delete', false)
            ->where('can.deleteAttachment', false)
        );

    $berkasReadPerm = Permission::query()->where('kode', 'berkas:read')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $berkasReadPerm->id,
        'alasan' => 'Deny berkas:read untuk pengujian capability hapus',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->perencanaan)
        ->get("/renstra/{$renstra->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('can.delete', false)
            ->where('can.deleteAttachment', false)
        );
});

test('Index mengirim capability unggah lampiran kepada modal tambah Renstra', function (): void {
    $permission = Permission::query()->where('kode', 'berkas:upload')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny berkas:upload untuk modal tambah Renstra',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->perencanaan)
        ->get('/renstra')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->where('can.uploadAttachment', false)
        );
});

test('Index tidak mengirim jumlah lampiran ketika izin baca berkas ditolak', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-HITUNG-BERKAS']);
    foreach (['Lampiran pertama', 'Lampiran kedua'] as $isi) {
        $renstra->berkas()->create([
            'uploaded_by' => $this->perencanaan->id,
            'mode' => 'teks',
            'isi_teks' => $isi,
        ]);
    }

    $this->actingAs($this->perencanaan)->get('/renstra')
        ->assertInertia(fn (Assert $page) => $page
            ->where('renstra.data.0.berkas_count', 2)
        );

    $permission = Permission::query()->where('kode', 'berkas:read')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny jumlah lampiran pada Index',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->perencanaan)->get('/renstra')
        ->assertInertia(fn (Assert $page) => $page
            ->where('renstra.data.0.berkas_count', null)
            ->where('renstra.data.0.can_delete', true)
        );
});

test('Index dan Show tidak mengungkap keberadaan lampiran lewat capability hapus tanpa izin berkas', function (): void {
    $kosong = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-TANPA-LAMPIRAN']);
    $berlampiran = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-DENGAN-LAMPIRAN']);
    $berlampiran->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'teks',
        'isi_teks' => 'Naskah pengujian',
    ]);

    foreach ([PermissionCodes::BERKAS_READ, PermissionCodes::BERKAS_DELETE] as $kode) {
        $permission = Permission::query()->where('kode', $kode)->firstOrFail();
        UserPermissionDeny::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->perencanaan->id,
            'permission_id' => $permission->id,
            'alasan' => 'Deny akses berkas pada pengujian capability',
            'ditetapkan_oleh' => $this->perencanaan->id,
        ]);
    }

    $this->actingAs($this->perencanaan)->get('/renstra')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->where('can.renstra:delete', true)
            ->where('can.berkas:delete', false)
            ->where('renstra.total', 2)
            ->where('renstra.data.0.berkas_count', null)
            ->where('renstra.data.1.berkas_count', null)
            ->where('renstra.data.0.can_delete', false)
            ->where('renstra.data.1.can_delete', false)
        );

    foreach ([$kosong, $berlampiran] as $renstra) {
        $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Renstra/Show')
                ->where('renstra.berkas', [])
                ->where('can.delete', false)
                ->where('can.deleteAttachment', false)
            );
    }
});

test('Index menolak filter berbentuk array sebelum menyusun query', function (): void {
    $this->actingAs($this->perencanaan)->get('/renstra?q[]=x')
        ->assertRedirect()
        ->assertSessionHasErrors('q');

    $this->actingAs($this->perencanaan)->get('/renstra?status[]=draft')
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    $this->actingAs($this->perencanaan)->get('/renstra?status=tidak-valid')
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-FILTER-VALID']);
    $this->actingAs($this->perencanaan)->get('/renstra?q=FILTER-VALID&status=draft')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'FILTER-VALID')
            ->where('filters.status', 'draft')
            ->where('renstra.total', 1)
        );
});

test('Deny berkas upload saat membuat Renstra dicatat di luar transaksi', function (): void {
    $permission = Permission::query()->where('kode', 'berkas:upload')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny unggah untuk pengujian create',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->perencanaan)->post('/renstra', [
        'nama' => 'Renstra unggah ditolak',
        'kode' => 'RENSTRA-UPLOAD-DENIED',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Lampiran langsung dari request.']],
    ])->assertForbidden();

    $this->assertDatabaseMissing('renstras', ['kode' => 'RENSTRA-UPLOAD-DENIED']);
    $audit = AuditLog::query()->where('tindakan', 'renstra.buat_ditolak')->latest('waktu')->firstOrFail();
    expect($audit->nilai_baru['alasan_penolakan'])->toBe('berkas_upload_denied');
    expect($audit->dasar_izin['keputusan'])->not->toBe('diizinkan');
});

test('Deny berkas upload saat mengubah Renstra dicatat di luar transaksi', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-UPDATE-UPLOAD-DENIED']);
    $permission = Permission::query()->where('kode', 'berkas:upload')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny unggah untuk pengujian update',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->perencanaan)->put("/renstra/{$renstra->id}", [
        'expected_state' => $renstra->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'nama' => $renstra->nama,
        'tahun_mulai' => $renstra->tahun_mulai,
        'tahun_selesai' => $renstra->tahun_selesai,
        'lampiran' => [['mode' => 'teks', 'isi_teks' => 'Lampiran langsung dari request.']],
    ])->assertForbidden();

    expect($renstra->berkas()->exists())->toBeFalse();
    $audit = AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->where('objek_id', $renstra->id)->latest('waktu')->firstOrFail();
    expect($audit->nilai_baru['alasan_penolakan'])->toBe('berkas_upload_denied');
    expect($audit->dasar_izin['keputusan'])->not->toBe('diizinkan');
});

test('Deny unggah didahulukan dari validasi lampiran yang tidak lengkap', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-DENY-VALIDASI']);
    $permission = Permission::query()->where('kode', 'berkas:upload')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny unggah sebelum validasi lampiran',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $this->actingAs($this->perencanaan)->post('/renstra', [
        'nama' => 'Renstra dengan lampiran tidak lengkap',
        'kode' => 'RENSTRA-DENY-CREATE-INVALID',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'lampiran' => [['mode' => 'teks']],
    ])->assertForbidden();

    $this->actingAs($this->perencanaan)->put("/renstra/{$renstra->id}", [
        'expected_state' => $renstra->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'nama' => $renstra->nama,
        'tahun_mulai' => $renstra->tahun_mulai,
        'tahun_selesai' => $renstra->tahun_selesai,
        'lampiran' => [['mode' => 'teks']],
    ])->assertForbidden();

    $this->assertDatabaseMissing('renstras', ['kode' => 'RENSTRA-DENY-CREATE-INVALID']);
    expect($renstra->berkas()->exists())->toBeFalse();
    foreach (['renstra.buat_ditolak', 'renstra.ubah_ditolak'] as $tindakan) {
        $audit = AuditLog::query()->where('tindakan', $tindakan)->latest('waktu')->firstOrFail();
        expect($audit->dasar_izin['permission'])->toBe('berkas:upload');
        expect($audit->dasar_izin['keputusan'])->toBe('ditolak');
    }
});

test('Halaman detail tidak mengirim path dan kolom internal lampiran', function (): void {
    $renstra = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-SAFE-BERKAS-PAYLOAD']);
    $berkas = $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'file',
        'nama_asli' => 'naskah.pdf',
        'path' => 'berkas/renstra/private/naskah.pdf',
        'mime' => 'application/pdf',
        'ukuran_bytes' => 123,
    ]);

    $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Show')
            ->where('renstra.berkas.0.download_url', route('renstra.berkas.download', [$renstra, $berkas]))
            ->missing('renstra.berkas.0.path')
            ->missing('renstra.berkas.0.berkasable_type')
            ->missing('renstra.berkas.0.berkasable_id')
            ->missing('renstra.berkas.0.dihapus_oleh')
        );
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

    $renstra->berkas()->create([
        'uploaded_by' => $this->perencanaan->id,
        'mode' => 'tautan',
        'tautan' => 'https://example.test/lampiran-privacy.pdf',
    ]);

    // Berikan user explicit deny pada regulasi:read
    $regulasiReadPerm = Permission::query()->where('kode', 'regulasi:read')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $regulasiReadPerm->id,
        'alasan' => 'Deny regulasi:read untuk pengujian',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    // Pada halaman Index: regulasiPilihan harus kosong [] dan pembuat tidak diserialisasi
    $responseIndex = $this->actingAs($this->perencanaan)->get('/renstra');
    $responseIndex->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Index')
            ->where('regulasiPilihan', [])
            ->has('renstra.data.0', fn (Assert $item) => $item
                ->missing('pembuat')
                ->where('regulasi_id', null)
                ->etc()
            )
        );

    // Pada halaman Show: pembuat dan pengunggah hanya mengekspos id dan nama, serta relasi regulasi disembunyikan
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
            ->has('renstra.berkas.0.pengunggah', fn (Assert $pengunggah) => $pengunggah
                ->has('id')
                ->has('nama')
                ->missing('email')
                ->missing('no_hp')
            )
        );

    $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Renstra/Edit')
            ->where('regulasiPilihan', [])
            ->where('renstra.regulasi_id', null)
            ->where('can.readRegulasi', false)
        );
});

test('Deny regulasi read menolak rujukan eksplisit tanpa menghalangi edit field lain', function (): void {
    $regulasiLama = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 201/2025',
        'tahun' => 2025,
        'tentang' => 'Rujukan lama',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);
    $regulasiBaru = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 202/2025',
        'tahun' => 2025,
        'tentang' => 'Rujukan baru',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-DENY-RUJUKAN',
        'regulasi_id' => $regulasiLama->id,
    ]);
    $permission = Permission::query()->where('kode', 'regulasi:read')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny baca rujukan regulasi',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    foreach ([$regulasiBaru->id, (string) Str::uuid()] as $index => $regulasiId) {
        $this->actingAs($this->perencanaan)->post('/renstra', [
            'nama' => 'Renstra ditolak karena rujukan',
            'kode' => 'RENSTRA-DENY-RUJUKAN-BARU-'.$index,
            'tahun_mulai' => 2030,
            'tahun_selesai' => 2034,
            'regulasi_id' => $regulasiId,
        ])->assertForbidden();
    }

    foreach ([$regulasiBaru->id, $regulasiLama->id, null] as $regulasiId) {
        $this->actingAs($this->perencanaan)->put("/renstra/{$renstra->id}", [
            'expected_state' => $renstra->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
            'nama' => 'Perubahan rujukan ditolak',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'regulasi_id' => $regulasiId,
        ])->assertForbidden();
    }

    $this->actingAs($this->perencanaan)->put("/renstra/{$renstra->id}", [
        'expected_state' => $renstra->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'nama' => 'Edit tanpa mengubah rujukan',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
    ])->assertRedirect();

    $renstra->refresh();
    expect($renstra->nama)->toBe('Edit tanpa mengubah rujukan');
    expect($renstra->regulasi_id)->toBe($regulasiLama->id);
    expect(Renstra::query()->where('kode', 'like', 'RENSTRA-DENY-RUJUKAN-BARU-%')->exists())->toBeFalse();

    $audit = AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->latest('waktu')->firstOrFail();
    expect($audit->nilai_baru['alasan_penolakan'])->toBe('regulasi_read_denied');
    expect($audit->dasar_izin['permission'])->toBe('regulasi:read');
    expect($audit->dasar_izin['keputusan'])->toBe('ditolak');
});

test('Deny regulasi read menolak field kosong tanpa membocorkan keadaan rujukan', function (): void {
    $renstraTanpaRujukan = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-TANPA-RUJUKAN']);
    $permission = Permission::query()->where('kode', 'regulasi:read')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $permission->id,
        'alasan' => 'Deny field rujukan kosong',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    foreach ([null, ''] as $index => $regulasiId) {
        $this->actingAs($this->perencanaan)->post('/renstra', [
            'nama' => 'Renstra dengan rujukan kosong',
            'kode' => 'RENSTRA-DENY-KOSONG-'.$index,
            'tahun_mulai' => 2030,
            'tahun_selesai' => 2034,
            'regulasi_id' => $regulasiId,
        ])->assertForbidden();

        $this->actingAs($this->perencanaan)->put("/renstra/{$renstraTanpaRujukan->id}", [
            'expected_state' => $renstraTanpaRujukan->stateToken(),
            'nomor_kebijakan' => '123/M/2026',
            'tanggal_kebijakan' => '2026-09-20',
            'nama' => 'Perubahan rujukan kosong ditolak',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'regulasi_id' => $regulasiId,
        ])->assertForbidden();
    }

    $this->actingAs($this->perencanaan)->put("/renstra/{$renstraTanpaRujukan->id}", [
        'expected_state' => $renstraTanpaRujukan->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'nama' => 'Edit tanpa field rujukan',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
    ])->assertRedirect();

    $this->actingAs($this->perencanaan)->post('/renstra', [
        'nama' => 'Buat tanpa field rujukan',
        'kode' => 'RENSTRA-TANPA-FIELD-RUJUKAN',
        'tahun_mulai' => 2030,
        'tahun_selesai' => 2034,
    ])->assertRedirect();

    expect($renstraTanpaRujukan->fresh()->nama)->toBe('Edit tanpa field rujukan');
    expect(Renstra::query()->where('kode', 'like', 'RENSTRA-DENY-KOSONG-%')->exists())->toBeFalse();
    $this->assertDatabaseHas('renstras', ['kode' => 'RENSTRA-TANPA-FIELD-RUJUKAN', 'regulasi_id' => null]);
});

test('Halaman edit Renstra menolak akses jika izin view ditolak meskipun memiliki izin update', function (): void {
    $renstra = buatRenstra($this->perencanaan, [
        'kode' => 'RENSTRA-EDIT-VIEW-DENY',
        'status' => Renstra::STATUS_DRAFT,
    ]);

    $renstraReadPerm = Permission::query()->where('kode', 'renstra:read')->firstOrFail();
    UserPermissionDeny::create([
        'id' => (string) Str::uuid(),
        'user_id' => $this->perencanaan->id,
        'permission_id' => $renstraReadPerm->id,
        'alasan' => 'Deny renstra:read untuk pengujian edit',
        'ditetapkan_oleh' => $this->perencanaan->id,
    ]);

    $response = $this->actingAs($this->perencanaan)->get("/renstra/{$renstra->id}/edit");
    $response->assertForbidden();
});

test('Validasi regulasi_id pada Renstra menolak regulasi nonaktif kecuali jika sedang dirujuk pada pembaruan', function (): void {
    $regulasiAktif = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 101/2025',
        'tahun' => 2025,
        'tentang' => 'Regulasi Aktif',
        'aktif' => true,
        'created_by' => $this->perencanaan->id,
    ]);

    $regulasiNonaktif1 = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 102/2025',
        'tahun' => 2025,
        'tentang' => 'Regulasi Nonaktif 1',
        'aktif' => false,
        'created_by' => $this->perencanaan->id,
    ]);

    $regulasiNonaktif2 = Regulasi::query()->create([
        'jenis' => 'permen',
        'nomor' => 'Permen 103/2025',
        'tahun' => 2025,
        'tentang' => 'Regulasi Nonaktif 2',
        'aktif' => false,
        'created_by' => $this->perencanaan->id,
    ]);

    // 1. Pembuatan baru dengan regulasi nonaktif ditolak
    $responseCreate = $this->actingAs($this->perencanaan)->post('/renstra', [
        'kode' => 'RENSTRA-REGULASI-CREATE-TEST',
        'nama' => 'Uji Coba Regulasi Nonaktif',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'regulasi_id' => $regulasiNonaktif1->id,
    ]);
    $responseCreate->assertSessionHasErrors(['regulasi_id']);

    // 2. Pembuatan baru dengan regulasi aktif berhasil
    $responseCreateValid = $this->actingAs($this->perencanaan)->post('/renstra', [
        'kode' => 'RENSTRA-REGULASI-CREATE-VALID',
        'nama' => 'Uji Coba Regulasi Aktif',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'regulasi_id' => $regulasiAktif->id,
    ]);
    $responseCreateValid->assertRedirect('/renstra');
    $createdRenstra = Renstra::query()->where('kode', 'RENSTRA-REGULASI-CREATE-VALID')->firstOrFail();

    // Set rujukan awal ke regulasiNonaktif1 secara langsung di DB untuk menguji skenario legacy rujukan nonaktif
    $createdRenstra->update(['regulasi_id' => $regulasiNonaktif1->id]);

    // 3. Update mempertahankan regulasi lama yang nonaktif berhasil
    $responseUpdateKeep = $this->actingAs($this->perencanaan)->put("/renstra/{$createdRenstra->id}", [
        'expected_state' => $createdRenstra->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'kode' => 'RENSTRA-REGULASI-CREATE-VALID',
        'nama' => 'Uji Coba Regulasi Pertahankan Rujukan Lama',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'regulasi_id' => $regulasiNonaktif1->id,
    ]);
    $responseUpdateKeep->assertRedirect("/renstra/{$createdRenstra->id}");

    // 4. Update mengganti ke regulasi nonaktif lain ditolak
    $responseUpdateChangeInactive = $this->actingAs($this->perencanaan)->put("/renstra/{$createdRenstra->id}", [
        'expected_state' => $createdRenstra->stateToken(),
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
        'kode' => 'RENSTRA-REGULASI-CREATE-VALID',
        'nama' => 'Uji Coba Ganti Regulasi Nonaktif Lain',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'regulasi_id' => $regulasiNonaktif2->id,
    ]);
    $responseUpdateChangeInactive->assertSessionHasErrors(['regulasi_id']);
});

/** Jalur langsung membuktikan guard use case tanpa bergantung pada FormRequest. */
function mutasiRenstraLangsung(string $operation, User $actor, Renstra $renstra, Berkas $berkas, array $extra = []): mixed
{
    $data = array_replace(['nama' => $renstra->nama, 'expected_state' => $renstra->fresh()->stateToken(), 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'alasan' => 'Perubahan fixture Renstra'], $extra);

    return match ($operation) {
        'create' => app(CreateRenstraAction::class)->handle($actor, ['kode' => 'RENSTRA-BARU', ...$data]),
        'update' => app(UpdateRenstraAction::class)->handle($actor, $renstra, $data),
        'delete' => app(DeleteRenstraAction::class)->handle($actor, $renstra, $data['alasan']),
        'attachment' => app(DeleteRenstraAttachmentAction::class)->handle($actor, $renstra, $berkas, $data['alasan']),
    };
}

function tolakIzinRenstra(User $actor, string $permission): UserPermissionDeny
{
    return UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'alasan' => 'Penolakan izin fixture Renstra', 'ditetapkan_oleh' => $actor->id]);
}

test('pasangan tahun efektif update memakai tahun akhir tersimpan ketika kedua alias dihilangkan', function (): void {
    $renstra = buatRenstra($this->perencanaan);
    $before = $renstra->fresh()->getAttributes();
    $this->actingAs($this->perencanaan)->put('/renstra/'.$renstra->id, ['nama' => 'Rentang terbalik', 'tahun_mulai' => 2030, 'expected_state' => $renstra->fresh()->stateToken()])
        ->assertSessionHasErrors(['tahun_akhir', 'tahun_selesai']);
    expect($renstra->fresh()->getAttributes())->toBe($before);
    expect(AuditLog::where('tindakan', 'renstra.ubah')->exists())->toBeFalse();
});

test('default dan alias tahun serta omission tetap menyimpan kontrak Renstra', function (): void {
    $this->actingAs($this->perencanaan)->post('/renstra', ['nama' => 'Default tahun', 'tahun_mulai' => 2025])->assertRedirect('/renstra');
    $renstra = Renstra::sole();
    expect($renstra->kode)->toBe('RENSTRA-2025-2025')->and($renstra->tahun_selesai)->toBe(2025);
    $this->put('/renstra/'.$renstra->id, ['nama' => $renstra->nama, 'expected_state' => $renstra->fresh()->stateToken(), 'tahun_mulai' => 2025, 'tahun_akhir' => 2029, 'keterangan' => 'Alias deskripsi'])->assertRedirect();
    expect($renstra->fresh()->tahun_selesai)->toBe(2029)->and($renstra->fresh()->deskripsi)->toBe('Alias deskripsi');
    $this->put('/renstra/'.$renstra->id, ['nama' => $renstra->nama, 'expected_state' => $renstra->fresh()->stateToken(), 'tahun_mulai' => 2026, 'deskripsi' => null])->assertRedirect();
    expect($renstra->fresh()->tahun_selesai)->toBe(2029)->and($renstra->fresh()->deskripsi)->toBeNull();
    $this->put('/renstra/'.$renstra->id, ['nama' => $renstra->nama, 'expected_state' => $renstra->fresh()->stateToken(), 'tahun_mulai' => 2026, 'tahun_selesai' => 2030, 'tahun_akhir' => 2029])->assertRedirect();
    expect($renstra->fresh()->tahun_selesai)->toBe(2030);
});

test('audit request memakai keputusan penolakan pertama yang sama', function (string $operation, string $permission, string $event): void {
    $renstra = buatRenstra($this->perencanaan);
    $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Awal', 'uploaded_by' => $this->perencanaan->id]);
    $real = app(PermissionResolver::class);
    $calls = 0;
    $resolver = Mockery::mock(PermissionResolver::class);
    $resolver->shouldReceive('resolve')->andReturnUsing(function ($actor, string $code, ...$args) use ($real, $permission, &$calls): PermissionDecision {
        if ($code === $permission) {
            $calls++;

            return new PermissionDecision($calls > 1, $code, ['alasan' => 'fixture_keputusan_awal', 'sumber_allow' => [], 'deny' => ['deny-awal']]);
        }

        return $real->resolve($actor, $code, ...$args);
    });
    app()->instance(PermissionResolver::class, $resolver);
    $path = $operation === 'create' ? '/renstra' : '/renstra/'.$renstra->id.($operation === 'attachment' ? '/berkas/'.$berkas->id : '');
    $this->actingAs($this->perencanaan)->call(match ($operation) {
        'create' => 'POST', 'update' => 'PUT', default => 'DELETE'
    }, $path, ['alasan' => 'Penolakan fixture'])->assertForbidden();
    $audit = AuditLog::where('tindakan', $event)->sole();
    expect($audit->dasar_izin['keputusan'])->toBe('ditolak')->and($audit->dasar_izin['deny'])->toBe(['deny-awal']);
    expect($calls)->toBe(1);
})->with([
    ['create', PermissionCodes::RENSTRA_CREATE, 'renstra.buat_ditolak'],
    ['update', PermissionCodes::RENSTRA_UPDATE, 'renstra.ubah_ditolak'],
    ['delete', PermissionCodes::RENSTRA_DELETE, 'renstra.hapus_ditolak'],
    ['attachment', PermissionCodes::BERKAS_DELETE, 'berkas.hapus_ditolak'],
]);

test('penolakan request dengan alasan kontrol tetap 403', function (string $operation, string $permission, string $event): void {
    $renstra = buatRenstra($this->perencanaan);
    $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Awal', 'uploaded_by' => $this->perencanaan->id]);
    tolakIzinRenstra($this->perencanaan, $permission);
    $path = $operation === 'create' ? '/renstra' : '/renstra/'.$renstra->id.($operation === 'attachment' ? '/berkas/'.$berkas->id : '');
    $this->actingAs($this->perencanaan)->call(match ($operation) {
        'create' => 'POST', 'update' => 'PUT', default => 'DELETE'
    }, $path, ['alasan' => str_repeat("\x01", 12)])->assertForbidden();
    $audit = AuditLog::where('tindakan', $event)->sole();
    expect(trim($audit->alasan))->not->toBe('')->and($audit->dasar_izin['keputusan'])->toBe('ditolak');
})->with([
    ['create', PermissionCodes::RENSTRA_CREATE, 'renstra.buat_ditolak'],
    ['update', PermissionCodes::RENSTRA_UPDATE, 'renstra.ubah_ditolak'],
    ['delete', PermissionCodes::RENSTRA_DELETE, 'renstra.hapus_ditolak'],
    ['attachment', PermissionCodes::BERKAS_DELETE, 'berkas.hapus_ditolak'],
]);

test('metadata create ditolak membatasi input raw dan UTF8 sebelum audit', function (bool $structured): void {
    $payload = $structured
        ? ['nama' => ['rahasia' => 'jangan salin'], 'tahun_mulai' => ['nested'], 'tahun_selesai' => ['nested']]
        : ['nama' => str_repeat('é', 300)."\xB1", 'tahun_mulai' => str_repeat('2', 2000), 'tahun_selesai' => "2029\xB1"];
    $this->actingAs($this->pembaca)->post('/renstra', $payload)->assertForbidden();
    $meta = AuditLog::where('tindakan', 'renstra.buat_ditolak')->sole()->nilai_baru;
    foreach (['nama', 'tahun_mulai', 'tahun_selesai'] as $field) {
        expect(is_array($meta[$field]))->toBeFalse();
        if ($structured) {
            expect($meta[$field])->toBeNull();
        } else {
            expect(mb_check_encoding((string) $meta[$field], 'UTF-8'))->toBeTrue();
            expect(mb_strlen((string) $meta[$field]))->toBeLessThanOrEqual(255);
        }
    }
})->with([true, false]);

test('guard parent berlaku pada penghapusan lampiran langsung', function (): void {
    $renstra = buatRenstra($this->perencanaan);
    $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Awal', 'uploaded_by' => $this->perencanaan->id]);
    $deny = tolakIzinRenstra($this->perencanaan, PermissionCodes::RENSTRA_DELETE);
    tolakIzinRenstra($this->perencanaan, PermissionCodes::RENSTRA_UPDATE);
    expect(fn () => mutasiRenstraLangsung('attachment', $this->perencanaan, $renstra, $berkas))->toThrow(AuthorizationException::class);
    expect($berkas->fresh()->trashed())->toBeFalse();
    $audit = AuditLog::where('tindakan', 'berkas.hapus_ditolak')->sole();
    expect($audit->dasar_izin['permission'])->toBe(PermissionCodes::RENSTRA_DELETE)->and($audit->dasar_izin['deny'])->toContain($deny->id);
});

test('lampiran induk lain menghasilkan 404 pada request dan batas mutasi', function (): void {
    $renstra = buatRenstra($this->perencanaan);
    $other = buatRenstra($this->perencanaan, ['kode' => 'RENSTRA-LAIN']);
    $berkas = $other->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Awal', 'uploaded_by' => $this->perencanaan->id]);
    $this->actingAs($this->perencanaan)->delete('/renstra/'.$renstra->id.'/berkas/'.$berkas->id, ['alasan' => 'Induk yang berbeda'])->assertNotFound();
    expect(fn () => mutasiRenstraLangsung('attachment', $this->perencanaan, $renstra, $berkas))->toThrow(ModelNotFoundException::class);
    expect($berkas->fresh()->trashed())->toBeFalse();
});

test('rollback audit mempertahankan DB dan berkas existing serta menghapus hanya unggahan baru', function (string $operation): void {
    $disk = Storage::fake('local');
    $renstra = buatRenstra($this->perencanaan);
    $path = 'berkas/renstra/'.$renstra->id.'/existing.pdf';
    $disk->put($path, 'Arsip existing');
    $berkas = $renstra->berkas()->create(['mode' => 'file', 'path' => $path, 'nama_asli' => 'existing.pdf', 'uploaded_by' => $this->perencanaan->id]);
    $before = $renstra->fresh()->getAttributes();
    $auditCount = AuditLog::count();
    $writer = app(WriteAuditLog::class);
    $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andReturnUsing(function (array $attributes) use ($writer): AuditLog {
        if ($attributes['tindakan'] !== 'berkas.unggah') {
            throw new RuntimeException('Audit fixture gagal');
        }

        return $writer->handle($attributes);
    });
    expect(fn () => mutasiRenstraLangsung($operation, $this->perencanaan, $renstra, $berkas, ['lampiran' => [['mode' => 'file', 'file' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')]]]))->toThrow(RuntimeException::class, 'Audit fixture gagal');
    expect($renstra->fresh()->getAttributes())->toBe($before)->and($berkas->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseCount('renstras', 1);
    $this->assertDatabaseCount('berkas', 1);
    $this->assertDatabaseCount('audit_log', $auditCount);
    expect($disk->allFiles('berkas/renstra'))->toBe([$path]);
})->with(['create', 'update', 'delete', 'attachment']);

test('kegagalan cleanup fisik aman dan tidak menutupi hasil utama', function (bool $compensate, bool $throws): void {
    $disk = Storage::fake('local');
    $renstra = buatRenstra($this->perencanaan);
    $path = 'berkas/renstra/'.$renstra->id.'/existing.pdf';
    $disk->put($path, 'Arsip existing');
    $berkas = $renstra->berkas()->create(['mode' => 'file', 'path' => $path, 'nama_asli' => 'existing.pdf', 'uploaded_by' => $this->perencanaan->id]);
    $failingDisk = Mockery::mock($disk)->makePartial();
    $delete = $failingDisk->shouldReceive('delete')->once();
    $throws ? $delete->andThrow(new RuntimeException('Pesan storage privat')) : $delete->andReturn(false);
    Storage::set('local', $failingDisk);
    Log::spy();
    if ($compensate) {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('Audit fixture utama'));
        expect(fn () => mutasiRenstraLangsung('update', $this->perencanaan, $renstra, $berkas, ['lampiran' => [['mode' => 'file', 'file' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')]]]))->toThrow(RuntimeException::class, 'Audit fixture utama');
        expect($berkas->fresh()->trashed())->toBeFalse();
    } else {
        mutasiRenstraLangsung('attachment', $this->perencanaan, $renstra, $berkas);
        expect(Berkas::withTrashed()->findOrFail($berkas->id)->trashed())->toBeTrue();
        expect(AuditLog::where('tindakan', 'berkas.hapus')->count())->toBe(1);
    }
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['jumlah_file'] === 1 && array_intersect(['path', 'exception', 'message'], array_keys($context)) === []);
})->with([[true, false], [true, true], [false, false], [false, true]]);

test('dependensi PK dan jadwal masing-masing mencegah penghapusan Renstra', function (string $dependency): void {
    $renstra = buatRenstra($this->perencanaan);
    if ($dependency === 'pk') {
        $renstra->renstraPk()->create(['tahun' => 2025, 'nomor_pk' => 'PK-2025', 'tanggal_pk' => '2025-01-01', 'created_by' => $this->perencanaan->id]);
    } else {
        $renstra->jadwalTahunan()->create(['tahun' => 2025, 'status' => 'draft', 'penutupan' => '2025-12-31']);
    }
    $this->actingAs($this->perencanaan)->delete('/renstra/'.$renstra->id, ['alasan' => 'Tidak boleh kehilangan dependensi'])->assertSessionHasErrors('renstra');
    expect($renstra->fresh())->not->toBeNull();
    expect(AuditLog::where('tindakan', 'renstra.hapus_ditolak')->sole()->nilai_baru['has_'.$dependency])->toBeTrue();
})->with(['pk', 'jadwal']);

test('penolakan unggah pada batas mutasi memakai keputusan asli tanpa resolusi ulang', function (string $operation): void {
    $renstra = buatRenstra($this->perencanaan);
    $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Awal', 'uploaded_by' => $this->perencanaan->id]);
    $real = app(PermissionResolver::class);
    $calls = 0;
    $this->mock(PermissionResolver::class)->shouldReceive('resolve')->andReturnUsing(function ($actor, string $code, ...$args) use ($real, &$calls): PermissionDecision {
        if ($code === PermissionCodes::BERKAS_UPLOAD) {
            $calls++;

            return new PermissionDecision($calls > 1, $code, ['alasan' => 'explicit_deny', 'sumber_allow' => ['roles' => [], 'grants' => []], 'deny' => ['deny-unggah-asli']]);
        }

        return $real->resolve($actor, $code, ...$args);
    });
    expect(fn () => mutasiRenstraLangsung($operation, $this->perencanaan, $renstra, $berkas, ['lampiran' => [['mode' => 'teks', 'isi_teks' => 'Ditolak']]]))->toThrow(AuthorizationException::class);
    $audit = AuditLog::where('tindakan', $operation === 'create' ? 'renstra.buat_ditolak' : 'renstra.ubah_ditolak')->sole();
    expect($calls)->toBe(1)->and($audit->dasar_izin['keputusan'])->toBe('ditolak')->and($audit->dasar_izin['deny'])->toBe(['deny-unggah-asli']);
    expect($audit->nilai_baru)->toBe(['alasan_penolakan' => 'berkas_upload_denied']);
    $this->assertDatabaseCount('renstras', 1);
    $this->assertDatabaseCount('berkas', 1);
})->with(['create', 'update']);

test('update tanpa perubahan tetap diaudit pada baris Renstra yang sama', function (): void {
    $renstra = buatRenstra($this->perencanaan);
    $berkas = $renstra->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Awal', 'uploaded_by' => $this->perencanaan->id]);
    mutasiRenstraLangsung('update', $this->perencanaan, $renstra, $berkas);
    $audit = AuditLog::where('tindakan', 'renstra.ubah_tanpa_perubahan')->sole();
    expect($audit->objek_id)->toBe($renstra->id)->and($audit->nilai_baru)->toEqual($audit->nilai_lama + ['hasil' => 'tidak_berubah']);
    expect($audit->nilai_baru['lampiran'][0]['uploaded_by'])->toBe($this->perencanaan->id);
    $this->assertDatabaseCount('renstras', 1);
});

test('metadata penolakan mempertahankan bentuk tahun yang sah menurut validator integer', function (): void {
    $this->actingAs($this->pembaca)->post('/renstra', ['nama' => 'Renstra é valid', 'tahun_mulai' => '+2025', 'tahun_selesai' => 2029.0])->assertForbidden();
    expect(AuditLog::where('tindakan', 'renstra.buat_ditolak')->sole()->nilai_baru)->toBe([
        'nama' => 'Renstra é valid', 'tahun_mulai' => '+2025', 'tahun_selesai' => 2029,
    ]);
});
