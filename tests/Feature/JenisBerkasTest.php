<?php

namespace Tests\Feature;

use App\Actions\Access\CreateDeny;
use App\Actions\Audit\WriteAuditLog;
use App\Actions\JenisBerkas\CreateJenisBerkasAction;
use App\Actions\JenisBerkas\DeleteJenisBerkasAction;
use App\Actions\JenisBerkas\UpdateJenisBerkasAction;
use App\Actions\Pengukuran\EvaluateEvidence;
use App\Http\Requests\DeleteJenisBerkasRequest;
use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class JenisBerkasTest extends TestCase
{
    use RefreshDatabase;

    protected User $perencanaan;

    protected User $admin;

    protected User $pegawai;

    protected IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->perencanaan = $this->userWithRole('perencanaan');
        $this->admin = $this->userWithRole('admin');
        $this->pegawai = $this->userWithRole('pegawai');

        $unit = Unit::create(['nama' => 'Unit Pengujian', 'created_by' => $this->perencanaan->id]);
        $renstra = Renstra::create([
            'kode' => 'R-UJI',
            'created_by' => $this->perencanaan->id,
            'nama' => 'Renstra Uji',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'S-UJI',
            'deskripsi' => 'Sasaran Uji',
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI',
            'nama' => 'Indikator Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);
    }

    protected function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    public function test_perencanaan_can_create_valid_jenis_berkas(): void
    {
        $payload = [
            'nama' => 'Laporan Capaian Triwulan',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'wajib' => true,
            'keterangan' => 'Dokumen resmi ditandatangani pimpinan unit',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'semua_mode_wajib' => false,
            'urutan' => 1,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 5120,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');

        $this->assertDatabaseHas('jenis_berkas', [
            'nama' => 'Laporan Capaian Triwulan',
            'tahap' => 'pengukuran',
            'wajib' => true,
        ]);

        $audit = AuditLog::where('tindakan', 'jenis_berkas.buat')->latest('waktu')->first();
        $this->assertNotNull($audit);
        $this->assertNotNull($audit->dasar_izin);
        $this->assertSame('allow', $audit->dasar_izin['reason']);
        $this->assertSame('jenis_berkas:create', $audit->dasar_izin['permission']);
    }

    /**
     * TEST-2: Tanpa mode diizinkan ditolak.
     */
    public function test_submit_without_allowed_mode_is_rejected(): void
    {
        $payload = [
            'nama' => 'Persyaratan Tanpa Mode',
            'tahap' => 'pengukuran',
            'izinkan_file' => false,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertSessionHasErrors('modes');
    }

    /**
     * TEST-3: semua_mode_wajib tersimpan dan diverifikasi pemenuhannya oleh EvaluateEvidence.
     */
    public function test_semua_mode_wajib_semantics(): void
    {
        $payload = [
            'nama' => 'Bukti Dukung Lengkap',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
            'semua_mode_wajib' => true,
            'wajib' => true,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');

        $this->assertDatabaseHas('jenis_berkas', [
            'nama' => 'Bukti Dukung Lengkap',
            'semua_mode_wajib' => true,
        ]);

        $jb = JenisBerkas::where('nama', 'Bukti Dukung Lengkap')->firstOrFail();

        // Setup context pengukuran untuk evaluasi pemenuhan bukti
        $renstra = Renstra::where('kode', 'R-UJI')->firstOrFail();
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-UJI',
            'tanggal_pk' => '2026-01-01',
            'created_by' => $this->perencanaan->id,
        ]);
        $periode = Periode::create([
            'nama' => 'Triwulan I',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        $context = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $this->indikator->id,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $this->indikator->unit_id,
            'nama' => 'Indikator Uji',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 70,
        ]);
        $pengukuran = PengukuranKinerja::create([
            'indikator_id' => $this->indikator->id,
            'tahun' => 2026,
            'periode_id' => $periode->id,
            'jadwal_snapshot_id' => $context->id,
            'sumber_nilai' => 'manual',
            'created_by' => $this->perencanaan->id,
        ]);

        $evaluator = app(EvaluateEvidence::class);

        // 1. Belum ada bukti dukung -> terpenuhi = false, mode kurang = file, tautan, teks
        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertFalse($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertEqualsCanonicalizing(['file', 'tautan', 'teks'], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // 2. Parsial: hanya mode teks disediakan -> terpenuhi tetap false karena semua_mode_wajib = true
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'teks',
            'isi_teks' => 'Catatan pemenuhan narasi',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertFalse($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertEqualsCanonicalizing(['file', 'tautan'], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // 3. Parsial: mode tautan ditambahkan -> terpenuhi tetap false (masih kurang mode file)
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'tautan',
            'tautan' => 'https://example.com/laporan-kinerja',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertFalse($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame(['file'], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // 4. Lengkap: mode file juga diunggah -> terpenuhi = true, mode kurang kosong
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'file',
            'nama_asli' => 'laporan.pdf',
            'path' => 'evidence/laporan.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 2048,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertTrue($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame([], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);
        $this->assertEqualsCanonicalizing(['file', 'tautan', 'teks'], $evaluations[$jb->id]['pemenuhan']['mode_terpenuhi']);
    }

    /**
     * Q43: alasan perubahan opsional, sedangkan audit sebelum/sesudah dan dasar izin tetap wajib.
     */
    #[DataProvider('optionalUpdateReasons')]
    public function test_update_with_optional_reason_keeps_audit_trail(array $reason): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Awal',
            'tahap' => 'pengukuran',
            'wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", $reason + [
            'nama' => 'Laporan Diperbarui',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect('/jenis-berkas');
        $this->assertSame('Laporan Diperbarui', $jb->fresh()->nama);
        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')->where('objek_id', $jb->id)->sole();
        $expectedReason = trim((string) ($reason['alasan'] ?? ''));
        $this->assertSame($expectedReason !== '' ? $expectedReason : 'Perubahan persyaratan berkas tanpa alasan tambahan.', $audit->alasan);
        $this->assertSame('Laporan Awal', $audit->nilai_lama['nama']);
        $this->assertSame('Laporan Diperbarui', $audit->nilai_baru['nama']);
        $this->assertSame($this->perencanaan->id, $audit->actor_id);
        $this->assertNotNull($audit->waktu);
        $this->assertSame('jenis_berkas:update', $audit->dasar_izin['permission']);
    }

    public static function optionalUpdateReasons(): array
    {
        return ['tanpa field' => [[]], 'null' => [['alasan' => null]], 'kosong' => [['alasan' => '']], 'singkat' => [['alasan' => 'OK']]];
    }

    public function test_update_and_delete_record_user_reason_and_audit_trail(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Awal', 'tahap' => 'pengukuran', 'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Update dengan alasan berhasil dan tercatat di audit_log beserta dasar_izin
        $successUpdate = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Diperbarui',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => false,
            'alasan' => 'Penyesuaian kebutuhan bukti mode tautan',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $successUpdate->assertRedirect('/jenis-berkas');

        $auditUpdate = AuditLog::where('tindakan', 'jenis_berkas.ubah')->where('objek_id', $jb->id)->first();
        $this->assertNotNull($auditUpdate);
        $this->assertSame('Penyesuaian kebutuhan bukti mode tautan', $auditUpdate->alasan);
        $this->assertNotNull($auditUpdate->dasar_izin);
        $this->assertSame('allow', $auditUpdate->dasar_izin['reason']);
        $this->assertSame('jenis_berkas:update', $auditUpdate->dasar_izin['permission']);

        // Delete dengan alasan berhasil dan tercatat di audit_log beserta dasar_izin
        $deleteResponse = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Penghapusan katalog persyaratan yang sudah tidak relevan',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);
        $deleteResponse->assertRedirect('/jenis-berkas');

        $auditDelete = AuditLog::where('tindakan', 'jenis_berkas.hapus')->where('objek_id', $jb->id)->first();
        $this->assertNotNull($auditDelete);
        $this->assertSame('Penghapusan katalog persyaratan yang sudah tidak relevan', $auditDelete->alasan);
        $this->assertNotNull($auditDelete->dasar_izin);
        $this->assertSame('allow', $auditDelete->dasar_izin['reason']);
        $this->assertSame('jenis_berkas:delete', $auditDelete->dasar_izin['permission']);
    }

    /**
     * TEST-5: Otorisasi fail closed: Admin dan Pegawai ditolak (403), dan percobaan mutasi sensitif dicatat audit.
     */
    public function test_unauthorized_users_cannot_mutate_jenis_berkas(): void
    {
        $payload = [
            'nama' => 'Mencoba Menyusup',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
        ];

        $responseAdmin = $this->actingAs($this->admin)->post('/jenis-berkas', $payload);
        $responseAdmin->assertStatus(403);

        $responsePegawai = $this->actingAs($this->pegawai)->post('/jenis-berkas', $payload);
        $responsePegawai->assertStatus(403);

        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Terlindungi',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Percobaan update oleh pegawai ditolak 403 dan dicatat di audit log
        $updateResponse = $this->actingAs($this->pegawai)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pembaruan Tidak Sah',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Mencoba ubah tanpa hak',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $updateResponse->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'jenis_berkas.ubah_ditolak',
            'objek_id' => $jb->id,
        ]);

        // Percobaan delete oleh pegawai ditolak 403 dan dicatat di audit log
        $deleteResponse = $this->actingAs($this->pegawai)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus tanpa hak',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);
        $deleteResponse->assertStatus(403);
        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'jenis_berkas.hapus_ditolak',
            'objek_id' => $jb->id,
        ]);
    }

    /**
     * TEST-6: Mutasi dan audit dibungkus dalam transaksi atomik (rollback jika audit gagal).
     */
    #[DataProvider('mutationMethods')]
    public function test_mutation_and_audit_are_atomic_in_single_transaction(string $method, string $suffix): void
    {
        $actor = $this->userWithRole('superadmin');
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan sebelum audit gagal', 'tahap' => 'pengukuran', 'izinkan_file' => true,
            'format_diizinkan' => 'pdf', 'ukuran_maks_kb' => 1024, 'created_by' => $actor->id,
        ]);
        $before = $jb->fresh()->getAttributes();
        $auditCount = AuditLog::count();
        $this->withoutExceptionHandling();
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('Audit system down'));
        $payload = [
            'nama' => 'Laporan Harusnya Rollback',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'alasan' => 'Alasan perubahan fixture audit',
            'expected_updated_at' => $jb->updated_at->toISOString(),
            'ukuran_maks_kb' => 2048,
        ];

        try {
            $this->actingAs($actor)->call($method, '/jenis-berkas'.($method === 'POST' ? '' : '/'.$jb->id).$suffix, $payload);
            $this->fail('Kegagalan audit harus diteruskan dan membatalkan mutasi.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit system down', $e->getMessage());
        }

        $this->assertDatabaseCount('jenis_berkas', 1);
        $this->assertSame($before, $jb->fresh()->getAttributes());
        $this->assertSame($auditCount, AuditLog::count());
    }

    public static function mutationMethods(): array
    {
        return [
            'create' => ['POST', ''],
            'update' => ['PUT', ''],
            'delete' => ['DELETE', ''],
        ];
    }

    #[DataProvider('mutationMethods')]
    public function test_direct_action_caller_cannot_mutate_with_valid_payload_after_permission_revocation(string $method, string $suffix): void
    {
        $actor = $this->userWithRole('superadmin');
        $permission = match ($method) {
            'POST' => 'jenis_berkas:create',
            'PUT' => 'jenis_berkas:update',
            'DELETE' => 'jenis_berkas:delete',
        };
        $this->assertTrue(app(PermissionResolver::class)->allows($actor, $permission));
        $deny = app(CreateDeny::class)->handle($this->admin, $actor->id, Permission::where('kode', $permission)->value('id'), null, 'Pencabutan sebelum pemanggilan Action');
        $jb = JenisBerkas::create(['nama' => 'Persyaratan awal', 'tahap' => 'pengukuran', 'izinkan_file' => true, 'created_by' => $this->perencanaan->id]);
        $before = $jb->fresh()->getAttributes();
        $data = ['alasan' => str_repeat("\x01", 10), 'expected_updated_at' => $jb->updated_at->toISOString()];
        try {
            match ($method) {
                'POST' => app(CreateJenisBerkasAction::class)->handle($actor, ['nama' => 'Persyaratan baru', 'tahap' => 'pengukuran', 'izinkan_file' => true]),
                'PUT' => app(UpdateJenisBerkasAction::class)->handle($actor, $jb->id, $data + ['nama' => 'Persyaratan baru', 'tahap' => 'pengukuran', 'izinkan_file' => true]),
                'DELETE' => app(DeleteJenisBerkasAction::class)->handle($actor, $jb->id, $data),
            };
            $this->fail('Pemanggil langsung wajib diperiksa oleh Action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('jenis_berkas', 1);
        $this->assertSame($before, $jb->fresh()->getAttributes());
        $audit = AuditLog::where('actor_id', $actor->id)->sole();
        $this->assertFalse($audit->dasar_izin['allowed']);
        $this->assertContains($deny->id, $audit->dasar_izin['denies']);
        $this->assertNotSame('', trim($audit->alasan));
        $this->assertStringNotContainsString("\x01", $audit->alasan);
    }

    #[DataProvider('requestDenials')]
    public function test_initial_denial_audits_the_decision_that_rejected_the_request(string $requestClass, string $permission): void
    {
        $denied = ['allowed' => false, 'permission' => $permission, 'reason' => 'no_allow', 'roles' => [], 'grants' => [], 'denies' => []];
        $calls = 0;
        $this->partialMock(PermissionResolver::class)->shouldReceive('decide')->andReturnUsing(function () use ($denied, &$calls): array {
            $calls++;

            return $calls === 1 ? $denied : [...$denied, 'allowed' => true, 'reason' => 'allow'];
        });
        $request = $requestClass::create('/jenis-berkas/'.Str::uuid(), 'DELETE', ['alasan' => ['tidak valid']]);
        $route = (new Route('DELETE', 'jenis-berkas/{id}', fn () => null))->bind($request);
        $request->setContainer($this->app)->setRedirector(app(Redirector::class));
        $request->setUserResolver(fn () => $this->pegawai);
        $request->setRouteResolver(fn () => $route);
        try {
            $request->validateResolved();
            $this->fail('FormRequest harus menolak keputusan pertama.');
        } catch (AuthorizationException) {
            $this->assertEquals($denied, AuditLog::where('actor_id', $this->pegawai->id)->sole()->dasar_izin);
            $this->assertSame(1, $calls, 'Audit menggunakan keputusan penolakan yang sama, bukan resolusi kedua.');
        }
    }

    public static function requestDenials(): array
    {
        return [
            [StoreJenisBerkasRequest::class, 'jenis_berkas:create'],
            [UpdateJenisBerkasRequest::class, 'jenis_berkas:update'],
            [DeleteJenisBerkasRequest::class, 'jenis_berkas:delete'],
        ];
    }

    #[DataProvider('unsafeDeniedReasons')]
    public function test_denial_keeps_safe_bounded_reason_and_domain_unchanged(string $method, string $suffix, array $input, string $expected): void
    {
        $jb = JenisBerkas::create(['nama' => 'Persyaratan terlindungi', 'tahap' => 'pengukuran', 'izinkan_file' => true, 'created_by' => $this->perencanaan->id]);
        $before = $jb->fresh()->getAttributes();
        $auditCount = AuditLog::count();

        $this->actingAs($this->pegawai)->call($method, '/jenis-berkas'.($method === 'POST' ? '' : '/'.$jb->id).$suffix, $input)->assertForbidden();

        $audit = AuditLog::where('actor_id', $this->pegawai->id)->sole();
        $this->assertSame($expected, $audit->alasan);
        $this->assertSame($auditCount + 1, AuditLog::count());
        $this->assertFalse($audit->dasar_izin['allowed']);
        $this->assertSame('no_allow', $audit->dasar_izin['reason']);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('manual', $audit->sumber);
        $this->assertSame($before, $jb->fresh()->getAttributes());
        $this->assertDatabaseCount('jenis_berkas', 1);
    }

    public static function unsafeDeniedReasons(): array
    {
        return [
            'create UTF-8 rusak' => ['POST', '', ['alasan' => "Alasan\xFF lanjutan"], 'Alasan? lanjutan'],
            'update NUL' => ['PUT', '', ['alasan' => "Alasan\0 lanjutan"], 'Alasan lanjutan'],
            'delete terlalu panjang' => ['DELETE', '', ['alasan' => str_repeat('é', 1100)], str_repeat('é', 1000)],
            'create nama mentah' => ['POST', '', ['nama' => "Bukti\0\xFF\x01".str_repeat('é', 300)], 'Percobaan penambahan persyaratan jenis berkas ditolak karena tidak memiliki izin. (Nama: Bukti?'.str_repeat('é', 249).')'],
        ];
    }

    #[DataProvider('reasonRequiredMutations')]
    public function test_authorized_control_only_reason_returns_field_error_without_mutation(string $method, string $suffix): void
    {
        $actor = $this->userWithRole('superadmin');
        $jb = JenisBerkas::create(['nama' => 'Persyaratan awal', 'tahap' => 'pengukuran', 'izinkan_file' => true, 'created_by' => $actor->id]);
        $before = $jb->fresh()->getAttributes();
        $auditCount = AuditLog::count();
        $payload = ['alasan' => str_repeat("\x01", 10), 'expected_updated_at' => $jb->updated_at->toISOString()];
        if ($method === 'PUT') {
            $payload += ['nama' => 'Persyaratan diubah', 'tahap' => 'pengukuran', 'izinkan_file' => true];
        }

        $this->actingAs($actor)->from('/jenis-berkas')->withHeader('X-Inertia', 'true')
            ->call($method, '/jenis-berkas/'.$jb->id.$suffix, $payload)
            ->assertRedirect('/jenis-berkas')->assertSessionHasErrors('alasan');

        $this->assertSame($before, $jb->fresh()->getAttributes());
        $this->assertSame($auditCount, AuditLog::count());
    }

    public static function reasonRequiredMutations(): array
    {
        return [['PUT', ''], ['DELETE', '']];
    }

    #[DataProvider('mutationMethods')]
    public function test_initial_denial_precedes_malformed_validation_and_records_one_canonical_decision(string $method, string $suffix): void
    {
        $jb = JenisBerkas::create(['nama' => 'Persyaratan terlindungi', 'tahap' => 'pengukuran', 'izinkan_file' => true, 'created_by' => $this->perencanaan->id]);
        $before = $jb->fresh()->getAttributes();
        $auditCount = AuditLog::count();
        $this->actingAs($this->pegawai)->call($method, '/jenis-berkas'.($method === 'POST' ? '' : '/'.$jb->id).$suffix, [
            'nama' => [], 'tahap' => 'invalid', 'alasan' => ['bukan string'], 'expected_updated_at' => 'invalid',
        ])->assertForbidden();
        $audit = AuditLog::where('actor_id', $this->pegawai->id)->sole();
        $this->assertSame($auditCount + 1, AuditLog::count());
        $this->assertFalse($audit->dasar_izin['allowed']);
        [$permission, $event] = match ($method) {
            'POST' => ['jenis_berkas:create', 'jenis_berkas.buat_ditolak'],
            'PUT' => ['jenis_berkas:update', 'jenis_berkas.ubah_ditolak'],
            'DELETE' => ['jenis_berkas:delete', 'jenis_berkas.hapus_ditolak'],
        };
        $this->assertSame($permission, $audit->dasar_izin['permission']);
        $this->assertSame('no_allow', $audit->dasar_izin['reason']);
        $this->assertSame($event, $audit->tindakan);
        $this->assertSame('manual', $audit->sumber);
        $this->assertEqualsCanonicalizing(['allowed', 'permission', 'reason', 'roles', 'grants', 'denies'], array_keys($audit->dasar_izin));
        $this->assertIsString($audit->alasan);
        $this->assertDatabaseCount('jenis_berkas', 1);
        $this->assertSame($before, $jb->fresh()->getAttributes());
    }

    /**
     * TEST-7: Pembaruan dengan versi usang (concurrency conflict) ditolak.
     */
    public function test_update_with_stale_version_is_rejected(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Target',
            'tahap' => 'pengukuran',
            'wajib' => false,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $staleTimestamp = now()->subMinutes(10)->toISOString();

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Update Konflik',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Mencoba update dengan versi usang',
            'expected_updated_at' => $staleTimestamp,
        ]);

        $response->assertSessionHasErrors('konflik');
        $this->assertSame('Laporan Target', $jb->fresh()->nama);
    }

    /**
     * TEST-8: Index menampilkan indikator nonaktif yang sedang direferensikan.
     */
    public function test_index_includes_referenced_inactive_indicators(): void
    {
        $unit = Unit::first();
        $sasaran = SasaranStrategis::first();
        $inactiveIndikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-NONAKTIF',
            'nama' => 'Indikator Lama Dinonaktifkan',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'status' => 'arsip',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        JenisBerkas::create([
            'nama' => 'Syarat Khusus Indikator Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveIndikator->id,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->get('/jenis-berkas');
        $response->assertOk();

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('JenisBerkas/Index')
            ->has('indikators', fn (AssertableInertia $prop) => $prop
                ->where('0.kode', 'I-NONAKTIF')
                ->where('0.status', 'arsip')
                ->etc()
            )
        );
    }

    /**
     * TEST-9: Penghapusan persyaratan yang masih dirujuk berkas ditolak dengan validation error.
     */
    public function test_cannot_delete_jenis_berkas_referenced_by_berkas(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Dengan Berkas',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        DB::table('berkas')->insert([
            'id' => (string) Str::uuid(),
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => (string) Str::uuid(),
            'mode' => 'teks',
            'isi_teks' => 'Catatan bukti yang dirujuk',
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus persyaratan yang sudah dirujuk bukti',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);

        $response->assertSessionHasErrors('alasan');
        $this->assertDatabaseHas('jenis_berkas', ['id' => $jb->id]);
    }

    public function test_legacy_upload_setting_does_not_warn_when_file_only_requirement_is_created(): void
    {
        Pengaturan::create([
            'kunci' => 'berkas.unggahan_aktif',
            'nilai' => 'false',
            'tipe' => 'boolean',
            'grup' => 'berkas',
            'updated_at' => now(),
        ]);

        $payload = [
            'nama' => 'Laporan Khusus File Dinonaktifkan',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');
        $response->assertSessionMissing('warning');
        $this->assertDatabaseHas('jenis_berkas', ['nama' => 'Laporan Khusus File Dinonaktifkan']);
    }

    /**
     * TEST-11: Persyaratan jenis berkas dapat dinonaktifkan melalui update (aktif = false).
     */
    public function test_perencanaan_can_deactivate_jenis_berkas_via_update(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Usang',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Usang (Dinonaktifkan)',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'aktif' => false,
            'alasan' => 'Penonaktifan persyaratan usang agar tidak memblokir pengajuan mendatang',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');
        $this->assertDatabaseHas('jenis_berkas', [
            'id' => $jb->id,
            'aktif' => false,
        ]);

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')->where('objek_id', $jb->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(false, $audit->nilai_baru['aktif']);
    }

    /**
     * TEST-12: Update wajib menyertakan expected_updated_at dan mendeteksi konflik konkurensi.
     */
    public function test_update_requires_expected_updated_at_and_detects_concurrency_conflict(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Concurrency',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. Tanpa expected_updated_at -> validasi gagal (422)
        $noVersion = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pembaruan Tanpa Versi',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Pembaruan tanpa expected_updated_at',
        ]);
        $noVersion->assertSessionHasErrors('expected_updated_at');

        // 2. Dengan expected_updated_at lama/tidak cocok -> konflik
        $staleTimestamp = now()->subMinutes(10)->toISOString();
        $conflictResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pembaruan Versi Usang',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Mencoba menimpa perubahan',
            'expected_updated_at' => $staleTimestamp,
        ]);
        $conflictResponse->assertSessionHasErrors('konflik');
    }

    /**
     * TEST-13: Store ditolak jika memilih indikator kinerja nonaktif.
     */
    public function test_store_fails_when_assigning_to_inactive_indicator(): void
    {
        $inactiveIndikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->indikator->unit_id,
            'kode' => 'I-NONAKTIF',
            'nama' => 'Indikator Nonaktif',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'status' => 'arsip',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Persyaratan Indikator Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveIndikator->id,
            'izinkan_file' => true,
        ]);

        $response->assertSessionHasErrors('indikator_id');
    }

    /**
     * TEST-14: Update mengizinkan indikator nonaktif yang sudah terpasang, tetapi menolak perpindahan ke indikator nonaktif lain.
     */
    public function test_update_allows_existing_inactive_indicator_but_rejects_switching_to_different_inactive(): void
    {
        $inactiveA = IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->indikator->unit_id,
            'kode' => 'I-NONAKTIF-A',
            'nama' => 'Indikator Nonaktif A',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'status' => 'arsip',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $inactiveB = IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->indikator->unit_id,
            'kode' => 'I-NONAKTIF-B',
            'nama' => 'Indikator Nonaktif B',
            'satuan' => 'dokumen',
            'tipe_perhitungan' => 'manual',
            'status' => 'arsip',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Lama Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveA->id,
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. Update dengan mempertahankan inactiveA -> diizinkan
        $okResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Persyaratan Lama Tetap A',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveA->id,
            'izinkan_file' => true,
            'alasan' => 'Pembaruan tanpa mengubah indikator nonaktif',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);
        $okResponse->assertRedirect('/jenis-berkas');

        // 2. Update dengan mengubah ke inactiveB -> ditolak
        $failResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Pindah ke B Nonaktif',
            'tahap' => 'pengukuran',
            'indikator_id' => $inactiveB->id,
            'izinkan_file' => true,
            'alasan' => 'Mencoba pindah ke indikator nonaktif lain',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);
        $failResponse->assertSessionHasErrors('indikator_id');
    }

    /**
     * TEST-15: failedAuthorization tidak mengalami TypeError saat payload alasan berupa array dan tetap merespons 403.
     */
    public function test_failed_authorization_handles_array_alasan_without_type_error(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Hak Akses',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        // Kirim alasan sebagai array oleh user tanpa izin (pegawai)
        $response = $this->actingAs($this->pegawai)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Coba Ubah',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => ['malicious', 'array', 'payload'],
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertStatus(403);

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah_ditolak')
            ->where('objek_id', $jb->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertIsString($audit->alasan);
    }

    public function test_store_does_not_warn_for_legacy_disabled_setting(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            [
                'nilai' => 'false',
                'tipe' => 'boolean',
                'grup' => 'berkas',
                'updated_at' => now(),
            ]
        );

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Bukti Wajib File Saat Mati',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ]);

        $response->assertRedirect('/jenis-berkas');
        $response->assertSessionMissing('warning');
    }

    /**
     * TEST-17: Delete gagal dan melempar error konflik jika expected_updated_at tidak cocok dengan versi database.
     */
    public function test_delete_fails_when_expected_updated_at_conflicts(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Uji Konflik Delete',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus dengan versi timestamp usang',
            'expected_updated_at' => now()->subHours(2)->toISOString(),
        ]);

        $response->assertSessionHasErrors('konflik');
        $this->assertDatabaseHas('jenis_berkas', ['id' => $jb->id]);
    }

    /**
     * TEST-18: Delete gagal dengan error validasi saat expected_updated_at bukan format tanggal valid.
     */
    public function test_delete_fails_when_expected_updated_at_is_invalid_format(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Uji Format Delete',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Mencoba hapus dengan tanggal invalid',
            'expected_updated_at' => 'format-tanggal-rusak',
        ]);

        $response->assertSessionHasErrors('expected_updated_at');
        $this->assertDatabaseHas('jenis_berkas', ['id' => $jb->id]);
    }

    /**
     * TEST-19: Update gagal dengan error validasi (bukan 500) saat expected_updated_at bukan tanggal valid.
     */
    public function test_update_fails_with_validation_error_when_expected_updated_at_is_invalid_format(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Uji Format Update',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Update Nama Baru',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Alasan yang sah untuk pembaruan',
            'expected_updated_at' => 'string-bukan-tanggal',
        ]);

        $response->assertSessionHasErrors('expected_updated_at');
    }

    public function test_all_modes_create_and_update_ignore_legacy_disabled_setting(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            [
                'nilai' => 'false',
                'tipe' => 'boolean',
                'grup' => 'berkas',
                'updated_at' => now(),
            ]
        );

        // 1. Jalur Store
        $storeResponse = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Semua Mode Saat Unggahan Mati',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'semua_mode_wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
        ]);

        $storeResponse->assertRedirect('/jenis-berkas');
        $storeResponse->assertSessionMissing('warning');

        $jb = JenisBerkas::where('nama', 'Semua Mode Saat Unggahan Mati')->firstOrFail();

        // 2. Jalur Update
        $updateResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Semua Mode Saat Unggahan Mati Diperbarui',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'semua_mode_wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
            'alasan' => 'Pembaruan justifikasi operasional',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $updateResponse->assertRedirect('/jenis-berkas');
        $updateResponse->assertSessionMissing('warning');
    }

    public function test_evidence_evaluation_preserves_grandfathered_evidence_when_format_or_size_limits_are_tightened(): void
    {
        // Persyaratan awal: izinkan pdf dan docx, ukuran maksimum 1000 KB
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Berkas Dinamis',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'wajib' => true,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 1000,
            'created_by' => $this->perencanaan->id,
        ]);

        // Buat konteks pengukuran
        $renstra = Renstra::where('kode', 'R-UJI')->firstOrFail();
        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-DINAMIS',
            'tanggal_pk' => '2026-01-01',
            'created_by' => $this->perencanaan->id,
        ]);
        $periode = Periode::create([
            'nama' => 'Triwulan II',
            'urutan' => 2,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        $context = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $this->indikator->id,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $this->indikator->unit_id,
            'nama' => 'Indikator Uji',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 80,
        ]);
        $pengukuran = PengukuranKinerja::create([
            'indikator_id' => $this->indikator->id,
            'tahun' => 2026,
            'periode_id' => $periode->id,
            'jadwal_snapshot_id' => $context->id,
            'sumber_nilai' => 'manual',
            'created_by' => $this->perencanaan->id,
        ]);

        // Unggah bukti draf berformat docx (400 KB) yang sesuai konfigurasi awal
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => $pengukuran->id,
            'mode' => 'file',
            'nama_asli' => 'laporan_kinerja.docx',
            'path' => 'berkas/laporan_kinerja.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ukuran_bytes' => 400 * 1024,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $evaluator = app(EvaluateEvidence::class);

        // Tahap 1: Konfigurasi awal memenuhi syarat -> terpenuhi = true
        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertTrue($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame([], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // Tahap 2: Batas format dipersempit menjadi hanya pdf -> Bukti lama TETAP SAH (Grandfathered sesuai SAKIP - Workflow.md:1489-1493)
        $jb->update(['format_diizinkan' => 'pdf']);
        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertTrue($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame([], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);

        // Tahap 3: Batas ukuran diperketat menjadi 200 KB (< 400 KB) -> Bukti lama TETAP SAH (Grandfathered)
        $jb->update(['format_diizinkan' => 'pdf,docx', 'ukuran_maks_kb' => 200]);
        $evaluations = collect($evaluator->handle($pengukuran))->keyBy('id');
        $this->assertTrue($evaluations[$jb->id]['pemenuhan']['terpenuhi']);
        $this->assertSame([], $evaluations[$jb->id]['pemenuhan']['mode_kurang']);
    }

    public function test_perencanaan_can_manage_format_and_size_with_jenis_berkas_permission(): void
    {
        $resolver = app(PermissionResolver::class);
        $this->assertFalse($resolver->allows($this->perencanaan, 'pengaturan:update'));
        $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Batas teknis Perencanaan', 'tahap' => 'pengukuran', 'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx', 'ukuran_maks_kb' => 5000,
        ])->assertRedirect('/jenis-berkas')->assertSessionHasNoErrors();
        $jb = JenisBerkas::where('nama', 'Batas teknis Perencanaan')->sole();
        $this->assertSame('pdf,docx', $jb->format_diizinkan);
        $this->assertSame(5000, $jb->ukuran_maks_kb);
        $this->actingAs($this->perencanaan)->put('/jenis-berkas/'.$jb->id, [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => true,
            'format_diizinkan' => 'pdf', 'ukuran_maks_kb' => 2000,
            'alasan' => 'Menyesuaikan persyaratan dokumen.', 'expected_updated_at' => $jb->updated_at->toISOString(),
        ])->assertRedirect('/jenis-berkas')->assertSessionHasNoErrors();
        $this->assertSame('pdf', $jb->fresh()->format_diizinkan);
        $this->assertSame(2000, $jb->fresh()->ukuran_maks_kb);
    }

    public function test_perencanaan_updates_technical_fields_through_requirement_form(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Teknis Khusus',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,docx,xlsx',
            'ukuran_maks_kb' => 10240,
            'alasan' => 'Admin memperbarui kuota ukuran berkas dan menambah format xlsx.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $this->assertDatabaseHas('jenis_berkas', [
            'id' => $jb->id,
            'format_diizinkan' => 'pdf,docx,xlsx',
            'ukuran_maks_kb' => 10240,
        ]);

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')
            ->where('objek_id', $jb->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('jenis_berkas:update', $audit->dasar_izin['permission'] ?? null);
    }

    public function test_format_narrowing_preserves_old_files_without_storage_warning(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Format Sempit',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx,xlsx',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        // Simpan bukti dukung lama dengan format docx
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => (string) Str::uuid(),
            'mode' => 'file',
            'nama_asli' => 'laporan_kinerja_lama.docx',
            'path' => 'berkas/laporan_kinerja_lama.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ukuran_bytes' => 15000,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // Perencanaan mempersempit format menjadi hanya 'pdf,png' (docx tidak lagi tercakup)
        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,png',
            'ukuran_maks_kb' => 5000,
            'alasan' => 'Penyempitan format yang diizinkan untuk keamanan berkas.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $response->assertSessionMissing('warning');

        // Bukti dukung lama tetap tersimpan (grandfathered)
        $this->assertDatabaseHas('berkas', [
            'jenis_berkas_id' => $jb->id,
            'nama_asli' => 'laporan_kinerja_lama.docx',
        ]);
    }

    public function test_update_technical_fields_records_only_requirement_permission(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Dual Izin',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($superadmin)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => 'Syarat Dual Izin Diperbarui',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 10240,
            'alasan' => 'Pembaruan nama dan batas teknis sekaligus oleh Superadmin.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')
            ->where('objek_id', $jb->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertIsArray($audit->dasar_izin);
        $this->assertSame('jenis_berkas:update', $audit->dasar_izin['permission']);
    }

    public function test_update_batas_teknis_requires_exact_version_timestamp_matching_optimistic_locking(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Kunci Versi',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        // 1. Kirim timestamp masa depan -> ditolak dengan error 'konflik'
        $futureIso = now()->addDays(2)->toISOString();
        $responseFuture = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 8192,
            'alasan' => 'Upaya pembaruan dengan timestamp masa depan.',
            'expected_updated_at' => $futureIso,
        ]);
        $responseFuture->assertSessionHasErrors('konflik');

        // 2. Kirim timestamp masa lalu yang tidak cocok -> ditolak dengan error 'konflik'
        $pastIso = now()->subDays(2)->toISOString();
        $responsePast = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 8192,
            'alasan' => 'Upaya pembaruan dengan timestamp usang.',
            'expected_updated_at' => $pastIso,
        ]);
        $responsePast->assertSessionHasErrors('konflik');

        // 3. Kirim timestamp persis identik -> berhasil
        $exactIso = ($jb->updated_at ?? $jb->created_at)->toISOString();
        $responseExact = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 8192,
            'alasan' => 'Pembaruan batas teknis dengan versi timestamp identik.',
            'expected_updated_at' => $exactIso,
        ]);
        $responseExact->assertRedirect(route('jenis-berkas.index'));
        $this->assertDatabaseHas('jenis_berkas', [
            'id' => $jb->id,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 8192,
        ]);
    }

    public function test_store_technical_fields_records_only_requirement_permission(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        $response = $this->actingAs($superadmin)->post(route('jenis-berkas.store'), [
            'nama' => 'Syarat Baru Dengan Batas Teknis Superadmin',
            'tahap' => 'pengukuran',
            'wajib' => true,
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx,xlsx',
            'ukuran_maks_kb' => 10240,
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $jb = JenisBerkas::where('nama', 'Syarat Baru Dengan Batas Teknis Superadmin')->firstOrFail();

        $audit = AuditLog::where('tindakan', 'jenis_berkas.buat')
            ->where('objek_id', $jb->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertIsArray($audit->dasar_izin);
        $this->assertSame('jenis_berkas:create', $audit->dasar_izin['permission']);
    }

    public function test_update_batas_teknis_rejects_non_string_format_gracefully(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Uji Input Malformed',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => ['pdf', 'docx'],
            'ukuran_maks_kb' => 8192,
            'alasan' => 'Uji kirim format_diizinkan sebagai array.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertSessionHasErrors('format_diizinkan');
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_format_expansion_does_not_trigger_grandfathering_warning(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Laporan Ekstensi Diperluas',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        // Simpan bukti dukung lama yang formatnya docx (dari riwayat sebelum konfigurasi master pdf)
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => (string) Str::uuid(),
            'mode' => 'file',
            'nama_asli' => 'laporan_lama.docx',
            'path' => 'berkas/laporan_lama.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ukuran_bytes' => 12000,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        // Perencanaan memperluas format dari 'pdf' menjadi 'pdf,xlsx'
        // Format 'pdf' tetap ada, tidak ada format lama yang dihilangkan
        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,xlsx',
            'ukuran_maks_kb' => 5000,
            'alasan' => 'Memperluas format file yang diizinkan untuk menyertakan spreadsheet.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $response->assertSessionMissing('warning');
    }

    public function test_store_jenis_berkas_rejected_by_explicit_deny_is_logged_to_audit(): void
    {
        $permission = Permission::where('kode', 'jenis_berkas:create')->sole();

        app(CreateDeny::class)->handle(
            $this->admin,
            $this->perencanaan->id,
            $permission->id,
            null,
            'Larangan eksplisit sementara untuk perencanaan'
        );

        $response = $this->actingAs($this->perencanaan)->post(route('jenis-berkas.store'), [
            'nama' => 'Syarat Ditolak Eksplisit',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'wajib' => true,
            'semua_mode_wajib' => false,
            'urutan' => 1,
            'keterangan' => 'Uji coba create dengan explicit deny',
        ]);

        $response->assertForbidden();

        $audit = AuditLog::where('tindakan', 'jenis_berkas.buat_ditolak')
            ->where('actor_id', $this->perencanaan->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('jenis_berkas.buat_ditolak', $audit->tindakan);
        $this->assertFalse($audit->dasar_izin['allowed'] ?? true);
        $this->assertSame('explicit_deny', $audit->dasar_izin['reason'] ?? null);
    }

    public function test_update_batas_teknis_supports_omitted_format_diizinkan(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Patch Ukuran Parsial',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'ukuran_maks_kb' => 8192,
            'alasan' => 'Hanya mengubah ukuran berkas maksimum tanpa mengirim format_diizinkan.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $this->assertDatabaseHas('jenis_berkas', [
            'id' => $jb->id,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 8192,
        ]);
    }

    public function test_format_narrowing_from_default_preserves_old_files(): void
    {
        config(['uploads.allowed_extensions' => 'pdf,docx,xlsx,jpg,jpeg,png']);

        $jb = JenisBerkas::create([
            'nama' => 'Syarat Format Global Default',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'format_diizinkan' => null,
            'ukuran_maks_kb' => null,
            'created_by' => $this->perencanaan->id,
        ]);

        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => (string) Str::uuid(),
            'mode' => 'file',
            'nama_asli' => 'tabel_kinerja.xlsx',
            'path' => 'berkas/tabel_kinerja.xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ukuran_bytes' => 15000,
            'uploaded_by' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,docx',
            'alasan' => 'Menetapkan format khusus dari default global yang lebih sempit.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $response->assertSessionMissing('warning');
        $this->assertDatabaseHas('berkas', ['jenis_berkas_id' => $jb->id, 'nama_asli' => 'tabel_kinerja.xlsx']);
    }

    public function test_substantive_update_without_tech_fields_does_not_assert_pengaturan_permission(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Awal Substantif',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => 'Syarat Diperbarui Substantif',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'wajib' => true,
            'semua_mode_wajib' => false,
            'urutan' => 5,
            'keterangan' => 'Update murni substantif',
            'alasan' => 'Koreksi penamaan dokumen bukti kinerja.',
            'expected_updated_at' => ($jb->updated_at ?? $jb->created_at)->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));

        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')
            ->where('objek_id', $jb->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('jenis_berkas:update', $audit->dasar_izin['permission'] ?? null);
        $this->assertArrayNotHasKey('pengaturan:update', $audit->dasar_izin);
    }

    /**
     * TEST-36: Optimistic locking menolak mutasi dengan timestamp lama saat terjadi pembaruan beruntun subdetik.
     */
    public function test_optimistic_locking_rejects_subsecond_stale_update(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Syarat Subdetik Concurrency',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 5000,
            'created_by' => $this->perencanaan->id,
        ]);

        $initialVersion = ($jb->updated_at ?? $jb->created_at)->toISOString();

        $firstResponse = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => 'Syarat Subdetik Concurrency Mutasi 1',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'wajib' => true,
            'semua_mode_wajib' => false,
            'urutan' => 2,
            'keterangan' => 'Mutasi pertama',
            'alasan' => 'Pembaruan data pertama dalam subdetik.',
            'expected_updated_at' => $initialVersion,
        ]);

        $firstResponse->assertRedirect(route('jenis-berkas.index'));

        $secondResponse = $this->actingAs($this->perencanaan)->put(route('jenis-berkas.update', $jb->id), [
            'nama' => 'Syarat Subdetik Concurrency Mutasi 2 Stale',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'wajib' => true,
            'semua_mode_wajib' => false,
            'urutan' => 3,
            'keterangan' => 'Mutasi kedua yang harus ditolak',
            'alasan' => 'Mencoba mengupdate dengan token lama.',
            'expected_updated_at' => $initialVersion,
        ]);

        $secondResponse->assertSessionHasErrors('konflik');
        $this->assertStringContainsString(
            'Data persyaratan telah diperbarui oleh pengguna lain',
            session('errors')->first('konflik')
        );
    }

    public function test_perencanaan_can_update_only_size_with_default_format(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Format Default',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => null,
            'ukuran_maks_kb' => null,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'ukuran_maks_kb' => 10240,
            'alasan' => 'Menaikkan batas ukuran berkas ke 10MB',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertRedirect(route('jenis-berkas.index'));
        $response->assertSessionHasNoErrors();

        $jb->refresh();
        $this->assertSame(10240, $jb->ukuran_maks_kb);
        $this->assertNull($jb->format_diizinkan);
    }

    /**
     * TEST-38: Store jenis berkas menerima tahap 'rencana_aksi' (gerbang bukti RA sudah ada) dan menolak tahap 'kegiatan' (SPJ) yang gerbangnya belum ada.
     */
    public function test_store_jenis_berkas_rejects_unsupported_tahap(): void
    {
        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Persyaratan Tahap Rencana Aksi',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jenis_berkas', ['nama' => 'Persyaratan Tahap Rencana Aksi', 'tahap' => 'rencana_aksi']);

        $responseKegiatan = $this->actingAs($this->perencanaan)->post('/jenis-berkas', [
            'nama' => 'Persyaratan Tahap Kegiatan',
            'tahap' => 'kegiatan',
            'izinkan_file' => true,
        ]);

        $responseKegiatan->assertSessionHasErrors('tahap');
    }

    /**
     * TEST-39: Update jenis berkas ke tahap 'rencana_aksi' diterima lewat jalur ubah substantif (alasan + optimistic lock); tahap 'kegiatan' tetap ditolak.
     */
    public function test_update_jenis_berkas_rejects_unsupported_tahap(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Valid Tahap',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Ubah Ke Rencana Aksi',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
            'alasan' => 'Mencoba memindahkan tahap',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('rencana_aksi', $jb->fresh()->tahap);

        $jb->refresh();
        $responseKegiatan = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Ubah Ke Kegiatan',
            'tahap' => 'kegiatan',
            'izinkan_file' => true,
            'alasan' => 'Mencoba memindahkan tahap',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $responseKegiatan->assertSessionHasErrors('tahap');
        $this->assertSame('rencana_aksi', $jb->fresh()->tahap);
    }

    public function test_optimistic_lock_fails_safely_if_record_timestamp_is_null(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Tanpa Timestamp',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        DB::table('jenis_berkas')->where('id', $jb->id)->update([
            'created_at' => null,
            'updated_at' => null,
        ]);

        // Uji Update ditolak
        $updateResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Coba Update Row Lama',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Alasan update row tanpa timestamp',
            'expected_updated_at' => now()->toISOString(),
        ]);
        $updateResponse->assertSessionHasErrors('konflik');

        // Uji Batas Teknis ditolak
        $batasTeknisResponse = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'ukuran_maks_kb' => 8192,
            'alasan' => 'Alasan ubah batas teknis row tanpa timestamp',
            'expected_updated_at' => now()->toISOString(),
        ]);
        $batasTeknisResponse->assertSessionHasErrors('konflik');

        // Uji Delete ditolak
        $deleteResponse = $this->actingAs($this->perencanaan)->delete("/jenis-berkas/{$jb->id}", [
            'alasan' => 'Alasan hapus row tanpa timestamp',
            'expected_updated_at' => now()->toISOString(),
        ]);
        $deleteResponse->assertSessionHasErrors('konflik');
    }

    public function test_clearing_format_override_preserves_old_files_without_warning(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        // Atur default konfigurasi hanya mengizinkan pdf
        config(['uploads.allowed_extensions' => 'pdf']);

        $jb = JenisBerkas::create([
            'nama' => 'Laporan Khusus Override',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx',
            'created_by' => $superadmin->id,
        ]);

        // Bukti lama tetap dipertahankan meski default konfigurasi hanya pdf.
        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => Str::uuid()->toString(),
            'mode' => 'file',
            'nama_asli' => 'arsip_lama.docx',
            'path' => 'evidence/arsip_lama.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ukuran_bytes' => 4096,
            'uploaded_by' => $superadmin->id,
            'created_at' => now(),
        ]);

        // Kosongkan override agar unggahan berikutnya memakai konfigurasi.
        $response = $this->actingAs($superadmin)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Khusus Override',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => '',
            'alasan' => 'Mengembalikan batas format ke setelan global',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');
        $response->assertSessionMissing('warning');

        $this->assertNull($jb->fresh()->format_diizinkan);
        $this->assertDatabaseHas('berkas', ['jenis_berkas_id' => $jb->id, 'nama_asli' => 'arsip_lama.docx']);
    }

    public function test_clearing_explicit_format_override_does_not_trigger_warning_when_fallback_covers_files(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        // Atur default konfigurasi mencakup pdf, docx, xlsx
        config(['uploads.allowed_extensions' => 'pdf,docx,xlsx']);

        $jb = JenisBerkas::create([
            'nama' => 'Laporan Multiformat',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx',
            'created_by' => $superadmin->id,
        ]);

        BuktiDukung::create([
            'jenis_berkas_id' => $jb->id,
            'berkasable_type' => 'pengukuran',
            'berkasable_id' => Str::uuid()->toString(),
            'mode' => 'file',
            'nama_asli' => 'arsip.docx',
            'path' => 'evidence/arsip.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ukuran_bytes' => 4096,
            'uploaded_by' => $superadmin->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Laporan Multiformat',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => '',
            'alasan' => 'Mengembalikan batas format ke setelan global',
            'expected_updated_at' => $jb->updated_at->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');
        $response->assertSessionMissing('warning');
    }

    /**
     * TEST-43: Mutasi tanpa perubahan (no-op) pada update substantif tidak mengubah updated_at dan tidak mencatat audit_log.
     */
    public function test_noop_substantive_update_does_not_bump_updated_at_or_record_audit_log(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Tetap',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'wajib' => false,
            'urutan' => 0,
            'created_by' => $this->perencanaan->id,
        ]);

        $originalUpdatedAt = $jb->fresh()->updated_at;
        $initialAuditCount = AuditLog::where('objek_id', $jb->id)->count();

        // Kirim update dengan data yang sama persis (hanya alasan dan expected_updated_at disediakan)
        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Persyaratan Tetap',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'wajib' => false,
            'urutan' => 0,
            'alasan' => 'Konfirmasi dialog ubah tanpa modifikasi field',
            'expected_updated_at' => $originalUpdatedAt->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');

        $refreshed = $jb->fresh();
        $this->assertEquals($originalUpdatedAt->toISOString(), $refreshed->updated_at->toISOString());
        $this->assertSame($initialAuditCount, AuditLog::where('objek_id', $jb->id)->count());
    }

    public function test_noop_batas_teknis_update_does_not_bump_updated_at_or_record_audit_log(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Batas Tetap',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 4096,
            'created_by' => $this->perencanaan->id,
        ]);

        $originalUpdatedAt = $jb->fresh()->updated_at;
        $initialAuditCount = AuditLog::where('objek_id', $jb->id)->count();

        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => $jb->nama, 'tahap' => $jb->tahap, 'izinkan_file' => $jb->izinkan_file,
            'format_diizinkan' => 'pdf,docx',
            'ukuran_maks_kb' => 4096,
            'alasan' => 'Konfirmasi modal batas teknis tanpa ubah nilai',
            'expected_updated_at' => $originalUpdatedAt->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');

        $refreshed = $jb->fresh();
        $this->assertEquals($originalUpdatedAt->toISOString(), $refreshed->updated_at->toISOString());
        $this->assertSame($initialAuditCount, AuditLog::where('objek_id', $jb->id)->count());
    }

    /**
     * TEST-45: Hook model static::saving tidak mengubah updated_at jika model tidak dirty.
     */
    public function test_model_saving_hook_does_not_bump_updated_at_when_not_dirty(): void
    {
        $jb = JenisBerkas::create([
            'nama' => 'Model Tetap',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $originalUpdatedAt = $jb->fresh()->updated_at;

        // Panggil save() langsung pada model yang tidak mengalami perubahan field
        $jb->save();

        $refreshed = $jb->fresh();
        $this->assertEquals($originalUpdatedAt->toISOString(), $refreshed->updated_at->toISOString());
    }

    public function test_store_ignores_legacy_disabled_setting_without_marking_audit(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            ['nilai' => 'false', 'tipe' => 'boolean', 'grup' => 'berkas', 'updated_at' => now()]
        );

        $payload = [
            'nama' => 'Bukti Wajib File Saat Saklar Mati',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'wajib' => true,
            'keterangan' => 'Uji penandaan saat saklar mati',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'semua_mode_wajib' => false,
            'urutan' => 1,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 2048,
        ];

        $response = $this->actingAs($this->perencanaan)->post('/jenis-berkas', $payload);
        $response->assertRedirect('/jenis-berkas');

        $jb = JenisBerkas::where('nama', 'Bukti Wajib File Saat Saklar Mati')->firstOrFail();

        $markingAudit = AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
            ->where('objek_id', $jb->id)
            ->first();

        $this->assertNull($markingAudit);
    }

    public function test_requirement_update_preserves_old_marker_without_revocation(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            ['nilai' => 'false', 'tipe' => 'boolean', 'grup' => 'berkas', 'updated_at' => now()]
        );

        // Buat file-only dan siapkan marker historis secara eksplisit.
        $jb = JenisBerkas::create([
            'nama' => 'Persyaratan Transisi Mode',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'wajib' => true,
            'aktif' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'semua_mode_wajib' => false,
            'created_by' => $this->perencanaan->id,
        ]);

        // Simulasikan marker aktif awal di audit_log
        app(AuditLogger::class)->catat(
            actor: $this->perencanaan,
            tindakan: 'berkas.tandai_tidak_dapat_dipenuhi',
            objekTipe: 'jenis_berkas',
            objekId: $jb->id,
            nilaiLama: ['status_pemenuhan' => 'normal'],
            nilaiBaru: ['status_pemenuhan' => 'tidak_dapat_dipenuhi'],
            alasan: 'Penandaan awal simulasi',
        );

        // Ubah menjadi izinkan_tautan = true
        $response = $this->actingAs($this->perencanaan)->put("/jenis-berkas/{$jb->id}", [
            'nama' => 'Persyaratan Transisi Mode',
            'tahap' => 'pengukuran',
            'indikator_id' => $this->indikator->id,
            'wajib' => true,
            'aktif' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => false,
            'semua_mode_wajib' => false,
            'alasan' => 'Mengizinkan tautan agar dapat dipenuhi PIC',
            'expected_updated_at' => $jb->fresh()->updated_at->toISOString(),
        ]);

        $response->assertRedirect('/jenis-berkas');

        // Marker lama tetap tersimpan tanpa pencabutan otomatis (Q44).
        $revocationAudit = AuditLog::where('tindakan', 'berkas.cabut_tidak_dapat_dipenuhi')
            ->where('objek_id', $jb->id)
            ->first();

        $this->assertNull($revocationAudit);
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->where('objek_id', $jb->id)->count());
    }

    /**
     * TEST-48: Transisi berulang file-only -> multi-mode -> file-only -> multi-mode saat saklar mati dengan frozen clock.
     */
}
