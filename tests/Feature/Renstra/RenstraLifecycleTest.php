<?php

use App\Actions\Renstra\ChangeRenstraStatus;
use App\Actions\Renstra\UpdateRenstraAction;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\AuditLogger;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenstraLifecycleTestCase extends TestCase
{
    public User $actor;

    public Renstra $renstra;
}

uses(RenstraLifecycleTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RegulasiPermissionSeeder::class);
    $role = Role::query()->where('kode', 'perencanaan')->firstOrFail();
    $role->permissions()->syncWithoutDetaching(Permission::query()
        ->whereIn('kode', RolePermissionPresets::forRole('perencanaan'))
        ->pluck('id')
        ->mapWithKeys(fn (string $id): array => [$id => ['id' => (string) Str::uuid(), 'created_at' => now()]])
        ->all());
    $this->actor = User::factory()->create(['status' => 'aktif']);
    $this->actor->roles()->attach($role->id, [
        'id' => (string) Str::uuid(),
        'sumber_pemberian' => 'manual',
        'diberikan_oleh' => $this->actor->id,
        'created_at' => now(),
    ]);
    $this->renstra = Renstra::query()->create([
        'kode' => 'RENSTRA-LIFECYCLE',
        'nama' => 'Renstra Perencanaan',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2029,
        'dasar_hukum' => 'Kepmen penetapan Renstra',
        'created_by' => $this->actor->id,
    ]);
});

/** @return array<string, mixed> */
function payloadLifecycleRevision(Renstra $renstra, array $overrides = []): array
{
    return array_replace([
        'nama' => 'Renstra Kebijakan Baru',
        'tahun_mulai' => $renstra->tahun_mulai,
        'tahun_selesai' => $renstra->tahun_selesai,
        'dasar_hukum' => $renstra->dasar_hukum,
        'expected_state' => $renstra->stateToken(),
        'alasan' => 'Penyesuaian terhadap kebijakan resmi yang baru.',
    ], $overrides);
}

test('master nonaktif menolak edit meskipun aktor memiliki izin update', function (): void {
    $this->renstra->update(['status' => Renstra::STATUS_NONAKTIF]);

    $this->actingAs($this->actor)
        ->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra))
        ->assertSessionHasErrors('renstra');

    expect($this->renstra->fresh()->nama)->toBe('Renstra Perencanaan');
});

test('revisi aktif mewajibkan nomor dan tanggal kebijakan selain alasan', function (): void {
    $this->renstra->update(['status' => Renstra::STATUS_AKTIF]);

    $this->actingAs($this->actor)
        ->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra))
        ->assertSessionHasErrors(['nomor_kebijakan', 'tanggal_kebijakan']);

    expect($this->renstra->fresh()->nama)->toBe('Renstra Perencanaan');
});

test('caller Action tetap wajib memberikan alasan revisi aktif', function (): void {
    $this->renstra->update(['status' => Renstra::STATUS_AKTIF]);
    $data = payloadLifecycleRevision($this->renstra, [
        'alasan' => '',
        'nomor_kebijakan' => '123/M/2026',
        'tanggal_kebijakan' => '2026-09-20',
    ]);

    expect(fn () => app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, $data))
        ->toThrow(ValidationException::class);
});

test('aktivasi menolak rentang persisted yang di luar batas valid', function (): void {
    $this->renstra->update(['tahun_mulai' => 1990, 'tahun_selesai' => 1994]);
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan", [
        'expected_state' => $this->renstra->stateToken(),
    ])->assertSessionHasErrors('tahun_mulai');
    expect($this->renstra->fresh()->status)->toBe(Renstra::STATUS_DRAFT);
});

test('caller Action menolak Regulasi nonaktif yang bukan rujukan lama', function (): void {
    $regulasi = Regulasi::query()->create([
        'jenis' => 'kepmen', 'nomor' => '123/M/2026', 'tahun' => 2026,
        'tentang' => 'Kebijakan pengujian', 'aktif' => false, 'created_by' => $this->actor->id,
    ]);
    expect(fn () => app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra, ['regulasi_id' => $regulasi->id])))
        ->toThrow(ValidationException::class);
    expect($this->renstra->fresh()->regulasi_id)->toBeNull();
});

test('input alasan malformed pada Action menghasilkan validasi dan satu audit denial', function (): void {
    $this->renstra->update(['status' => Renstra::STATUS_AKTIF]);
    expect(fn () => app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra, [
        'alasan' => ['invalid'], 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20',
    ])))->toThrow(ValidationException::class);
    expect(AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->count())->toBe(1);
});

