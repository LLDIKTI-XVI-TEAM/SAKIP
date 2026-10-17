<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksiVersi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PengukuranTanpaTargetTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesPengukuranFixture;

    #[DataProvider('kondisiTarget')]
    public function test_pengajuan_membedakan_tanpa_target_sah_dari_target_belum_lengkap(bool $annual, bool $final, bool $past, ?int $value, string $status, bool $allowed): void
    {
        $this->pengukuran->periode->update(['is_nilai_akhir' => $final]);
        $last = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => ! $final]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $last->id, 'pengisian_mulai' => '2026-12-01',
            'pengisian_selesai' => '2026-12-15', 'reviu_mulai' => '2026-12-15', 'reviu_selesai' => '2026-12-31']);
        if ($past) {
            $this->jadwal->update(['activated_at' => '2026-03-15 16:00:00']);
            $this->travelTo(now()->setDate(2026, 4, 1)->setTime(9, 0));
        }
        $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'definisi',
            'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target', 'baseline']),
            'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'dinilai_tahunan' => $annual]);
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id]);
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $context->id, 'nomor' => 2,
            'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => $value, 'status_perhitungan' => $status, 'komponen' => []]]],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);

        if ($allowed || $status === 'belum_diisi') {
            $this->actingAs($this->actor)->get('/pengukuran')->assertInertia(fn ($page) => $page
                ->where('pengukurans.0.pengukuran.tanpa_target_periode', $status === 'tidak_ditargetkan' && $value === null)
                ->where('pengukurans.0.pengukuran.target', $value));
            $originalPeriod = $this->pengukuran->periode_id;
            $this->pengukuran->update(['periode_id' => $last->id]);
            $this->get('/pengukuran')->assertInertia(fn ($page) => $page
                ->where('pengukurans.0.jenis', 'absent')->where('pengukurans.0.target', $value)
                ->where('pengukurans.0.tanpa_target_periode', $status === 'tidak_ditargetkan' && $value === null));
            $this->pengukuran->update(['periode_id' => $originalPeriod]);
        }
        $response = $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75]);
        if (! $allowed) {
            $response->assertUnprocessable()->assertJsonValidationErrors('pengajuan');
            $this->assertDatabaseCount('pengukuran_versi', 0);
            $this->assertSame(['draft', 1, null], [$this->pengukuran->fresh()->status_alur, $this->pengukuran->fresh()->versi, $this->pengukuran->fresh()->nilai]);
            $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());

            return;
        }
        $response->assertRedirect('/pengukuran');
        $version = $this->pengukuran->fresh()->latestVersion;
        $this->assertSame($value === null ? null : '0.00', $version->snapshot['target']);
        $this->assertSame($value === null, $version->snapshot['tanpa_target_periode']);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.tanpa_target_periode', $value === null)
            ->where('pengukuran.selisih_target_pk', '5.000000000000'));
        // Perubahan RA sesudah submit tidak boleh mengganti penanda pada daftar versi beku.
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $context->id, 'nomor' => 3,
            'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => 100, 'status_perhitungan' => 'terhitung', 'komponen' => []]]],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        foreach (['/pengukuran', '/verifikasi'] as $route) {
            $prefix = $route === '/pengukuran' ? 'pengukurans.0.pengukuran.' : 'pengukurans.0.';
            $this->get($route)->assertInertia(fn ($page) => $page
                ->where($prefix.'tanpa_target_periode', $value === null)
                ->where($prefix.'target', $value === null ? null : '0.00'));
        }
    }

    public static function kondisiTarget(): array
    {
        return ['annual sebelum final' => [true, false, false, null, 'tidak_ditargetkan', true],
            'nonannual normal' => [false, false, false, null, 'tidak_ditargetkan', false],
            'final normal' => [true, true, false, null, 'tidak_ditargetkan', false],
            'target belum diisi' => [true, false, false, null, 'belum_diisi', false],
            'target nol' => [true, false, false, 0, 'terhitung', true],
            'nol bukan tanpa target' => [true, false, false, 0, 'tidak_ditargetkan', false],
            'nonannual lampau saat aktivasi' => [false, false, true, null, 'tidak_ditargetkan', true]];
    }

    #[DataProvider('komponenTanpaTarget')]
    public function test_target_rasio_tidak_ditargetkan_harus_memuat_seluruh_komponen_kosong(string $condition, bool $allowed, bool $unmeasurable = false): void
    {
        $final = Periode::create(['nama' => 'Periode akhir', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $final->id, 'pengisian_mulai' => '2026-12-01',
            'pengisian_selesai' => '2026-12-15', 'reviu_mulai' => '2026-12-15', 'reviu_selesai' => '2026-12-31']);
        $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'definisi',
            'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target']), 'nomor_versi' => 2, 'menggantikan_id' => $this->context->id,
            'tipe_perhitungan' => 'rasio_persen', 'dinilai_tahunan' => true, 'komposisi_final' => false]);
        $definitions = [];
        foreach (['pembilang', 'penyebut'] as $position => $role) {
            $component = IndikatorKomponen::create(['indikator_id' => $this->pengukuran->indikator_id, 'kode' => $role, 'label' => $role,
                'peran' => $role, 'bobot' => 1, 'urutan' => $position + 1, 'aktif' => true, 'created_by' => $this->actor->id]);
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $context->id, 'komponen_id' => $component->id,
                'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => $position + 1]);
            $definitions[] = ['komponen_id' => $component->id, 'nilai' => null];
        }
        $context->update(['komposisi_final' => true]);
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'komponen']);
        $targets = $definitions;
        if ($condition === 'partial') {
            $targets[0]['nilai'] = 0;
        }
        if ($condition === 'duplicate') {
            $targets[1]['komponen_id'] = $targets[0]['komponen_id'];
        }
        if ($condition === 'missing-value') {
            unset($targets[0]['nilai']);
        }
        if ($condition === 'missing-component') {
            array_pop($targets);
        }
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $context->id, 'nomor' => 2,
            'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => null, 'status_perhitungan' => 'tidak_ditargetkan', 'komponen' => $targets]]],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        $input = [['komponen_id' => $definitions[0]['komponen_id'], 'nilai' => 8], ['komponen_id' => $definitions[1]['komponen_id'], 'nilai' => 10]];
        $response = $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan',
            'komponen' => $unmeasurable ? [] : $input, 'status_perhitungan' => $unmeasurable ? 'belum_dapat_diukur' : null]);
        if ($allowed) {
            $response->assertRedirect('/pengukuran');
            $this->assertTrue($this->pengukuran->fresh()->latestVersion->snapshot['tanpa_target_periode']);
            $this->assertSame($unmeasurable ? null : '80.000000000000', $this->pengukuran->fresh()->latestVersion->snapshot['nilai']);
            $this->assertSame($unmeasurable ? 'belum_dapat_diukur' : 'terhitung', $this->pengukuran->fresh()->latestVersion->snapshot['status_perhitungan']);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('pengajuan');
            $this->assertDatabaseCount('pengukuran_versi', 0);
            $this->assertDatabaseCount('pengukuran_komponen', 0);
        }
    }

    public static function komponenTanpaTarget(): array
    {
        return ['semua kosong' => ['empty', true], 'realisasi belum dapat diukur' => ['empty', true, true], 'nol dan kosong' => ['partial', false],
            'ID duplikat' => ['duplicate', false], 'nilai absen' => ['missing-value', false], 'komponen absen' => ['missing-component', false]];
    }
}
