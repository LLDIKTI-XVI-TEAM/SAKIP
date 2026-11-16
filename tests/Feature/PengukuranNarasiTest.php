<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PengukuranNarasiTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesPengukuranFixture;

    private function input(): array
    {
        return ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75,
            'progres_kegiatan' => 'Pendampingan tahap pertama selesai.',
            'kendala_masalah' => 'Sebagian data masih dikonfirmasi.',
            'strategi_tindak_lanjut' => 'Menuntaskan konfirmasi pada periode berikutnya.', 'catatan' => null];
    }

    #[DataProvider('narasiWajib')]
    public function test_narasi_kosong_menolak_pengajuan_tanpa_mutasi_parsial(string $field, ?string $value): void
    {
        $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, [...$this->input(), $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->pengukuran->refresh();
        $this->assertSame(['draft', 1, null], [$this->pengukuran->status_alur, $this->pengukuran->versi, $this->pengukuran->nilai]);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public static function narasiWajib(): array
    {
        return ['progres kosong' => ['progres_kegiatan', null], 'kendala kosong' => ['kendala_masalah', null],
            'strategi spasi' => ['strategi_tindak_lanjut', '   ']];
    }

    public function test_narasi_lengkap_menggantikan_kewajiban_catatan_flag_lama(): void
    {
        $this->pengukuran->indikator->update(['wajib_catatan' => true]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, $this->input())
            ->assertSessionHasNoErrors()->assertRedirect('/pengukuran');
        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
        $this->assertTrue($this->pengukuran->indikator->fresh()->wajib_catatan);
    }

    public function test_narasi_pengukuran_dibekukan_dan_dibaca_dari_versi_pengajuan(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, $this->input())->assertSessionHasNoErrors();
        $version = $this->pengukuran->fresh()->latestVersion;
        foreach (['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut'] as $field) {
            $this->assertArrayHasKey($field, $version->snapshot);
            $this->assertSame($this->input()[$field], $version->snapshot[$field]);
        }
        $this->pengukuran->update(['progres_kegiatan' => 'Header berubah setelah submit.']);
        $this->assertSame($this->input()['progres_kegiatan'], $version->fresh()->snapshot['progres_kegiatan']);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.progres_kegiatan', $this->input()['progres_kegiatan'])
            ->where('pengukuran.kendala_masalah', $this->input()['kendala_masalah'])
            ->where('pengukuran.strategi_tindak_lanjut', $this->input()['strategi_tindak_lanjut']));
    }

    public function test_draft_boleh_menyimpan_narasi_belum_lengkap_dan_checklist_tetap_dari_server(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, [...$this->input(), 'action' => 'draft', 'kendala_masalah' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->input()['progres_kegiatan'], $this->pengukuran->fresh()->progres_kegiatan);
        $this->assertNull($this->pengukuran->fresh()->kendala_masalah);
        $this->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertInertia(fn ($page) => $page
            ->where('pengukuran.prasyarat.siap', false)
            ->where('pengukuran.prasyarat.alasan', ['Kendala/masalah wajib diisi sebelum pengajuan.'])
            ->where('pengukuran.can.submit', true));
        $this->assertDatabaseCount('pengukuran_versi', 0);
    }

    public function test_narasi_lengkap_dengan_nilai_memburuk_tetap_boleh_tanpa_catatan(): void
    {
        $this->pengukuran->periode->update(['urutan' => 2]);
        $previousPeriod = Periode::create(['nama' => 'Periode sebelumnya', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $previous = PengukuranKinerja::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $previousPeriod->id,
            'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id, 'status_alur' => 'disahkan']);
        KinerjaSnapshot::create(['pengukuran_id' => $previous->id, 'jadwal_snapshot_id' => $this->context->id, 'nomor' => 1,
            'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => [],
            'snapshot' => ['nilai' => '90.000000000000'], 'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, $this->input())->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
    }

    public function test_periode_lampau_ditentukan_saat_aktivasi_bukan_saat_submit(): void
    {
        $this->jadwal->update(['activated_at' => '2026-03-15 16:00:00']);
        $this->travelTo(now()->setDate(2026, 4, 1)->setTime(9, 0));
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, [...$this->input(),
            'progres_kegiatan' => null, 'kendala_masalah' => null, 'strategi_tindak_lanjut' => null])->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
    }
}
