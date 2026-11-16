<?php

namespace Tests\Feature\TargetTahunan;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/** Saran baseline editor target PK tahun T dari realisasi akhir T−1; jadwal 2026 fixture menjadi sumber T−1 untuk PK 2027. */
class SaranBaselineTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    private const KOSONG = ['nilai' => null, 'periode' => null];

    private ?PeriodeJadwal $twIv = null;

    public function test_saran_dari_realisasi_akhir_yang_disahkan(): void
    {
        $header = $this->sahkanTwIv(['nilai' => '82.500000000000']);
        // Nama master diubah sesudah pengesahan; keterangan tetap memakai nama beku di versi.
        Periode::whereKey($header->periode_id)->update(['nama' => 'TW Akhir']);

        $this->assertSame(['status' => 'disahkan', 'nilai' => '82.5', 'periode' => 'Triwulan IV'], $this->saran($this->editor()));
    }

    public function test_saran_tetap_muncul_bila_target_tersimpan_dan_baseline_kosong(): void
    {
        $this->sahkanTwIv(['nilai' => '82.500000000000']);
        $this->targetTersimpan(['target_tahunan' => '80']);

        $response = $this->editor();

        $this->assertSame('disahkan', $this->saran($response)['status']);
        $response->assertJsonPath('target_tahunan', '80')->assertJsonPath('baseline', null);
    }

    public function test_saran_mengabaikan_indikator_dan_periode_lain(): void
    {
        $twIv = $this->twIv();
        $this->sahkan($this->pengukuran, ['nilai' => '99.000000000000']);
        $this->sahkan($this->headerUntuk($this->indikatorBaru('I-LAIN'), $twIv->periode_id), ['nilai' => '99.000000000000']);

        $this->assertSame(['status' => 'belum_disahkan', ...self::KOSONG], $this->saran($this->editor()));
    }

    public function test_periode_nilai_akhir_berurutan_terbesar_dipakai_bila_jadwal_punya_lebih_dari_satu(): void
    {
        $header = $this->sahkanTwIv(['nilai' => '82.500000000000']);
        // Penggantian periode nilai akhir: periode lama dinonaktifkan dengan tanda tetap, pengganti belum diukur.
        Periode::whereKey($header->periode_id)->update(['aktif' => false]);
        Periode::whereKey($this->periode(5, '2027-01-01', '2027-01-31')->id)->update(['is_nilai_akhir' => true]);

        $this->assertSame(['status' => 'belum_disahkan', ...self::KOSONG], $this->saran($this->editor()));
    }

    public function test_saran_tidak_melompati_tahun(): void
    {
        $this->sahkanTwIv(['nilai' => '82.500000000000']);

        $this->assertSame(['status' => 'belum_ada', ...self::KOSONG], $this->saran($this->editor(2028)));
    }

    public function test_realisasi_nol_menjadi_saran_nol(): void
    {
        $this->sahkanTwIv(['nilai' => '0.000000000000']);

        $this->assertSame(['status' => 'disahkan', 'nilai' => '0', 'periode' => 'Triwulan IV'], $this->saran($this->editor()));
    }

    public function test_belum_disahkan_untuk_baris_kosong_diajukan_dan_dibuka_kembali(): void
    {
        $twIv = $this->twIv();
        $belumDisahkan = ['status' => 'belum_disahkan', ...self::KOSONG];
        $this->assertSame($belumDisahkan, $this->saran($this->editor()), 'Baris TW IV belum ada.');

        $header = $this->headerUntuk($this->context, $twIv->periode_id);
        $this->ajukan($header, ['nilai' => '82.500000000000']);
        $this->assertSame($belumDisahkan, $this->saran($this->editor()), 'Pengukuran baru diajukan.');

        // Versi tanpa nama periode beku memakai nama periode nilai akhir jadwal.
        $this->sahkan($header, ['nilai' => '82.500000000000']);
        $this->assertSame(['status' => 'disahkan', 'nilai' => '82.5', 'periode' => 'Triwulan IV'], $this->saran($this->editor()));

        // Simulasi buka kembali: header kembali ke alur, versi disahkan lama tetap tersimpan.
        $header->forceFill(['status_alur' => 'dikembalikan'])->save();
        $this->assertSame($belumDisahkan, $this->saran($this->editor()), 'Pengukuran dibuka kembali.');
    }

    public function test_tidak_dapat_dihitung(): void
    {
        $this->sahkanTwIv(['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung']);

        $this->assertSame(['status' => 'tidak_dapat_dihitung', ...self::KOSONG], $this->saran($this->editor()));
    }

    public function test_realisasi_negatif_tidak_menjadi_saran(): void
    {
        $this->sahkanTwIv(['nilai' => '-5.000000000000']);

        $this->assertSame(['status' => 'negatif', ...self::KOSONG], $this->saran($this->editor()));
    }

    public function test_belum_ada_tanpa_jadwal_draft_atau_tanpa_snapshot(): void
    {
        $this->sahkanTwIv(['nilai' => '82.500000000000']);
        $belumAda = ['status' => 'belum_ada', ...self::KOSONG];

        $this->assertSame($belumAda, $this->saran($this->editor(2026)), 'Tidak ada jadwal 2025.');

        $tanpaSnapshot = IndikatorKinerja::create([...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'satuan', 'tipe_perhitungan', 'created_by_role']),
            'kode' => 'I-TANPA', 'nama' => 'Indikator tanpa snapshot', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id]);
        $this->assertSame($belumAda, $this->saran($this->editor(indikatorId: $tanpaSnapshot->id)), 'Indikator tidak diukur jadwal 2026.');

        $this->jadwal->update(['status' => 'draft']);
        $this->assertSame($belumAda, $this->saran($this->editor()), 'Jadwal 2026 masih draft.');
    }

    public function test_baseline_tersimpan_menghentikan_saran(): void
    {
        $this->sahkanTwIv(['nilai' => '82.500000000000']);
        $target = $this->targetTersimpan(['baseline' => '0']);

        $response = $this->editor();
        $this->assertNull($this->saran($response));
        $response->assertJsonPath('baseline', '0');

        $target->update(['baseline' => '70']);
        $response = $this->editor();
        $this->assertNull($this->saran($response));
        $response->assertJsonPath('baseline', '70');
    }

    public function test_tanpa_hak_ubah_atau_baca_saja_tanpa_saran(): void
    {
        $this->sahkanTwIv(['nilai' => '82.500000000000']);
        $perencana = $this->userWithRole('perencanaan');
        $this->deny($perencana, 'target:update', null);
        $this->assertTanpaSaranKarenaBacaSaja($this->editor(actor: $perencana));

        $indikator = IndikatorKinerja::findOrFail($this->context->indikator_id);
        $indikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->assertTanpaSaranKarenaBacaSaja($this->editor());
        $indikator->update(['status' => IndikatorKinerja::STATUS_AKTIF]);

        Renstra::findOrFail($this->jadwal->renstra_id)->update(['status' => Renstra::STATUS_NONAKTIF]);
        $this->assertTanpaSaranKarenaBacaSaja($this->editor());
    }

    public function test_deny_pengukuran_read_menang_untuk_semua_status(): void
    {
        $this->twIv();
        $this->assertSame('belum_disahkan', $this->saran($this->editor(actor: $this->userWithRole('perencanaan')))['status']);
        $this->assertNull($this->saran($this->editor(actor: $this->perencanaTanpaBaca($this->unit->id))), 'Deny unit, baris TW IV belum ada.');

        $this->sahkanTwIv(['nilai' => '82.500000000000']);
        $this->assertSame('disahkan', $this->saran($this->editor(actor: $this->userWithRole('perencanaan')))['status']);
        foreach ([null, $this->unit->id] as $unitId) {
            $this->assertNull($this->saran($this->editor(actor: $this->perencanaTanpaBaca($unitId))), 'Deny '.($unitId ?? 'global').', versi disahkan ada.');
        }

        $this->jadwal->update(['status' => 'draft']);
        $this->assertSame('belum_ada', $this->saran($this->editor(actor: $this->userWithRole('perencanaan')))['status']);
        foreach ([null, $this->unit->id] as $unitId) {
            $this->assertNull($this->saran($this->editor(actor: $this->perencanaTanpaBaca($unitId))), 'Deny '.($unitId ?? 'global').', sumber T−1 tidak ada.');
        }
    }

    public function test_hak_baca_memakai_unit_versi_snapshot_efektif(): void
    {
        $this->twIv();
        $unitMaster = $this->unitBaru('Unit Master Baru');
        $unitBelumEfektif = $this->unitBaru('Unit Versi Belum Efektif');
        // Unit master dipindah sesudah snapshot dibuat; versi kedua baru efektif sesudah periode nilai akhir.
        IndikatorKinerja::findOrFail($this->context->indikator_id)->update(['unit_id' => $unitMaster->id]);
        $this->versiBaru(2, $this->periode(5, '2027-01-01', '2027-01-31'), $unitBelumEfektif);

        $this->assertNull($this->saran($this->editor(actor: $this->perencanaTanpaBaca($this->unit->id))), 'Deny unit versi efektif.');
        $this->assertSame('belum_disahkan', $this->saran($this->editor(actor: $this->perencanaTanpaBaca($unitMaster->id)))['status'], 'Deny unit master.');
        $this->assertSame('belum_disahkan', $this->saran($this->editor(actor: $this->perencanaTanpaBaca($unitBelumEfektif->id)))['status'], 'Deny unit versi belum efektif.');
    }

    public function test_konfigurasi_tidak_lengkap_tanpa_saran(): void
    {
        foreach ([$this->actor, $this->userWithRole('perencanaan')] as $aktor) {
            $this->assertNull($this->saran($this->editor(actor: $aktor)), 'Jadwal 2026 tanpa periode nilai akhir.');
        }

        $this->twIv();
        $belumEfektif = $this->indikatorBaru('I-P5', snapshot: ['periode_mulai_id' => $this->periode(5, '2027-01-01', '2027-01-31')->id]);
        foreach ([$this->actor, $this->userWithRole('perencanaan')] as $aktor) {
            $this->assertNull($this->saran($this->editor(actor: $aktor, indikatorId: $belumEfektif->indikator_id)), 'Tidak ada versi efektif pada periode nilai akhir.');
        }
    }

    public function test_membuka_editor_tidak_menulis(): void
    {
        $this->sahkanTwIv(['nilai' => '82.500000000000']);
        $this->targetTersimpan(['target_tahunan' => '80']);
        $this->pk(2027);
        $targets = DB::table('target_kinerjas')->get();
        $audit = DB::table('audit_log')->count();

        $pertama = $this->editor();
        $kedua = $this->editor();

        $this->assertSame('disahkan', $this->saran($kedua)['status']);
        $this->assertEquals($targets, DB::table('target_kinerjas')->get());
        $this->assertSame($audit, DB::table('audit_log')->count());
        $this->assertSame($pertama->json('expected_state'), $kedua->json('expected_state'));
    }

    private function pk(int $tahun): RenstraPk
    {
        return RenstraPk::firstOrCreate(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => $tahun],
            ['nomor_pk' => 'PK-UJI-'.$tahun, 'tanggal_pk' => $tahun.'-01-01', 'created_by' => $this->actor->id]);
    }

    private function editor(int $tahun = 2027, ?User $actor = null, ?string $indikatorId = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->actor)
            ->getJson('/perjanjian-kinerja/'.$this->pk($tahun)->id.'/target/'.($indikatorId ?? $this->context->indikator_id).'/editor');
    }

    /** Key wajib ada lebih dulu, karena asersi null juga lulus bila key tidak dikirim. */
    private function saran(TestResponse $response): ?array
    {
        $response->assertOk();
        $this->assertArrayHasKey('saran_baseline', $response->json());

        return $response->json('saran_baseline');
    }

    private function assertTanpaSaranKarenaBacaSaja(TestResponse $response): void
    {
        $response->assertJsonPath('can.update', false);
        $this->assertNull($this->saran($response));
    }

    /** Periode nilai akhir jadwal 2026; tanpa ini jadwal tidak punya periode nilai akhir. */
    private function twIv(): PeriodeJadwal
    {
        return $this->twIv ??= $this->tambahPeriode(4, '2026-12-01', '2026-12-31');
    }

    /** Nilai header sengaja berbeda dari versi disahkan agar terbukti saran diambil dari versi. */
    private function sahkanTwIv(array $snapshot): PengukuranKinerja
    {
        $header = $this->headerUntuk($this->context, $this->twIv()->periode_id);
        $header->forceFill(['nilai' => '60', 'status_perhitungan' => 'terhitung'])->save();
        $this->sahkan($header, ['periode' => ['id' => $header->periode_id, 'nama' => 'Triwulan IV', 'urutan' => 4, 'is_nilai_akhir' => true], ...$snapshot]);

        return $header;
    }

    private function targetTersimpan(array $nilai): TargetKinerja
    {
        return TargetKinerja::create(['indikator_kinerja_id' => $this->context->indikator_id, 'tahun' => 2027, 'updated_by' => $this->actor->id, ...$nilai]);
    }

    /** Deny bersifat kumulatif per pengguna, jadi setiap kasus memakai perencana baru. */
    private function perencanaTanpaBaca(?string $unitId): User
    {
        $perencana = $this->userWithRole('perencanaan');
        $this->deny($perencana, 'pengukuran:read', $unitId);

        return $perencana;
    }

    private function deny(User $user, string $permission, ?string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function periode(int $urutan, string $mulai, string $selesai): Periode
    {
        $periode = Periode::create(['nama' => 'Triwulan '.$urutan, 'urutan' => $urutan, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $selesai, 'reviu_mulai' => $selesai, 'reviu_selesai' => $selesai]);

        return $periode;
    }

    private function versiBaru(int $nomor, Periode $mulai, Unit $unit): JadwalSnapshot
    {
        return JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'nomor_versi' => $nomor, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Versi sintetis', 'rujukan_koreksi' => 'Fixture', 'periode_mulai_id' => $mulai->id, 'unit_id' => $unit->id]);
    }
}
