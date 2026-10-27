<?php

namespace Tests\Feature\Kegiatan;

use App\Actions\Kegiatan\SimpanKegiatan;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class KegiatanHttpTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private User $perencanaan;

    private User $pegawai;

    private Unit $unit;

    private Unit $unitLain;

    private Periode $periode;

    /**
     * Identitas Rencana Aksi hanya untuk URL kembali (`back()`); halaman
     * kegiatan RA sendiri dibuktikan di `HalamanKegiatanRencanaAksiTest`.
     */
    private string $rencanaAksiId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->perencanaan = $this->user('perencanaan');
        $this->pegawai = $this->user('pegawai');
        $this->unit = Unit::create(['nama' => 'Unit A', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);
        $this->unitLain = Unit::create(['nama' => 'Unit B', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);
        $this->periode = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        $this->rencanaAksiId = (string) Str::uuid();
        foreach (['kegiatan:create', 'kegiatan:update'] as $code) {
            UserPermissionGrant::create(['user_id' => $this->pegawai->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id, 'alasan' => 'Penugasan kegiatan unit', 'diberikan_oleh' => $this->perencanaan->id]);
        }
    }

    public function test_basis_data_menolak_anggaran_negatif_di_luar_validasi_request(): void
    {
        $this->expectException(QueryException::class);
        $this->kegiatan()->forceFill(['anggaran' => -1])->save();
    }

    public function test_anggaran_nol_sah_dan_kosong_ditolak_pada_ubah(): void
    {
        $kegiatan = $this->kegiatan();

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'anggaran' => 0, 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertSessionHasNoErrors();
        $this->assertSame('0', $kegiatan->fresh()->anggaran);

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'anggaran' => null, 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertSessionHasErrors('anggaran');
        $this->assertSame('0', $kegiatan->fresh()->anggaran);
    }

    public function test_ubah_data_rencana_mempertahankan_status_dan_data_pelaksanaan_dan_kembali_ke_halaman_asal(): void
    {
        $kegiatan = $this->kegiatan();
        $kegiatan->forceFill(['uraian_pelaksanaan' => 'Narasi histori'])->save();
        $kembali = $this->halamanKegiatan();
        $this->from($kembali)->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana diperbarui', 'expected_state' => $kegiatan->fresh()->versionToken(),
            'status' => 'batal', 'uraian_pelaksanaan' => 'Payload tidak dipercaya',
        ]))->assertRedirect($kembali)->assertSessionHasNoErrors();

        $this->assertSame('Rencana diperbarui', $kegiatan->fresh()->nama);
        $this->assertSame('rencana', $kegiatan->fresh()->status);
        $this->assertSame('Narasi histori', $kegiatan->fresh()->uraian_pelaksanaan);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah')->sole();
        $this->assertSame('Rencana awal', $audit->nilai_lama['nama']);
        $this->assertSame('Rencana diperbarui', $audit->nilai_baru['nama']);
    }

    /** Data rencana hanya dapat diubah selama status `rencana` (Q69 §69.3). */
    #[DataProvider('statusSelainRencana')]
    public function test_data_rencana_status_selain_rencana_ditolak_422(string $status): void
    {
        $kegiatan = $this->kegiatan();
        $kegiatan->forceFill(['status' => $status])->save();

        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", [...$this->payload(), 'nama' => 'Ditolak', 'expected_state' => $kegiatan->fresh()->versionToken()])
            ->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Data rencana terkunci karena status kegiatan sudah berubah.']);

        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah')->exists());
    }

    public static function statusSelainRencana(): array
    {
        return [['tidak_terlaksana'], ['terlaksana'], ['ditunda'], ['batal']];
    }

    public function test_request_lintas_unit_ditolak_dan_teraudit_tanpa_mutasi(): void
    {
        $kegiatan = $this->kegiatan($this->unitLain);
        // Mengirim unit A tidak boleh menyamarkan kepemilikan objek unit B.
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => 'token-tidak-dipakai'])->assertForbidden();
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame($this->unitLain->id, $kegiatan->fresh()->unit_id);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->count());
    }

    public function test_pemindahan_unit_memerlukan_izin_unit_tujuan_juga(): void
    {
        $kegiatan = $this->kegiatan();
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'unit_id' => $this->unitLain->id, 'expected_state' => $kegiatan->versionToken(),
        ]))->assertForbidden();
        $this->assertSame($this->unit->id, $kegiatan->fresh()->unit_id);
    }

    public function test_deny_ubah_menolak_request_bahkan_superadmin(): void
    {
        $aktor = $this->user('superadmin');
        $kegiatan = $this->kegiatan();
        $this->deny($aktor, 'kegiatan:update', $this->unit->id);
        $this->actingAs($aktor)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => 'token-tidak-dipakai'])->assertForbidden();
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $this->assertNotEmpty(AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->sole()->dasar_izin);
    }

    public function test_unit_nonaktif_dan_periode_nonaktif_atau_asing_ditolak_sebagai_validasi(): void
    {
        $kegiatan = $this->kegiatan();

        $this->unit->update(['status' => 'nonaktif']);
        $this->actingAs($this->perencanaan)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('unit_id');

        $this->unit->update(['status' => 'aktif']);
        $this->periode->update(['aktif' => false]);
        $this->actingAs($this->perencanaan)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('periode_id');

        $this->actingAs($this->perencanaan)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'periode_id' => (string) Str::uuid(), 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('periode_id');
        $this->assertDatabaseCount('kegiatan', 1);
    }

    public function test_grant_tulis_tanpa_izin_baca_unit_ditolak_pada_simpan(): void
    {
        $kegiatan = $this->kegiatan();
        $admin = $this->user('admin');
        foreach (['kegiatan:create', 'kegiatan:update'] as $code) {
            UserPermissionGrant::create(['user_id' => $admin->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id, 'alasan' => 'Grant tanpa izin baca', 'diberikan_oleh' => $this->perencanaan->id]);
        }
        $this->actingAs($admin)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => $kegiatan->versionToken()])->assertForbidden();
        $this->assertSame('kegiatan:read', AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->where('actor_id', $admin->id)->firstOrFail()->dasar_izin['permission']);

        // Deny baca pada unit tulis menutup simpan pada unit itu.
        $this->deny($this->pegawai, 'kegiatan:read', $this->unit->id);
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => $kegiatan->versionToken()])->assertForbidden();
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_stale_edit_tidak_menimpa_data_terbaru(): void
    {
        $kegiatan = $this->kegiatan();
        $token = $kegiatan->versionToken();
        $kegiatan->update(['nama' => 'Perubahan pengguna lain']);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => $token])->assertUnprocessable()->assertJsonValidationErrors('expected_state');
        $this->assertSame('Perubahan pengguna lain', $kegiatan->fresh()->nama);
    }

    public function test_akun_yang_dinonaktifkan_sebelum_submit_ditolak_dan_diaudit_sekali(): void
    {
        $kegiatan = $this->kegiatan();
        $this->pegawai->update(['status' => 'nonaktif']);
        // Mutasi Inertia fail-closed dengan 403, searah guard akun nonaktif di modul lain.
        $this->actingAs($this->pegawai)->post(
            "/kegiatan/{$kegiatan->id}",
            array_replace($this->payload(), ['unit_id' => 'payload-rusak']),
            ['X-Inertia' => 'true'],
        )->assertForbidden()->assertHeaderMissing('Location');
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame($this->pegawai->id, $audit->actor_id);
        $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
    }

    public function test_action_simpan_mengaudit_penolakan_dengan_target_tepercaya_dan_keputusan_asli(): void
    {
        $kegiatan = $this->kegiatan($this->unitLain);
        $this->deny($this->pegawai, 'kegiatan:update', $this->unitLain->id);
        $decision = app(PermissionResolver::class)->resolve($this->pegawai, 'kegiatan:update', $this->unitLain->id);
        // Keputusan baru berbeda; audit harus mempertahankan keputusan yang menolak request.
        $this->pegawai->update(['status' => 'nonaktif']);
        $action = app(SimpanKegiatan::class);
        $action->catatPenolakan($this->pegawai, $kegiatan, $this->unit->id, 'Izin ditolak.', $decision);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame('kegiatan', $audit->objek_tipe);
        $this->assertSame('explicit_deny', $audit->dasar_izin['alasan']);

        foreach ([strtoupper($this->unit->id), 'rusak', ['id' => $this->unit->id], (string) Str::uuid(), null] as $unitId) {
            $action->catatPenolakan($this->pegawai, null, $unitId, 'Akun tidak aktif.');
        }
        $audits = AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->get();
        $this->assertCount(5, $audits);
        $this->assertSame(1, $audits->where('objek_tipe', 'unit')->where('objek_id', $this->unit->id)->count());
        $this->assertSame(4, $audits->where('objek_tipe', 'pengguna')->where('objek_id', $this->pegawai->id)->count());
        foreach ($audits as $audit) {
            $this->assertSame($this->pegawai->id, $audit->actor_id);
            $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
            $this->assertSame('kegiatan:create', $audit->dasar_izin['permission']);
            $this->assertNull($audit->nilai_baru);
        }
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_nama_hingga_500_karakter_dan_tujuan_opsional(): void
    {
        $kegiatan = $this->kegiatan();

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => str_repeat('n', 501), 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertSessionHasErrors('nama');

        $nama = str_repeat('é', 500);
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => $nama, 'tujuan' => '', 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertSessionHasNoErrors();
        $this->assertSame($nama, $kegiatan->fresh()->nama);
        $this->assertNull($kegiatan->fresh()->tujuan);

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => $nama, 'tujuan' => null, 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertSessionHasNoErrors();
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah')->where('objek_id', $kegiatan->id)
            ->orderByDesc('waktu')->orderByDesc('id')->firstOrFail();
        $this->assertNull($audit->nilai_baru['tujuan']);
    }

    public function test_gagal_audit_membatalkan_perubahan(): void
    {
        $kegiatan = $this->kegiatan();
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Audit gagal sintetis'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
                'nama' => 'Tidak boleh tersimpan', 'expected_state' => $kegiatan->fresh()->versionToken(),
            ]));
            $this->fail('Mutasi seharusnya dibatalkan bila audit gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit gagal sintetis', $exception->getMessage());
        }
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_capability_batch_antar_unit_memakai_keputusan_kanonis_dengan_query_tetap(): void
    {
        $this->deny($this->pegawai, 'kegiatan:update', $this->unit->id);
        $resolver = app(PermissionResolver::class);
        $scopes = [$this->unit->id, strtoupper($this->unit->id), $this->unitLain->id, null, 'bukan-uuid'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $batch = $resolver->decideManyForUnits($this->pegawai, ['kegiatan:create', 'kegiatan:update', 'kegiatan:read'], $scopes);
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        DB::disableQueryLog();
        foreach ($scopes as $scope) {
            $this->assertSame($resolver->decideMany($this->pegawai, ['kegiatan:create', 'kegiatan:update', 'kegiatan:read'], $scope), $batch[$scope ?? '']);
        }
    }

    public function test_deny_dengan_payload_unit_rusak_tetap_diaudit(): void
    {
        $kegiatan = $this->kegiatan($this->unitLain);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['unit_id' => 'rusak']))->assertForbidden();
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->where('objek_id', $kegiatan->id)->count());
    }

    public function test_konteks_kegiatan_yang_sudah_diklaim_tidak_dapat_dipindah_tetapi_nama_boleh_diubah(): void
    {
        $indikator = $this->targetIndicator($this->perencanaan);
        $indikator->update(['unit_id' => $this->unit->id]);
        $jadwal = JadwalTahunan::create(['renstra_id' => $indikator->sasaranStrategis->renstra_id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => 'draft']);
        $ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $jadwal->id, 'penanggung_jawab_id' => $this->pegawai->id, 'tahun' => 2026, 'created_by' => $this->perencanaan->id]);
        $kegiatan = $this->kegiatan();
        KlaimKegiatan::create(['rencana_aksi_id' => $ra->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->perencanaan->id, 'created_at' => now()]);
        $token = $kegiatan->fresh()->versionToken();
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['tahun' => 2027, 'expected_state' => $token]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->assertSame(2026, $kegiatan->fresh()->tahun);
        $kembali = $this->halamanKegiatan($ra->id);
        $this->from($kembali)->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Nama dikoreksi', 'unit_id' => strtoupper($this->unit->id), 'periode_id' => strtoupper($this->periode->id), 'expected_state' => $token,
        ]))->assertRedirect($kembali)->assertSessionHasNoErrors();
        $this->assertSame('Nama dikoreksi', $kegiatan->fresh()->nama);
    }

    public function test_tahun_dengan_jadwal_ditutup_menolak_ubah(): void
    {
        $kegiatan = $this->kegiatan();
        $jadwal = JadwalTahunan::create(['renstra_id' => $this->renstraUji()->id, 'tahun' => $kegiatan->tahun, 'penutupan' => ($kegiatan->tahun + 1).'-12-31', 'status' => 'aktif']);
        $ubah = fn (array $extra = []) => $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}",
            [...$this->payload(), 'nama' => 'Nama baru', 'expected_state' => $kegiatan->fresh()->versionToken(), ...$extra]);
        $ubah()->assertSessionHasNoErrors();

        $jadwal->update(['status' => 'ditutup', 'closed_at' => now()]);
        $ubah(['nama' => 'Ditolak'])->assertSessionHasErrors('tahun');
        // Memindahkan kegiatan dari tahun yang ditutup ke tahun terbuka juga ditolak.
        $ubah(['tahun' => $kegiatan->tahun + 5])->assertSessionHasErrors('tahun');
        $this->assertSame('Nama baru', $kegiatan->fresh()->nama);
        $this->assertSame($kegiatan->tahun, $kegiatan->fresh()->tahun);
        $this->assertDatabaseCount('kegiatan', 1);
    }

    public function test_tanggal_penutupan_lewat_juga_mengunci_kegiatan(): void
    {
        $kegiatan = $this->kegiatan();
        JadwalTahunan::create(['renstra_id' => $this->renstraUji()->id, 'tahun' => 2026, 'penutupan' => today()->subDay()->toDateString(), 'status' => 'aktif']);

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Ditolak', 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertSessionHasErrors('tahun');
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_simpan_mengunci_jadwal_tahun_sebelum_kegiatan_searah_pengajuan_pengukuran(): void
    {
        $kegiatan = $this->kegiatan();
        $kunci = [];
        DB::listen(function ($query) use (&$kunci): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'for update') || str_contains($sql, 'for share')) {
                $kunci[] = $sql;
            }
        });

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", [...$this->payload(), 'nama' => 'Nama baru', 'expected_state' => $kegiatan->fresh()->versionToken()])
            ->assertSessionHasNoErrors();

        // Pengajuan pengukuran mengunci jadwal lalu kegiatan; urutan terbalik di sini dapat deadlock.
        $jadwal = array_key_first(array_filter($kunci, fn (string $sql): bool => str_contains($sql, 'from "jadwal_tahunan"')));
        $baris = array_key_first(array_filter($kunci, fn (string $sql): bool => str_contains($sql, 'from "kegiatan"')));
        $this->assertNotNull($jadwal);
        $this->assertNotNull($baris);
        $this->assertLessThan($baris, $jadwal);
    }

    public function test_lanjutan_tetap_pada_tahun_asal_dan_periode_lebih_akhir(): void
    {
        // Q69 §69.5 butir 2 tetap berlaku saat lanjutan tanpa klaim diubah lewat form biasa.
        $tw1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $tw2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        $tw3 = Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => false]);
        $asal = Kegiatan::create([...$this->payload(), 'periode_id' => $tw2->id, 'status' => 'ditunda', 'created_by' => $this->perencanaan->id]);
        $lanjutan = Kegiatan::create([...$this->payload(), 'periode_id' => $tw3->id, 'kegiatan_asal_id' => $asal->id, 'created_by' => $this->perencanaan->id]);
        $ubah = fn (array $extra) => $this->actingAs($this->pegawai)->post("/kegiatan/{$lanjutan->id}",
            [...$this->payload(), 'periode_id' => $tw3->id, 'expected_state' => $lanjutan->fresh()->versionToken(), ...$extra]);

        $ubah(['tahun' => 2027])->assertSessionHasErrors(['tahun' => 'Kegiatan lanjutan harus tetap pada tahun kegiatan asal.']);
        foreach ([$tw2, $tw1] as $periode) {
            $ubah(['periode_id' => $periode->id])->assertSessionHasErrors(['periode_id' => 'Periode kegiatan lanjutan harus lebih akhir dari periode kegiatan asal.']);
        }
        $this->assertSame([2026, $tw3->id], [(int) $lanjutan->fresh()->tahun, $lanjutan->fresh()->periode_id]);

        // Pindah ke periode lain yang tetap lebih akhir boleh, begitu juga perubahan tanpa tahun dan periode.
        $ubah(['periode_id' => $this->periode->id])->assertSessionHasNoErrors();
        $this->assertSame($this->periode->id, $lanjutan->fresh()->periode_id);
        $ubah(['periode_id' => $this->periode->id, 'anggaran' => 2000000])->assertSessionHasNoErrors();
        $this->assertSame('2000000', (string) $lanjutan->fresh()->anggaran);
    }

    public function test_ubah_dengan_rencana_aksi_id_mengalihkan_ke_halaman_ubah_ra(): void
    {
        $rencanaAksi = $this->rencanaAksiUji();
        $kegiatan = $this->kegiatan();
        $halamanUbah = route('rencana-aksi.kegiatan.edit', [
            'rencanaAksi' => $rencanaAksi->id, 'kegiatan' => $kegiatan->id,
        ]);

        $this->actingAs($this->perencanaan)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana berkonteks RA', 'rencana_aksi_id' => $rencanaAksi->id,
            'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertRedirect($halamanUbah)
            ->assertSessionHas('success', 'Rencana kegiatan berhasil diperbarui.');
        $this->assertSame('Rencana berkonteks RA', $kegiatan->fresh()->nama);

        // Tanpa `rencana_aksi_id`, form non-RA tetap memakai `back()` seperti sebelumnya.
        $asal = $this->halamanKegiatan();
        $this->from($asal)->actingAs($this->perencanaan)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana tanpa konteks', 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]))->assertRedirect($asal)->assertSessionHasNoErrors();
        $this->assertSame('Rencana tanpa konteks', $kegiatan->fresh()->nama);
    }

    public function test_simpan_dua_kali_memakai_token_baru_dari_halaman_ubah_ra(): void
    {
        $rencanaAksi = $this->rencanaAksiUji();
        $kegiatan = $this->kegiatan();
        KlaimKegiatan::create([
            'rencana_aksi_id' => $rencanaAksi->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->perencanaan->id, 'created_at' => now(),
        ]);
        $halamanUbah = route('rencana-aksi.kegiatan.edit', [
            'rencanaAksi' => $rencanaAksi->id, 'kegiatan' => $kegiatan->id,
        ]);

        $tokenAwal = $kegiatan->fresh()->versionToken();
        $this->actingAs($this->perencanaan)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana langkah pertama', 'rencana_aksi_id' => $rencanaAksi->id,
            'expected_state' => $tokenAwal,
        ]))->assertRedirect($halamanUbah)->assertSessionHasNoErrors();

        // Halaman edit hasil redirect membawa token versi baru, bukan token sebelum simpan.
        $tokenBaru = null;
        $this->actingAs($this->perencanaan)->get($halamanUbah)->assertOk()->assertInertia(
            function (Assert $page) use (&$tokenBaru, $kegiatan): void {
                $page->component('RencanaAksi/KegiatanEdit')
                    ->where('kegiatan.id', $kegiatan->id)
                    ->where('kegiatan.version_token', function (string $token) use (&$tokenBaru): bool {
                        $tokenBaru = $token;

                        return strlen($token) === 64;
                    });
            },
        );
        $this->assertNotNull($tokenBaru);
        $this->assertNotSame($tokenAwal, $tokenBaru);

        $this->from($halamanUbah)->actingAs($this->perencanaan)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana langkah kedua', 'rencana_aksi_id' => $rencanaAksi->id,
            'expected_state' => $tokenBaru,
        ]))->assertRedirect($halamanUbah)->assertSessionHasNoErrors();
        $this->assertSame('Rencana langkah kedua', $kegiatan->fresh()->nama);
    }

    public function test_rencana_aksi_id_tidak_sah_ditolak_validasi_tanpa_mutasi(): void
    {
        $kegiatan = $this->kegiatan();
        $payload = array_replace($this->payload(), [
            'nama' => 'Tidak boleh tersimpan', 'expected_state' => $kegiatan->fresh()->versionToken(),
        ]);

        foreach (['bukan-uuid', (string) Str::uuid()] as $rencanaAksiId) {
            $this->actingAs($this->perencanaan)
                ->postJson("/kegiatan/{$kegiatan->id}", [...$payload, 'rencana_aksi_id' => $rencanaAksiId])
                ->assertUnprocessable()->assertJsonValidationErrors('rencana_aksi_id');
        }

        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah')->exists());
    }

    /** Rencana Aksi draf pada unit uji hanya untuk membuktikan konteks redirect form kegiatan. */
    private function rencanaAksiUji(): RencanaAksi
    {
        $indikator = $this->targetIndicator($this->perencanaan);
        $indikator->update(['unit_id' => $this->unit->id]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $indikator->sasaranStrategis->renstra_id, 'tahun' => 2026,
            'penutupan' => '2026-12-31', 'status' => 'draft',
        ]);

        return RencanaAksi::create([
            'indikator_id' => $indikator->id, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $jadwal->id,
            'penanggung_jawab_id' => $this->perencanaan->id, 'tahun' => 2026, 'created_by' => $this->perencanaan->id,
        ]);
    }

    private function halamanKegiatan(?string $rencanaAksiId = null): string
    {
        return route('rencana-aksi.kegiatan.index', ['rencanaAksi' => $rencanaAksiId ?? $this->rencanaAksiId]);
    }

    private function renstraUji(): Renstra
    {
        return Renstra::create(['kode' => 'R-'.Str::random(6), 'nama' => 'Renstra uji', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $this->perencanaan->id]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $role)->value('id'), ['id' => (string) Str::uuid(), 'diberikan_oleh' => $user->id, 'sumber_pemberian' => 'manual', 'created_at' => now()]);

        return $user;
    }

    private function payload(): array
    {
        return ['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->periode->id, 'nama' => 'Rencana baru', 'tujuan' => 'Mendukung kinerja unit', 'sasaran_peserta' => 0, 'keterangan_peserta' => null, 'lokasi' => null, 'tanggal_rencana' => null, 'anggaran' => 1500000];
    }

    private function kegiatan(?Unit $unit = null): Kegiatan
    {
        return Kegiatan::create(array_replace($this->payload(), ['unit_id' => ($unit ?? $this->unit)->id, 'nama' => 'Rencana awal', 'created_by' => $this->perencanaan->id]));
    }

    private function deny(User $user, string $permission, ?string $unit): void
    {
        UserPermissionDeny::create(['user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => $unit, 'alasan' => 'Pembatasan pengujian', 'ditetapkan_oleh' => $this->perencanaan->id]);
    }
}