test('audit penolakan izin mempertahankan isi alasan setelah NUL', function (string $permission): void {
    UserPermissionDeny::query()->create([
        'user_id' => $this->actor->id,
        'permission_id' => Permission::query()->where('kode', $permission)->sole()->id,
        'ditetapkan_oleh' => $this->actor->id,
        'alasan' => 'Fixture penolakan revisi',
    ]);
    $before = $this->renstra->fresh()->getAttributes();

    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra, [
        'alasan' => "Revisi\0uji\x1B", 'regulasi_id' => null,
    ]))->assertForbidden();

    $audit = AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->sole();
    expect($audit->alasan)->toBe('Revisiuji')
        ->and($audit->dasar_izin['keputusan'])->toBe('ditolak')
        ->and($this->renstra->fresh()->getAttributes())->toBe($before);
})->with(['renstra:update', 'regulasi:read']);

test('revisi menolak teks audit rusak pada request dan batas mutasi', function (string $field, string $value): void {
    $this->renstra->update(['status' => Renstra::STATUS_AKTIF]);
    $before = $this->renstra->fresh()->getAttributes();
    $payload = payloadLifecycleRevision($this->renstra, [
        'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20', $field => $value,
    ]);

    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", $payload)->assertSessionHasErrors($field);
    try {
        app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, $payload);
        $this->fail('Teks audit rusak wajib ditolak sebelum mutasi.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect($this->renstra->fresh()->getAttributes())->toBe($before)
        ->and(AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->count())->toBe(2);
})->with([
    'alasan NUL' => ['alasan', "Revisi\0uji"],
    'alasan UTF-8' => ['alasan', "Revisi\xC3\x28uji"],
    'nomor NUL' => ['nomor_kebijakan', "123\0M/2026"],
    'nomor UTF-8' => ['nomor_kebijakan', "123\xC3\x28M/2026"],
]);

test('alasan maksimal tetap menyimpan nomor dan tanggal kebijakan utuh', function (): void {
    $this->renstra->update(['status' => Renstra::STATUS_AKTIF]);
    $reason = str_repeat('a', 1000);
    $payload = payloadLifecycleRevision($this->renstra, [
        'alasan' => $reason, 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20',
    ]);

    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", $payload)->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('tindakan', 'renstra.ubah')->sole()->alasan)
        ->toBe($reason."\nRujukan: 123/M/2026 tanggal 2026-09-20");
});

test('lifecycle berurutan menyimpan status boolean dan audit server tanpa input alasan', function (): void {
    foreach (['aktifkan' => 'aktif', 'nonaktifkan' => 'nonaktif', 'arsipkan' => 'diarsipkan'] as $path => $status) {
        $before = $this->renstra->fresh();
        $this->actingAs($this->actor)->post("/renstra/{$before->id}/{$path}", [
            'expected_state' => $before->stateToken(), 'alasan' => 'Alasan palsu dari klien', 'status' => 'draft',
        ])->assertRedirect("/renstra/{$before->id}")->assertSessionHasNoErrors();
        $current = $before->fresh();
        expect($current->status)->toBe($status)->and($current->is_aktif)->toBe($status === 'aktif');
    }
    $audits = AuditLog::query()->where('objek_id', $this->renstra->id)->orderBy('waktu')->get();
    expect($audits)->toHaveCount(3);
    foreach ($audits as $audit) {
        expect($audit->dasar_izin['permission'])->toBe('renstra:update')
            ->and($audit->alasan)->not->toBe('Alasan palsu dari klien')
            ->and($audit->nilai_baru)->not->toHaveKey('expected_state');
    }
    $this->actingAs($this->actor)->get("/renstra/{$this->renstra->id}/edit")->assertForbidden();
});

test('kelengkapan Sasaran adalah warning nonblocking dan flash sukses berada di envelope native', function (): void {
    $id = $this->renstra->id;
    $this->actingAs($this->actor)->get("/renstra/{$id}")->assertInertia(fn (Assert $page) => $page
        ->where('can.activate', true)->has('lifecycle.warnings', 1));
    $this->actingAs($this->actor)->post("/renstra/{$id}/aktifkan", ['expected_state' => $this->renstra->stateToken()])->assertSessionHasNoErrors();
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])->get("/renstra/{$id}");
    $response->assertJsonPath('flash.success', 'Renstra berhasil diaktifkan.')->assertJsonPath('props.flash.success', null);
});

