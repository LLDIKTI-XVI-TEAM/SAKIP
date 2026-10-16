<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class EnsureDraftPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private IndikatorKinerja $indicator;

    private Periode $tw1;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        // Fixture bersama membuat header; skenario pembentukan dimulai dari kewajiban tanpa header.
        $this->pengukuran->delete();
        $this->indicator = IndikatorKinerja::findOrFail($this->context->indikator_id);
        $this->tw1 = Periode::findOrFail($this->context->periode_mulai_id);
    }

    public function test_create_draft_dari_snapshot_lalu_replay_tanpa_header_atau_audit_kedua(): void
    {
        $perencana = $this->userWithRole('perencanaan');

        $response = $this->ensure($perencana, $this->context->id, $this->tw1->id);

        $created = PengukuranKinerja::sole();
        $response->assertRedirect(route('pengukuran.edit', $created->id));
        $this->assertSame('draft', $created->status_alur);
        $this->assertSame(1, $created->versi);
        $this->assertSame('belum_diisi', $created->status_perhitungan);
        $this->assertNull($created->nilai);
        $this->assertSame('manual', $created->sumber_nilai);
        $this->assertSame(2026, $created->tahun);
        $this->assertSame($this->context->id, $created->jadwal_snapshot_id);
        $this->assertSame($perencana->id, $created->created_by);

        $this->ensure($perencana, $this->context->id, $this->tw1->id)->assertRedirect(route('pengukuran.edit', $created->id));

        $repeated = PengukuranKinerja::sole();
        $this->assertSame($created->id, $repeated->id);
        $this->assertSame(1, $repeated->versi);
        $audit = AuditLog::where('tindakan', 'pengukuran.buat')->sole();
        $this->assertSame($created->id, $audit->objek_id);
        $this->assertSame('pengukuran:create', $audit->dasar_izin['permission']);
        $this->assertTrue($audit->dasar_izin['allowed']);
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    public function test_draft_komponen_tidak_memerlukan_rencana_aksi_sah(): void
    {
        [$indicator, $snapshot] = $this->indikatorBaru('penjumlahan', $this->tw1);

        $this->ensure($this->userWithRole('perencanaan'), $snapshot->id, $this->tw1->id)->assertRedirect();

        $created = PengukuranKinerja::where('indikator_id', $indicator->id)->sole();
        $this->assertSame('komponen', $created->sumber_nilai);
        $this->assertSame('belum_diisi', $created->status_perhitungan);
    }

    public function test_jalur_perencanaan_tidak_memerlukan_pj_atau_jendela_pic(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 20));

        $this->ensure($this->userWithRole('perencanaan'), $this->context->id, $this->tw1->id)->assertRedirect();

        $this->assertDatabaseCount('pengukuran_kinerjas', 1);
    }

    public function test_tahun_ditutup_menolak_pembentukan_kecuali_sesi_koreksi_mencakupnya(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 5));
        $planner = $this->userWithRole('perencanaan');

        $this->assertDitolak($this->ensure($planner, $this->context->id, $this->tw1->id), 'pengukuran', 'allow');

        $this->context->jadwal->update(['koreksi_mulai' => now()->subDay(), 'koreksi_sampai' => now()->addDay(),
            'lingkup_koreksi' => ['indikator_ids' => [$this->indicator->id], 'periode_ids' => [$this->tw1->id], 'jenis_objek' => ['pengukuran']]]);
        $this->ensure($planner, $this->context->id, $this->tw1->id)->assertRedirect();
        $this->assertDatabaseCount('pengukuran_kinerjas', 1);
    }

    public function test_pic_grant_create_efektif_dalam_jendela_inklusif_berhasil(): void
    {
        $pic = $this->picDenganGrant('pengukuran:create');

        $this->ensure($pic, $this->context->id, $this->tw1->id)->assertRedirect();

        $this->assertSame($pic->id, PengukuranKinerja::sole()->created_by);
    }

    public function test_pic_di_luar_jendela_ditolak_dengan_audit(): void
    {
        $pic = $this->picDenganGrant('pengukuran:create');
        $this->travelTo(now()->setDate(2026, 3, 16));

        $this->assertDitolak($this->ensure($pic, $this->context->id, $this->tw1->id), 'pengukuran', 'allow');
    }

    public function test_grant_tanpa_pj_efektif_ditolak_dengan_audit(): void
    {
        $pegawai = $this->userWithRole('pegawai');
        $this->grant($pegawai, 'pengukuran:create');

        $this->assertDitolak($this->ensure($pegawai, $this->context->id, $this->tw1->id), 'pengukuran', 'allow');
    }

    public function test_unit_nonaktif_ditolak_dengan_audit(): void
    {
        $this->unit->update(['status' => 'nonaktif']);

        $this->assertDitolak($this->ensure($this->userWithRole('perencanaan'), $this->context->id, $this->tw1->id), 'pengukuran', 'allow');
    }

    public function test_jadwal_tidak_aktif_ditolak_dengan_audit(): void
    {
        $this->jadwal->update(['status' => 'ditutup']);

        $this->assertDitolak($this->ensure($this->userWithRole('perencanaan'), $this->context->id, $this->tw1->id), 'pengukuran', 'allow');
    }

    public function test_indikator_arsip_menolak_header_baru_tetapi_histori_tetap_dapat_dibuka(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $tw2 = $this->periode(2, '2026-03-01', '2026-03-31');
        $this->ensure($perencana, $this->context->id, $tw2->id)->assertRedirect();
        $history = PengukuranKinerja::sole();
        $this->indicator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $this->ensure($perencana, $this->context->id, $tw2->id)->assertRedirect(route('pengukuran.edit', $history->id));
        $response = $this->ensure($perencana, $this->context->id, $this->tw1->id);

        $response->assertSessionHasErrors('indikator_id');
        $this->assertSame([$history->id], PengukuranKinerja::pluck('id')->all());
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    public function test_deny_unit_menang_atas_allow_peran(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $this->deny($perencana, 'pengukuran:create', $this->unit->id);

        $this->assertDitolak($this->ensure($perencana, $this->context->id, $this->tw1->id), null, 'explicit_deny');
    }

    public function test_izin_update_saja_tidak_membentuk_header(): void
    {
        $this->assertDitolak($this->ensure($this->picDenganGrant('pengukuran:update'), $this->context->id, $this->tw1->id), null, 'no_allow');
    }

    public function test_izin_create_saja_tidak_memberi_hak_simpan(): void
    {
        $pic = $this->picDenganGrant('pengukuran:create');
        $this->ensure($pic, $this->context->id, $this->tw1->id)->assertRedirect();
        $created = PengukuranKinerja::sole();

        $this->actingAs($pic)->post('/pengukuran/'.$created->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 70])->assertForbidden();

        $this->assertNull($created->fresh()->nilai);
    }

    public function test_akun_aktif_tanpa_peran_tidak_berwenang(): void
    {
        $tanpaPeran = User::factory()->create(['status' => 'aktif']);

        $this->assertDitolak($this->ensure($tanpaPeran, $this->context->id, $this->tw1->id), null, 'no_role');
    }

    public function test_pembaca_dapat_replay_tetapi_tidak_membentuk_header(): void
    {
        $pembaca = $this->userWithRole('pegawai');
        $this->assertDitolak($this->ensure($pembaca, $this->context->id, $this->tw1->id), null, 'no_allow');

        $this->ensure($this->userWithRole('perencanaan'), $this->context->id, $this->tw1->id)->assertRedirect();
        $existing = PengukuranKinerja::sole();

        $this->ensure($pembaca, $this->context->id, $this->tw1->id)->assertRedirect(route('pengukuran.edit', $existing->id));
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    public function test_replay_tanpa_izin_baca_403_tanpa_audit(): void
    {
        $this->ensure($this->userWithRole('perencanaan'), $this->context->id, $this->tw1->id)->assertRedirect();
        $pembaca = $this->userWithRole('pegawai');
        $this->deny($pembaca, 'pengukuran:read', $this->unit->id);

        $this->ensure($pembaca, $this->context->id, $this->tw1->id)->assertForbidden();

        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    public function test_input_tidak_sah_atau_konteks_asing_tidak_membuat_audit(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $asing = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);

        $this->actingAs($perencana)->post('/pengukuran/ensure-draft', ['jadwal_snapshot_id' => 'bukan-uuid', 'periode_id' => $this->tw1->id])->assertSessionHasErrors('jadwal_snapshot_id');
        $this->actingAs($perencana)->post('/pengukuran/ensure-draft', ['jadwal_snapshot_id' => $this->context->id, 'periode_id' => $this->tw1->id, 'indikator_id' => $this->indicator->id])->assertSessionHasErrors('indikator_id');
        $this->ensure($perencana, (string) Str::uuid7(), $this->tw1->id)->assertNotFound();
        $this->ensure($perencana, $this->context->id, $asing->id)->assertNotFound();

        $this->assertDatabaseCount('pengukuran_kinerjas', 0);
        $this->assertSame(0, AuditLog::where('tindakan', 'like', 'pengukuran.buat%')->count());
    }

    public function test_periode_belum_efektif_ditolak_tanpa_header(): void
    {
        $tw2 = $this->periode(2, '2026-03-01', '2026-03-31');
        $tw3 = $this->periode(3, '2026-03-01', '2026-03-31');
        [, $snapshot] = $this->indikatorBaru('manual', $tw3);

        $response = $this->ensure($this->userWithRole('perencanaan'), $snapshot->id, $tw2->id);

        $response->assertSessionHasErrors('periode_id');
        $this->assertDatabaseCount('pengukuran_kinerjas', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    public function test_absent_memakai_versi_efektif_terpilih_bukan_versi_terbesar(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $tw2 = $this->periode(2, '2026-03-01', '2026-03-31');
        $tw3 = $this->periode(3, '2026-03-01', '2026-03-31');
        $v2 = $this->versiBaru(2, $tw3, $this->unit);

        $this->ensure($perencana, $v2->id, $tw2->id)->assertSessionHasErrors('jadwal_snapshot_id');
        $this->ensure($perencana, $this->context->id, $tw2->id)->assertRedirect();

        $this->assertSame($this->context->id, PengukuranKinerja::sole()->jadwal_snapshot_id);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    public function test_versi_efektif_terbesar_dipilih_walau_periode_mulai_lebih_awal(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $tw3 = $this->periode(3, '2026-03-01', '2026-03-31');
        $tw4 = $this->periode(4, '2026-03-01', '2026-03-31', true);
        $v2 = $this->versiBaru(2, $tw3, $this->unit);
        $v3 = $this->versiBaru(3, $this->tw1, $this->unit);

        $this->ensure($perencana, $v2->id, $tw4->id)->assertSessionHasErrors('jadwal_snapshot_id');
        $this->ensure($perencana, $v3->id, $tw4->id)->assertRedirect();

        $this->assertSame($v3->id, PengukuranKinerja::sole()->jadwal_snapshot_id);
    }

    public function test_replay_existing_tetap_dijepit_dan_memakai_unit_header(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $tw2 = $this->periode(2, '2026-03-01', '2026-03-31');
        $this->ensure($perencana, $this->context->id, $tw2->id)->assertRedirect();
        $existing = PengukuranKinerja::sole();
        $unitLain = Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->actor->id]);
        $v3 = $this->versiBaru(3, $this->tw1, $unitLain);
        // Replay tidak memeriksa guard insert: jadwal, unit, dan arsip berubah tidak menghalangi pembacaan.
        $this->indicator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->travelTo(now()->setDate(2027, 2, 1));
        $pembacaA = $this->userWithRole('pegawai');
        $this->deny($pembacaA, 'pengukuran:read', $unitLain->id);
        $pembacaB = $this->userWithRole('pegawai');
        $this->deny($pembacaB, 'pengukuran:read', $this->unit->id);

        $this->ensure($pembacaA, $v3->id, $tw2->id)->assertRedirect(route('pengukuran.edit', $existing->id));
        $this->ensure($pembacaB, $v3->id, $tw2->id)->assertForbidden();

        $fresh = $existing->fresh();
        $this->assertSame([$this->context->id, 1, null], [$fresh->jadwal_snapshot_id, $fresh->versi, $fresh->nilai]);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.buat')->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->count());
    }

    private function ensure(User $user, string $snapshotId, string $periodeId): TestResponse
    {
        return $this->actingAs($user)->post('/pengukuran/ensure-draft', ['jadwal_snapshot_id' => $snapshotId, 'periode_id' => $periodeId]);
    }

    /** Penolakan create: status/field sesuai jenis, tanpa header, satu audit dengan dasar izin aktual dan konteks tervalidasi. */
    private function assertDitolak(TestResponse $response, ?string $field, string $reason): void
    {
        $field === null ? $response->assertForbidden() : $response->assertSessionHasErrors($field);
        $this->assertDatabaseCount('pengukuran_kinerjas', 0);
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.buat')->count());
        $audit = AuditLog::where('tindakan', 'pengukuran.buat_ditolak')->sole();
        $this->assertSame('pengukuran:create', $audit->dasar_izin['permission']);
        $this->assertSame($reason, $audit->dasar_izin['reason']);
        $this->assertSame($this->context->id, $audit->nilai_baru['jadwal_snapshot_id']);
        $this->assertSame($this->tw1->id, $audit->nilai_baru['periode_id']);
        $this->assertSame($this->indicator->id, $audit->nilai_baru['indikator_id']);
        $this->assertNotNull($audit->objek_id);
    }

    private function picDenganGrant(string $permission): User
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, $permission);
        PenugasanIndikator::create(['indikator_id' => $this->indicator->id, 'user_id' => $pic->id, 'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $pic;
    }

    private function deny(User $user, string $permission, ?string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function periode(int $urutan, string $mulai, string $selesai, bool $final = false): Periode
    {
        $periode = Periode::create(['nama' => 'Triwulan '.$urutan, 'urutan' => $urutan, 'aktif' => true, 'is_nilai_akhir' => $final]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $selesai, 'reviu_mulai' => $selesai, 'reviu_selesai' => '2026-12-31']);

        return $periode;
    }

    private function versiBaru(int $nomor, Periode $mulai, Unit $unit): JadwalSnapshot
    {
        return JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'nomor_versi' => $nomor, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Versi sintetis', 'rujukan_koreksi' => 'Fixture', 'periode_mulai_id' => $mulai->id, 'unit_id' => $unit->id]);
    }

    /** @return array{0: IndikatorKinerja, 1: JadwalSnapshot} indikator baru tanpa Rencana Aksi beserta snapshot V1-nya */
    private function indikatorBaru(string $tipe, Periode $mulai): array
    {
        $indicator = IndikatorKinerja::create([...$this->indicator->only(['sasaran_strategis_id', 'unit_id', 'satuan', 'status', 'tahun_mulai_berlaku', 'created_by', 'created_by_role']),
            'kode' => 'I-BARU-'.$tipe, 'nama' => 'Indikator Baru', 'tipe_perhitungan' => $tipe]);
        $snapshot = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'unit_id', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target']),
            'indikator_id' => $indicator->id, 'periode_mulai_id' => $mulai->id, 'nama' => 'Indikator Baru', 'tipe_perhitungan' => $tipe]);

        return [$indicator, $snapshot];
    }
}
