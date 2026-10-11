<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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
        foreach (['kegiatan:create', 'kegiatan:update'] as $code) {
            UserPermissionGrant::create(['user_id' => $this->pegawai->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id, 'alasan' => 'Penugasan kegiatan unit', 'diberikan_oleh' => $this->perencanaan->id]);
        }
    }

    public function test_pegawai_tanpa_pj_dan_jadwal_membuat_rencana_dengan_audit_dan_tanpa_input_fase_lanjut(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['tanggal_rencana' => '2026-10-17']) + [
            'anggaran' => ['bukan' => 'angka'], 'status' => 'terlaksana', 'created_by' => $this->perencanaan->id,
            'tanggal_realisasi' => '2026-10-12', 'uraian_pelaksanaan' => 'Tidak boleh tersimpan',
        ])->assertRedirect('/kegiatan')->assertSessionHasNoErrors();

        $kegiatan = Kegiatan::sole();
        $this->assertSame('rencana', $kegiatan->status);
        $this->assertSame($this->pegawai->id, $kegiatan->created_by);
        $this->assertNull($kegiatan->anggaran);
        $this->assertNull($kegiatan->tanggal_realisasi);
        $this->assertNull($kegiatan->uraian_pelaksanaan);
        $this->assertSame('2026-10-17', $kegiatan->tanggal_rencana->format('Y-m-d'));
        $audit = AuditLog::where('tindakan', 'kegiatan.buat')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame('rencana', $audit->nilai_baru['status']);
        $this->assertNotEmpty($audit->dasar_izin);
        $this->assertDatabaseCount('penanggung_jawab', 0);
        $this->assertDatabaseCount('jadwal_tahunan', 0);
    }

    public function test_ubah_data_rencana_mempertahankan_status_anggaran_dan_data_pelaksanaan(): void
    {
        $kegiatan = $this->kegiatan();
        $kegiatan->forceFill(['status' => 'terlaksana', 'anggaran' => '100.000000000000', 'uraian_pelaksanaan' => 'Narasi histori'])->save();
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana diperbarui', 'expected_state' => $this->editToken($kegiatan),
            'status' => 'batal', 'anggaran' => null, 'uraian_pelaksanaan' => 'Payload tidak dipercaya',
        ]))->assertRedirect('/kegiatan')->assertSessionHasNoErrors();

        $this->assertSame('Rencana diperbarui', $kegiatan->fresh()->nama);
        $this->assertSame('terlaksana', $kegiatan->fresh()->status);
        $this->assertSame('100.000000000000', $kegiatan->fresh()->anggaran);
        $this->assertSame('Narasi histori', $kegiatan->fresh()->uraian_pelaksanaan);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah')->sole();
        $this->assertSame('Rencana awal', $audit->nilai_lama['nama']);
        $this->assertSame('Rencana diperbarui', $audit->nilai_baru['nama']);
    }

    public function test_request_lintas_unit_ditolak_dan_teraudit_tanpa_mutasi(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['unit_id' => $this->unitLain->id]))->assertForbidden();
        $kegiatan = $this->kegiatan($this->unitLain);
        // Mengirim unit A tidak boleh menyamarkan kepemilikan objek unit B.
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => 'token-tidak-dipakai'])->assertForbidden();
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame($this->unitLain->id, $kegiatan->fresh()->unit_id);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->count());
    }

    public function test_pemindahan_unit_memerlukan_izin_unit_tujuan_juga(): void
    {
        $kegiatan = $this->kegiatan();
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'unit_id' => $this->unitLain->id, 'expected_state' => $this->editToken($kegiatan),
        ]))->assertForbidden();
        $this->assertSame($this->unit->id, $kegiatan->fresh()->unit_id);
    }

    public function test_deny_ubah_mematikan_capability_dan_menolak_request_bahkan_superadmin(): void
    {
        $aktor = $this->user('superadmin');
        $kegiatan = $this->kegiatan();
        $this->deny($aktor, 'kegiatan:update', $this->unit->id);
        $this->actingAs($aktor)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->component('Kegiatan/Index')->where('kegiatan.data.0.can.update', false));
        $this->actingAs($aktor)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => 'token-tidak-dipakai'])->assertForbidden();
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $this->assertNotEmpty(AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->sole()->dasar_izin);
    }

    public function test_unit_nonaktif_dan_periode_nonaktif_atau_asing_ditolak_sebagai_validasi(): void
    {
        $this->unitLain->update(['status' => 'nonaktif']);
        $this->actingAs($this->perencanaan)->postJson('/kegiatan', array_replace($this->payload(), ['unit_id' => $this->unitLain->id]))->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->periode->update(['aktif' => false]);
        $this->actingAs($this->perencanaan)->postJson('/kegiatan', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('periode_id');
        $this->actingAs($this->perencanaan)->postJson('/kegiatan', array_replace($this->payload(), ['periode_id' => (string) Str::uuid()]))->assertUnprocessable()->assertJsonValidationErrors('periode_id');
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_baca_global_menyaring_deny_unit_dan_menghormati_deny_global(): void
    {
        $this->kegiatan();
        $barisB = $this->kegiatan($this->unitLain);
        $this->deny($this->pegawai, 'kegiatan:read', $this->unit->id);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->component('Kegiatan/Index')
            ->has('kegiatan.data', 1)->where('kegiatan.data.0.id', $barisB->id)->where('kegiatan.data.0.can.update', false)->where('can.create', true)->missing('kegiatan.data.0.anggaran'));
        $this->deny($this->pegawai, 'kegiatan:read', null);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertForbidden();
    }

    public function test_form_hanya_memberikan_unit_yang_boleh_ditulis_tanpa_izin_unit_read(): void
    {
        $this->actingAs($this->pegawai)->get('/kegiatan/buat')->assertInertia(fn (Assert $page) => $page->component('Kegiatan/Form')
            ->where('unitOptions.0.id', $this->unit->id)->has('unitOptions', 1)->where('periodeOptions.0.id', $this->periode->id)->missing('kegiatan.anggaran'));
        $this->actingAs($this->user('pegawai'))->get('/kegiatan/buat')->assertForbidden();
        $this->actingAs($this->pegawai)->get('/kegiatan/bukan-uuid/edit')->assertNotFound();
    }

    public function test_stale_edit_tidak_menimpa_data_terbaru(): void
    {
        $kegiatan = $this->kegiatan();
        $token = $this->editToken($kegiatan);
        $kegiatan->update(['nama' => 'Perubahan pengguna lain']);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => $token])->assertUnprocessable()->assertJsonValidationErrors('expected_state');
        $this->assertSame('Perubahan pengguna lain', $kegiatan->fresh()->nama);
    }

    public function test_gagal_audit_membatalkan_pembuatan(): void
    {
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Audit gagal sintetis'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->pegawai)->post('/kegiatan', $this->payload());
            $this->fail('Mutasi seharusnya dibatalkan bila audit gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit gagal sintetis', $exception->getMessage());
        }
        $this->assertDatabaseCount('kegiatan', 0);
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

    public function test_grant_unit_yang_dinonaktifkan_mendapat_error_master_spesifik(): void
    {
        $this->unit->update(['status' => 'nonaktif']);
        $this->actingAs($this->pegawai)->postJson('/kegiatan', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_filter_status_batal_dan_pagination_dikerjakan_server(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->kegiatan()->update(['status' => 'batal']);
        }
        $this->kegiatan();
        $this->actingAs($this->pegawai)->get('/kegiatan?status=batal&tahun=2026&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('kegiatan.total', 21)->has('kegiatan.data', 1)->where('kegiatan.data.0.status', 'batal'));
    }

    public function test_konteks_kegiatan_yang_sudah_diklaim_tidak_dapat_dipindah_tetapi_nama_boleh_diubah(): void
    {
        $indikator = $this->targetIndicator($this->perencanaan);
        $indikator->update(['unit_id' => $this->unit->id]);
        $jadwal = JadwalTahunan::create(['renstra_id' => $indikator->sasaranStrategis->renstra_id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => 'draft']);
        $ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $jadwal->id, 'penanggung_jawab_id' => $this->pegawai->id, 'tahun' => 2026, 'created_by' => $this->perencanaan->id]);
        $kegiatan = $this->kegiatan();
        KlaimKegiatan::create(['rencana_aksi_id' => $ra->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->perencanaan->id, 'created_at' => now()]);
        $token = $this->editToken($kegiatan);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['tahun' => 2027, 'expected_state' => $token]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->assertSame(2026, $kegiatan->fresh()->tahun);
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['nama' => 'Nama dikoreksi', 'unit_id' => strtoupper($this->unit->id), 'periode_id' => strtoupper($this->periode->id), 'expected_state' => $token]))->assertRedirect('/kegiatan')->assertSessionHasNoErrors();
        $this->assertSame('Nama dikoreksi', $kegiatan->fresh()->nama);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $role)->value('id'), ['id' => (string) Str::uuid(), 'diberikan_oleh' => $user->id, 'sumber_pemberian' => 'manual', 'created_at' => now()]);

        return $user;
    }

    private function payload(): array
    {
        return ['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->periode->id, 'nama' => 'Rencana baru', 'tujuan' => 'Mendukung kinerja unit', 'sasaran_peserta' => 0, 'keterangan_peserta' => null, 'lokasi' => null, 'tanggal_rencana' => null];
    }

    private function editToken(Kegiatan $kegiatan): string
    {
        return $this->actingAs($this->pegawai)->get("/kegiatan/{$kegiatan->id}/edit")->viewData('page')['props']['kegiatan']['version_token'];
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