test('aktivasi menolak dasar hukum kosong dan mencatat alasan denial', function (): void {
    $this->renstra->update(['dasar_hukum' => '   ']);
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan", ['expected_state' => $this->renstra->stateToken()])
        ->assertSessionHasErrors('dasar_hukum');
    expect($this->renstra->fresh()->status)->toBe('draft');
    expect(AuditLog::query()->where('tindakan', 'renstra.activate_ditolak')->sole()->nilai_baru['alasan_penolakan'])->toBe('dasar_hukum_kosong');
});

test('aktivasi memakai rentang inklusif dan menerima rentang bersebelahan', function (int $start, int $end, bool $overlap): void {
    Renstra::query()->create([
        'kode' => 'RENSTRA-AKTIF-LAIN', 'nama' => 'Renstra aktif lain', 'tahun_mulai' => $start, 'tahun_selesai' => $end,
        'status' => 'aktif', 'dasar_hukum' => 'Kepmen lama', 'created_by' => $this->actor->id,
    ]);
    $response = $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan", ['expected_state' => $this->renstra->stateToken()]);
    if ($overlap) {
        $response->assertSessionHasErrors('tahun_mulai');
    } else {
        $response->assertSessionHasNoErrors();
    }
    expect($this->renstra->fresh()->status)->toBe($overlap ? 'draft' : 'aktif');
})->with([[2021, 2025, true], [2029, 2033, true], [2030, 2034, false]]);

test('transisi yang melompati state atau membuka kembali arsip ditolak', function (string $status, string $path): void {
    $this->renstra->update(['status' => $status]);
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/{$path}", ['expected_state' => $this->renstra->stateToken()])
        ->assertSessionHasErrors('renstra');
    expect($this->renstra->fresh()->status)->toBe($status);
})->with([['draft', 'arsipkan'], ['aktif', 'arsipkan'], ['nonaktif', 'aktifkan'], ['diarsipkan', 'aktifkan']]);

test('nonaktivasi menolak Jadwal aktif tanpa mengubah Jadwal atau master', function (): void {
    $this->renstra->update(['status' => 'aktif']);
    $jadwal = JadwalTahunan::query()->create(['renstra_id' => $this->renstra->id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => 'aktif']);
    $before = $jadwal->fresh()->getAttributes();
    $this->actingAs($this->actor)->get("/renstra/{$this->renstra->id}")->assertInertia(fn (Assert $page) => $page->where('lifecycle.nonactivation_blocked', true));
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/nonaktifkan", ['expected_state' => $this->renstra->stateToken()])
        ->assertSessionHasErrors('renstra');
    expect($this->renstra->fresh()->status)->toBe('aktif')->and($jadwal->fresh()->getAttributes())->toBe($before);
    $audit = AuditLog::query()->where('tindakan', 'renstra.deactivate_ditolak')->sole();
    expect($audit->nilai_baru['alasan_penolakan'])->toBe('jadwal_aktif')->and($audit->alasan)->toBe('Permintaan perubahan status Renstra ditolak.');
});

test('revisi range mempertahankan seluruh tahun Jadwal dari semua status', function (string $status): void {
    $this->renstra->update(['status' => 'aktif']);
    JadwalTahunan::query()->create(['renstra_id' => $this->renstra->id, 'tahun' => 2025, 'penutupan' => '2025-12-31', 'status' => $status]);
    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra, [
        'tahun_mulai' => 2026, 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20',
    ]))->assertSessionHasErrors('tahun_mulai');
    expect($this->renstra->fresh()->tahun_mulai)->toBe(2025);
})->with(['draft', 'aktif', 'ditutup']);

