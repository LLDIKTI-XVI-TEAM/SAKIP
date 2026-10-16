<?php

namespace Tests\Feature\Pengukuran;

use App\Models\AuditLog;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\RencanaAksiVersi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PenyebutNolTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private const PESAN_ALASAN = 'Alasan penyebut nol wajib diisi.';

    public function test_draft_tanpa_alasan_boleh_tetapi_pengajuan_ditolak_tanpa_versi_dan_teraudit(): void
    {
        [$n, $t] = $this->ratioContext();
        $url = '/pengukuran/'.$this->pengukuran->id;
        $komponen = fn ($a, $b) => ['komponen' => [['komponen_id' => $n, 'nilai' => $a], ['komponen_id' => $t, 'nilai' => $b]]];

        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', ...$komponen(5, 0)])->assertSessionHasNoErrors();
        $this->assertSame('tidak_dapat_dihitung', $this->pengukuran->fresh()->status_perhitungan);
        $this->assertNull($this->pengukuran->fresh()->nilai);
        $this->get($url.'/edit')->assertOk()->assertInertia(fn ($page) => $page->where('pengukuran.can.submit', false)
            ->where('pengukuran.prasyarat.alasan', fn ($alasan) => collect($alasan)->contains(self::PESAN_ALASAN)));
        foreach ([null, '   '] as $alasan) {
            $this->post($url, ['versi' => 2, 'action' => 'ajukan', ...$komponen(5, 0), 'alasan_tidak_dapat_dihitung' => $alasan])
                ->assertSessionHasErrors(['pengajuan' => self::PESAN_ALASAN]);
        }
        $this->postJson($url, ['versi' => 2, 'action' => 'ajukan', ...$komponen(5, 0)])
            ->assertUnprocessable()->assertJsonValidationErrors(['pengajuan' => self::PESAN_ALASAN]);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame('draft', $this->pengukuran->fresh()->status_alur);
        $ditolak = AuditLog::where('tindakan', 'pengukuran.ditolak')->get();
        $this->assertCount(3, $ditolak);
        $this->assertTrue($ditolak->every(fn ($a) => $a->nilai_baru['tindakan_diminta'] === 'ajukan' && $a->nilai_baru['jenis_penolakan'] === 'validasi_bisnis'));
        // Spasi lewat HTTP sudah menjadi null oleh middleware; cabang trim() dikunci lewat nilai DB langsung.
        PengukuranKinerja::whereKey($this->pengukuran->id)->update(['alasan_tidak_dapat_dihitung' => '   ']);
        $this->get($url.'/edit')->assertOk()->assertInertia(fn ($page) => $page->where('pengukuran.can.submit', false)
            ->where('pengukuran.prasyarat.alasan', fn ($alasan) => collect($alasan)->contains(self::PESAN_ALASAN)));
    }

    public function test_ajukan_dari_draft_terhitung_dengan_input_penyebut_nol_tanpa_alasan_dibatalkan_utuh(): void
    {
        [$n, $t] = $this->ratioContext();
        $url = '/pengukuran/'.$this->pengukuran->id;
        $komponen = fn ($a, $b) => ['komponen' => [['komponen_id' => $n, 'nilai' => $a], ['komponen_id' => $t, 'nilai' => $b]]];

        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', ...$komponen(5, 10)])->assertSessionHasNoErrors();
        $this->assertSame('50.000000000000', $this->pengukuran->fresh()->nilai);
        $this->post($url, ['versi' => 2, 'action' => 'ajukan', ...$komponen(5, 0)])
            ->assertSessionHasErrors(['pengajuan' => self::PESAN_ALASAN]);
        $measurement = $this->pengukuran->fresh();
        $this->assertSame('terhitung', $measurement->status_perhitungan);
        $this->assertSame('50.000000000000', $measurement->nilai);
        $this->assertSame(2, $measurement->versi);
        $this->assertDatabaseHas('pengukuran_komponen', ['pengukuran_id' => $measurement->id, 'komponen_id' => $t, 'nilai' => 10]);
        $this->assertDatabaseCount('pengukuran_versi', 0);
    }

    public function test_komponen_belum_lengkap_tidak_memakai_pengecualian_penyebut_nol(): void
    {
        [$n, $t] = $this->ratioContext();
        $url = '/pengukuran/'.$this->pengukuran->id;
        $komponen = fn ($a, $b) => ['komponen' => [['komponen_id' => $n, 'nilai' => $a], ['komponen_id' => $t, 'nilai' => $b]]];
        $kurang = [...$komponen(null, 0), 'alasan_tidak_dapat_dihitung' => 'Populasi nol.'];

        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', ...$kurang])->assertSessionHasNoErrors();
        $measurement = $this->pengukuran->fresh();
        $this->assertSame('belum_diisi', $measurement->status_perhitungan);
        $this->assertNull($measurement->nilai);
        $this->assertNull($measurement->alasan_tidak_dapat_dihitung);
        $this->post($url, ['versi' => 2, 'action' => 'ajukan', ...$kurang])->assertSessionHasErrors('pengajuan');
        $pesan = session('errors')->get('pengajuan');
        $this->assertContains('Nilai manual atau seluruh komponen snapshot wajib diisi.', $pesan);
        $this->assertNotContains(self::PESAN_ALASAN, $pesan);
    }

    public function test_nol_faktual_rasio_tersimpan_terhitung_bukan_null(): void
    {
        [$n, $t] = $this->ratioContext();
        $url = '/pengukuran/'.$this->pengukuran->id;
        $komponen = fn ($a, $b) => ['komponen' => [['komponen_id' => $n, 'nilai' => $a], ['komponen_id' => $t, 'nilai' => $b]]];

        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', ...$komponen(0, 10)])->assertSessionHasNoErrors();
        $measurement = $this->pengukuran->fresh();
        $this->assertSame('terhitung', $measurement->status_perhitungan);
        $this->assertSame('0.000000000000', $measurement->nilai);
    }

    public function test_versi_diajukan_dan_disahkan_menampilkan_penanda_dan_alasan_dari_snapshot(): void
    {
        [$n, $t] = $this->ratioContext();
        $url = '/pengukuran/'.$this->pengukuran->id;
        $komponen = fn ($a, $b) => ['komponen' => [['komponen_id' => $n, 'nilai' => $a], ['komponen_id' => $t, 'nilai' => $b]]];
        $alasan = 'Tidak ada mahasiswa baru pada periode ini.';

        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', ...$komponen(5, 0), 'alasan_tidak_dapat_dihitung' => $alasan])->assertSessionHasNoErrors();
        $this->get($url.'/edit')->assertOk()->assertInertia(fn ($page) => $page->where('pengukuran.can.submit', true)->where('pengukuran.prasyarat.siap', true));
        $this->post($url, ['versi' => 2, 'action' => 'ajukan', ...$komponen(5, 0), 'alasan_tidak_dapat_dihitung' => $alasan])
            ->assertSessionHasNoErrors()->assertRedirect('/pengukuran');
        $versi = KinerjaSnapshot::sole()->snapshot;
        $this->assertSame('tidak_dapat_dihitung', $versi['status_perhitungan']);
        $this->assertNull($versi['nilai']);
        $this->assertSame($alasan, $versi['alasan_tidak_dapat_dihitung']);
        // Bukti halaman membaca snapshot, bukan baris hidup.
        PengukuranKinerja::whereKey($this->pengukuran->id)->update(['alasan_tidak_dapat_dihitung' => 'Teks baris hidup berubah.']);
        $reviewer = $this->userWithRole('perencanaan');
        $beku = fn (string $status) => $this->actingAs($reviewer)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()
            ->assertInertia(fn ($page) => $page->component('Verifikasi/Show')->where('pengukuran.status', $status)
                ->where('pengukuran.status_perhitungan', 'tidak_dapat_dihitung')->where('pengukuran.nilai', null)
                ->where('pengukuran.alasan_tidak_dapat_dihitung', $alasan));
        $beku('diajukan');
        foreach (['verifikasi', 'sahkan'] as $aksi) {
            $this->actingAs($reviewer)->post('/verifikasi/'.$this->pengukuran->id.'/'.$aksi, ['versi' => $this->pengukuran->fresh()->versi])->assertSessionHasNoErrors();
        }
        $beku('disahkan');
        $this->actingAs($this->userWithRole('pegawai'))->get('/verifikasi/'.$this->pengukuran->id)->assertForbidden();
    }

    /** Salinan fixture rasio dari CanonicalPengukuranTest; sengaja tidak dipindah ke trait bersama. */
    private function ratioContext(): array
    {
        $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target']),
            'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Konteks rasio sintetis', 'rujukan_koreksi' => 'Fixture', 'tipe_perhitungan' => 'rasio_persen']);
        $ids = [];
        foreach (['pembilang', 'penyebut'] as $index => $role) {
            $c = IndikatorKomponen::create(['indikator_id' => $this->pengukuran->indikator_id, 'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => $index + 1, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $context->id, 'komponen_id' => $c->id, 'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => $index + 1]);
            $ids[] = $c->id;
        }
        $this->plan->update(['jadwal_snapshot_id' => $context->id]);
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $context->id, 'nomor' => 2, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => 80, 'status_perhitungan' => 'terhitung', 'komponen' => [['komponen_id' => $ids[0], 'nilai' => 8], ['komponen_id' => $ids[1], 'nilai' => 10]]]]],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'komponen']);
        $this->pengukuran->refresh();

        return $ids;
    }
}
