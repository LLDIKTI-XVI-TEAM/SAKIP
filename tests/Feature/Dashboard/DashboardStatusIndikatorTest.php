<?php

namespace Tests\Feature\Dashboard;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\RencanaAksiVersi;
use App\Models\SasaranStrategis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DashboardStatusIndikatorTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    /** @return array<string, mixed> */
    private function props(string $query = ''): array
    {
        return $this->actingAs($this->actor)->get('/dashboard'.$query)->assertOk()->viewData('page')['props'];
    }

    /** @return array<string, mixed>|null */
    private function baris(array $props, string $kode): ?array
    {
        return collect($props['statusIndikator'])->firstWhere('indikator.kode', $kode);
    }

    private function tolak(string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    public function test_indikator_tanpa_pengukuran_dihitung_belum_mengisi(): void
    {
        $this->indikatorBaru('I-DUA');

        $props = $this->props();

        $this->assertSame(1, $props['ringkasanStatus']['belum_mengisi']);
        $this->assertSame(1, $props['ringkasanStatus']['dalam_proses']);
        $this->assertSame(['total' => 2, 'belum_mengisi' => 1, 'draft' => 1, 'diajukan' => 0, 'diverifikasi' => 0, 'dikembalikan' => 0, 'disahkan' => 0], $props['stats']);
        $this->assertSame('belum_mengisi', $this->baris($props, 'I-DUA')['status']);
        $this->assertNull($this->baris($props, 'I-DUA')['action']);
    }

    public function test_periode_sebelum_efektif_tidak_berlaku_dan_tidak_dihitung(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->indikatorBaru('I-DUA', snapshot: ['periode_mulai_id' => $tw2->periode_id]);

        $props = $this->props();

        $this->assertSame('tidak_berlaku', $this->baris($props, 'I-DUA')['status']);
        $this->assertSame(1, $props['ringkasanStatus']['tidak_berlaku']);
        $this->assertSame(1, $props['stats']['total']);
    }

    public function test_indikator_arsip_bukan_kewajiban_baru(): void
    {
        $dua = $this->indikatorBaru('I-DUA');
        IndikatorKinerja::whereIn('id', [$dua->indikator_id, $this->pengukuran->indikator_id])->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $props = $this->props();

        $this->assertNull($this->baris($props, 'I-DUA'));
        $this->assertNotNull($this->baris($props, 'I-UJI'));
        $this->assertSame(1, $props['stats']['total']);
    }

    public function test_angka_resmi_dari_versi_disahkan_bukan_master_atau_snapshot_jadwal(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80', 'target' => '75', 'target_pk' => '72', 'indikator' => ['nama' => 'Nama Beku']]);
        IndikatorKinerja::whereKey($this->pengukuran->indikator_id)->update(['nama' => 'Nama Master Baru']);

        $row = $this->baris($this->props(), 'I-UJI');

        $this->assertSame(['belum_ditetapkan', '80', '75', '72', '8', 'Nama Beku', 'disahkan'],
            [$row['status'], $row['realisasi'], $row['target'], $row['target_pk'], $row['selisih'], $row['indikator']['nama'], $row['status_alur']]);
    }

    public function test_tanpa_target_periode_menampilkan_realisasi_target_pk_dan_selisih(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '65.5', 'target' => null, 'tanpa_target_periode' => true, 'target_pk' => '70']);

        $props = $this->props();
        $row = $this->baris($props, 'I-UJI');

        $this->assertSame(['tanpa_target_periode', '65.5', null, true, '70', '-4.5'],
            [$row['status'], $row['realisasi'], $row['target'], $row['tanpa_target_periode'], $row['target_pk'], $row['selisih']]);
        $this->assertSame([1, 0], [$props['ringkasanStatus']['tanpa_target_periode'], $props['ringkasanStatus']['belum_ditetapkan']]);
    }

    public function test_belum_dapat_diukur_bukan_nol_dan_menang_atas_tanpa_target(): void
    {
        $this->sahkan($this->pengukuran, ['status_perhitungan' => 'belum_dapat_diukur']);
        $this->sahkan($this->headerUntuk($this->indikatorBaru('I-DUA')), ['status_perhitungan' => 'belum_dapat_diukur', 'target' => null, 'tanpa_target_periode' => true]);

        $props = $this->props();

        foreach (['I-UJI', 'I-DUA'] as $kode) {
            $row = $this->baris($props, $kode);
            $this->assertSame(['belum_dapat_diukur', null, null], [$row['status'], $row['realisasi'], $row['selisih']], $kode);
        }
        $this->assertSame(2, $props['ringkasanStatus']['belum_dapat_diukur']);
    }

    public function test_realisasi_nol_berbeda_dari_kosong(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '0']);

        $row = $this->baris($this->props(), 'I-UJI');

        $this->assertSame(['belum_ditetapkan', '0', '-70'], [$row['status'], $row['realisasi'], $row['selisih']]);
    }

    public function test_draft_lewat_jendela_tidak_mengisi_kecuali_periode_lampau(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 20));
        $this->assertSame('tidak_mengisi', $this->baris($this->props(), 'I-UJI')['status']);

        // Aktivasi setelah jendela TW I selesai menjadikan TW I periode lampau 2026 (Q46 §46.1).
        DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->update(['activated_at' => Carbon::parse('2026-03-18 02:00:00', 'UTC')]);
        $this->assertSame('dalam_proses', $this->baris($this->props(), 'I-UJI')['status']);
    }

    public function test_periode_belum_dibuka_nol_di_progres(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->headerUntuk($this->context, $tw2->periode_id);
        $this->travelTo(now()->setDate(2026, 3, 10));

        $props = $this->props();

        $this->assertSame([true, 1], [$props['progresPeriode'][0]['dibuka'], $props['progresPeriode'][0]['total']]);
        $this->assertSame([false, 0, 0], [$props['progresPeriode'][1]['dibuka'], $props['progresPeriode'][1]['total'], $props['progresPeriode'][1]['draft']]);
    }

    public function test_deny_mengikuti_unit_snapshot_bukan_unit_master(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $this->indikatorBaru('I-DUA', indikator: ['unit_id' => $unitB->id]);

        $this->tolak($unitB->id);
        $this->assertNotNull($this->baris($this->props(), 'I-DUA'));

        $this->tolak($this->unit->id);
        $this->assertNull($this->baris($this->props(), 'I-DUA'));
    }

    public function test_filter_sasaran_dan_unit_menyaring_baris(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $sasaran = SasaranStrategis::create(['renstra_id' => $this->jadwal->renstra_id, 'kode' => 'S-DUA', 'deskripsi' => 'Sasaran Dua']);
        $this->indikatorBaru('I-DUA', indikator: ['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unitB->id], snapshot: ['unit_id' => $unitB->id]);

        $this->assertSame(['I-DUA'], collect($this->props('?sasaran_id='.$sasaran->id)['statusIndikator'])->pluck('indikator.kode')->all());
        $this->assertSame(['I-UJI'], collect($this->props('?unit_id='.$this->unit->id)['statusIndikator'])->pluck('indikator.kode')->all());
    }

    public function test_penanda_persetujuan_sendiri_dan_reviu_terlambat_tetap_tampil(): void
    {
        $this->indikatorBaru('I-DUA');
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $props = $this->props();

        $this->assertSame([true, false], [$this->baris($props, 'I-UJI')['self_approval'], $this->baris($props, 'I-UJI')['reviu_terlambat']]);
        $this->assertSame([false, false], [$this->baris($props, 'I-DUA')['self_approval'], $this->baris($props, 'I-DUA')['reviu_terlambat']]);
    }

    public function test_pengajuan_yang_direviu_memakai_pic_beku_versi_pengajuan(): void
    {
        $diverifikasi = $this->headerUntuk($this->indikatorBaru('I-DUA'));
        $draft = $this->headerUntuk($this->indikatorBaru('I-TIGA'));
        $beku = ['pic' => ['id' => $this->actor->id, 'nama' => 'PIC Saat Diajukan']];
        $this->ajukan($this->pengukuran, $beku);
        $this->ajukan($diverifikasi, $beku, 'diverifikasi');
        $picBaru = $this->userWithRole('pegawai');
        foreach ([$this->pengukuran, $diverifikasi, $draft] as $header) {
            PenugasanIndikator::create(['indikator_id' => $header->indikator_id, 'user_id' => $picBaru->id,
                'tanggal_mulai_berlaku' => '2026-03-10', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        }

        $props = $this->props();

        $this->assertSame('PIC Saat Diajukan', $this->baris($props, 'I-UJI')['pic']['nama']);
        $this->assertSame('PIC Saat Diajukan', $this->baris($props, 'I-DUA')['pic']['nama']);
        $this->assertSame($picBaru->nama, $this->baris($props, 'I-TIGA')['pic']['nama']);
    }

    public function test_grafik_memisahkan_seri_bila_satuan_snapshot_berubah(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $koreksi = $this->context->replicate();
        $koreksi->fill(['nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'periode_mulai_id' => $tw2->periode_id, 'satuan' => 'persen', 'desimal_tampilan' => 1])->save();
        $this->travelTo(now()->setDate(2026, 6, 5));

        $grafik = $this->props()['grafik'];

        $seri = collect($grafik['indikator'])->where('kode', 'I-UJI')->values();
        $this->assertSame([['poin', 2], ['persen', 1]], $seri->map(fn ($i) => [$i['satuan'], $i['desimal_tampilan']])->all());
        $this->assertNotSame($seri[0]['id'], $seri[1]['id']);
        $this->assertSame([$this->pengukuran->periode_id], array_column($grafik['seri'][$seri[0]['id']], 'periode_id'));
        $this->assertSame([$tw2->periode_id], array_column($grafik['seri'][$seri[1]['id']], 'periode_id'));
    }

    public function test_target_pengajuan_yang_direviu_beku_dari_versi_pengajuan(): void
    {
        $this->ajukan($this->pengukuran, ['target' => '65', 'tanpa_target_periode' => false], 'diverifikasi');
        // Revisi target RA sesudah pengajuan: versi RA baru yang disahkan; versi lama beku.
        RencanaAksiVersi::create([...$this->planVersion->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'nomor' => 2, 'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => null, 'status_perhitungan' => 'tidak_ditargetkan', 'komponen' => []]]]]);

        $row = $this->baris($this->props(), 'I-UJI');

        $this->assertSame(['dalam_proses', '65', false], [$row['status'], $row['target'], $row['tanpa_target_periode']]);
    }
}