test('revisi rentang mencakup tahun PK meskipun belum memiliki Jadwal', function (int $year, int $start, int $end, bool $rejected, string $status): void {
    $this->renstra->update(['status' => $status]);
    $pk = RenstraPk::query()->create([
        'renstra_id' => $this->renstra->id, 'tahun' => $year, 'nomor_pk' => 'PK-REGRESI',
        'tanggal_pk' => '2025-01-01', 'created_by' => $this->actor->id,
    ]);
    $before = $pk->fresh()->getAttributes();
    $response = $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra, [
        'tahun_mulai' => $start, 'tahun_selesai' => $end, 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20',
    ]));
    if ($rejected) {
        $response->assertSessionHasErrors('tahun_mulai');
        expect($this->renstra->fresh()->tahun_mulai)->toBe(2025)->and($this->renstra->fresh()->tahun_selesai)->toBe(2029);
        expect(AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->sole()->nilai_baru['alasan_penolakan'])->toBe('tahun_pk_di_luar_rentang');
        expect(AuditLog::query()->where('tindakan', 'renstra.ubah')->count())->toBe(0);
    } else {
        $response->assertSessionHasNoErrors();
        expect($this->renstra->fresh()->tahun_mulai)->toBe($start)->and($this->renstra->fresh()->tahun_selesai)->toBe($end);
    }
    expect($pk->fresh()->getAttributes())->toBe($before)->and($pk->jadwalTahunan()->exists())->toBeFalse();
})->with([[2025, 2026, 2029, true, 'aktif'], [2029, 2025, 2028, true, 'draft'], [2025, 2024, 2030, false, 'aktif']]);

test('arsip nonaktif tetap tanpa syarat tambahan dan mempertahankan Jadwal existing', function (): void {
    $this->renstra->update(['status' => 'nonaktif']);
    $jadwal = JadwalTahunan::query()->create(['renstra_id' => $this->renstra->id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => 'aktif']);
    $before = $jadwal->fresh()->getAttributes();
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/arsipkan", [
        'expected_state' => $this->renstra->stateToken(),
    ])->assertSessionHasNoErrors();
    expect($this->renstra->fresh()->status)->toBe('diarsipkan')->and($jadwal->fresh()->getAttributes())->toBe($before);
    expect(AuditLog::query()->where('tindakan', 'renstra.archive')->count())->toBe(1);
});

test('tanggal kalender tidak valid menolak revisi resmi', function (string $date): void {
    $this->renstra->update(['status' => 'aktif']);
    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra, [
        'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => $date,
    ]))->assertSessionHasErrors('tanggal_kebijakan');
    expect($this->renstra->fresh()->nama)->toBe('Renstra Perencanaan');
})->with(['2026-02-29', '2026-02-31']);

test('state stale dan token hilang menolak mutation serta retry sukses tidak menggandakan audit', function (): void {
    $token = $this->renstra->stateToken();
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan")->assertSessionHasErrors('expected_state');
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan", ['expected_state' => $token])->assertSessionHasNoErrors();
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan", ['expected_state' => $token])->assertSessionHasErrors('expected_state');
    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra, [
        'expected_state' => $token, 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20',
    ]))->assertSessionHasErrors('expected_state');
    expect(AuditLog::query()->where('tindakan', 'renstra.activate')->count())->toBe(1);
});

test('revisi no-op tidak menyimpan master dan mengaudit hasil tidak berubah', function (): void {
    $this->renstra->update(['status' => 'aktif']);
    $before = $this->renstra->fresh()->getAttributes();
    $payload = payloadLifecycleRevision($this->renstra, ['nama' => $this->renstra->nama, 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2028-02-29']);
    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", $payload)->assertSessionHasNoErrors();
    expect($this->renstra->fresh()->getAttributes())->toBe($before);
    expect(AuditLog::query()->where('tindakan', 'renstra.ubah')->count())->toBe(0);
    $audit = AuditLog::query()->where('tindakan', 'renstra.ubah_tanpa_perubahan')->sole();
    expect($audit->nilai_baru['hasil'])->toBe('tidak_berubah')->and($audit->alasan)->toContain('123/M/2026', '2028-02-29');
});

test('deny efektif menolak lifecycle dan menyembunyikan capability', function (): void {
    UserPermissionDeny::query()->create([
        'user_id' => $this->actor->id, 'permission_id' => Permission::query()->where('kode', 'renstra:update')->sole()->id,
        'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Fixture denial lifecycle',
    ]);
    $this->actingAs($this->actor)->get("/renstra/{$this->renstra->id}")->assertInertia(fn (Assert $page) => $page
        ->where('can.activate', false)->where('can.update', false));
    $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/aktifkan", ['expected_state' => $this->renstra->stateToken()])->assertForbidden();
    expect(AuditLog::query()->where('tindakan', 'renstra.lifecycle_ditolak')->count())->toBe(1);
});

test('kegagalan audit me-rollback perubahan domain', function (string $operation): void {
    $before = $this->renstra->fresh()->getAttributes();
    $auditCount = AuditLog::query()->count();
    $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Fixture audit gagal'));
    $mutation = $operation === 'status'
        ? fn () => app(ChangeRenstraStatus::class)->execute($this->renstra, $this->actor, 'activate', $this->renstra->stateToken())
        : fn () => app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra));
    expect($mutation)->toThrow(RuntimeException::class, 'Fixture audit gagal');
    expect($this->renstra->fresh()->getAttributes())->toBe($before);
    expect(AuditLog::query()->count())->toBe($auditCount);
})->with(['status', 'revision']);

test('revisi master dan lifecycle tidak menulis graph Jadwal snapshot atau Pengukuran existing', function (): void {
    $this->renstra->update(['status' => 'aktif']);
    $ids = array_map(fn () => (string) Str::uuid(), array_fill_keys(['unit', 'sasaran', 'indikator', 'komponen', 'periode', 'jadwal', 'snapshot', 'snapshot_komponen', 'pengukuran', 'pengukuran_versi'], null));
    DB::table('unit')->insert(['id' => $ids['unit'], 'nama' => 'Unit fixture histori', 'created_by' => $this->actor->id, 'created_at' => now()]);
    DB::table('sasaran_strategis')->insert(['id' => $ids['sasaran'], 'renstra_id' => $this->renstra->id, 'kode' => 'SS-TEST', 'deskripsi' => 'Sasaran historis']);
    DB::table('indikator_kinerjas')->insert([
        'id' => $ids['indikator'],
        'sasaran_strategis_id' => $ids['sasaran'],
        'unit_id' => $ids['unit'],
        'kode' => 'IK-TEST',
        'nama' => 'Indikator master',
        'satuan' => 'persen',
        'status' => 'aktif',
        'tahun_mulai_berlaku' => 2025,
        'created_by' => $this->actor->id,
        'created_by_role' => 'perencanaan',
    ]);
    DB::table('indikator_komponen')->insert(['id' => $ids['komponen'], 'indikator_id' => $ids['indikator'], 'kode' => 'K1', 'label' => 'Komponen master', 'peran' => 'penjumlah', 'urutan' => 1, 'created_by' => $this->actor->id]);
    DB::table('periode')->insert(['id' => $ids['periode'], 'nama' => 'TW I', 'urutan' => 1]);
    DB::table('jadwal_tahunan')->insert(['id' => $ids['jadwal'], 'renstra_id' => $this->renstra->id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => 'ditutup']);
    DB::table('jadwal_snapshot')->insert([
        'id' => $ids['snapshot'], 'jadwal_id' => $ids['jadwal'], 'indikator_id' => $ids['indikator'],
        'periode_mulai_id' => $ids['periode'], 'unit_id' => $ids['unit'], 'nama' => 'Nama historis beku',
        'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => '70.123456789012',
    ]);
    DB::table('jadwal_snapshot_komponen')->insert([
        'id' => $ids['snapshot_komponen'], 'jadwal_snapshot_id' => $ids['snapshot'], 'komponen_id' => $ids['komponen'],
        'kode' => 'K1', 'label' => 'Komponen beku', 'peran' => 'penjumlah', 'bobot' => '1.000000000001', 'urutan' => 1,
    ]);
    DB::table('pengukuran_kinerjas')->insert([
        'id' => $ids['pengukuran'], 'indikator_id' => $ids['indikator'], 'tahun' => 2026, 'periode_id' => $ids['periode'],
        'jadwal_snapshot_id' => $ids['snapshot'], 'nilai' => '71.123456789012', 'sumber_nilai' => 'manual',
        'status_perhitungan' => 'terhitung', 'status_alur' => 'disahkan', 'created_by' => $this->actor->id,
    ]);
    DB::table('pengukuran_versi')->insert([
        'id' => $ids['pengukuran_versi'], 'pengukuran_id' => $ids['pengukuran'], 'jadwal_snapshot_id' => $ids['snapshot'],
        'nomor' => 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan',
        'dasar_izin_pengajuan' => json_encode(['permission' => 'fixture']), 'snapshot' => json_encode(['nama' => 'Nama historis beku', 'nilai' => '71.123456789012']),
    ]);
    $tables = ['sasaran_strategis', 'indikator_kinerjas', 'indikator_komponen', 'jadwal_tahunan', 'jadwal_snapshot', 'jadwal_snapshot_komponen', 'pengukuran_kinerjas', 'pengukuran_versi'];
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $this->actingAs($this->actor)->put("/renstra/{$this->renstra->id}", payloadLifecycleRevision($this->renstra, [
        'tahun_selesai' => 2030, 'nomor_kebijakan' => '123/M/2026', 'tanggal_kebijakan' => '2026-09-20',
    ]))->assertSessionHasNoErrors();
    foreach (['nonaktifkan', 'arsipkan'] as $path) {
        $this->actingAs($this->actor)->post("/renstra/{$this->renstra->id}/{$path}", ['expected_state' => $this->renstra->fresh()->stateToken()])->assertSessionHasNoErrors();
        foreach ($tables as $table) {
            expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($before[$table]);
        }
    }
    $audit = AuditLog::query()->where('tindakan', 'renstra.ubah')->sole();
    expect($audit->nilai_lama['nama'])->toBe('Renstra Perencanaan')->and($audit->nilai_baru['nama'])->toBe('Renstra Kebijakan Baru')
        ->and($audit->alasan)->toContain('123/M/2026', '2026-09-20')->and($audit->dasar_izin)->not->toBeNull();
});

test('rujukan lama nonaktif tetap boleh dipertahankan dan token berasal dari master sebelum masking', function (): void {
    $regulasi = Regulasi::query()->create([
        'jenis' => 'kepmen', 'nomor' => '123/M/2026', 'tahun' => 2026, 'tentang' => 'Rujukan lama',
        'aktif' => false, 'created_by' => $this->actor->id,
    ]);
    $this->renstra->update(['regulasi_id' => $regulasi->id]);
    app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra, ['regulasi_id' => $regulasi->id]));
    UserPermissionDeny::query()->create([
        'user_id' => $this->actor->id, 'permission_id' => Permission::query()->where('kode', 'regulasi:read')->sole()->id,
        'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Fixture masking',
    ]);
    $this->actingAs($this->actor)->get("/renstra/{$this->renstra->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->where('renstra.regulasi_id', null)->where('expected_state', $this->renstra->fresh()->stateToken()));
    $master = $this->renstra->fresh();
    $token = $master->stateToken();
    // Mengetahui kandidat UUID Regulasi tidak cukup untuk mencocokkan token dari props.
    expect($token)->not->toBe(hash('sha256', json_encode($master->masterAttributes(), JSON_THROW_ON_ERROR)));
    expect($token)->toMatch('/^[a-f0-9]{64}$/')->toBe($master->stateToken());
    $this->actingAs($this->actor)->put("/renstra/{$master->id}", payloadLifecycleRevision($master))->assertSessionHasNoErrors();
    expect($master->fresh()->regulasi_id)->toBe($regulasi->id);
});

test('token state Renstra bergantung pada kunci server', function (): void {
    $token = $this->renstra->stateToken();
    $original = Crypt::getFacadeRoot();
    try {
        Crypt::swap(new Encrypter(str_repeat('R', 32), 'AES-256-CBC'));
        expect($this->renstra->stateToken())->not->toBe($token);
    } finally {
        Crypt::swap($original);
    }
});

test('recheck setelan unggahan pada service mencatat satu denial setelah rollback', function (): void {
    Pengaturan::query()->create(['kunci' => 'berkas.unggahan_aktif', 'nilai' => 'false', 'tipe' => 'boolean', 'grup' => 'berkas']);
    $file = UploadedFile::fake()->create('naskah.pdf', 1, 'application/pdf');
    expect(fn () => app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra, [
        'lampiran' => [['mode' => 'file', 'file' => $file]],
    ])))->toThrow(ValidationException::class);
    expect($this->renstra->fresh()->nama)->toBe('Renstra Perencanaan')->and($this->renstra->berkas()->count())->toBe(0);
    expect(AuditLog::query()->where('tindakan', 'renstra.ubah_ditolak')->count())->toBe(1);
});

test('caller Action tidak dapat menyimpan tahun kosong sebagai nol', function (): void {
    expect(fn () => app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra, ['tahun_mulai' => '', 'tahun_selesai' => ''])))
        ->toThrow(ValidationException::class);
    expect($this->renstra->fresh()->tahun_mulai)->toBe(2025);
});

test('caller Action menormalkan FK Regulasi kosong sebagai null', function (): void {
    app(UpdateRenstraAction::class)->handle($this->actor, $this->renstra, payloadLifecycleRevision($this->renstra, ['regulasi_id' => '']));
    expect($this->renstra->fresh()->regulasi_id)->toBeNull();
});
