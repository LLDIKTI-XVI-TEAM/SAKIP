<?php

namespace Tests\Feature\Periode;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/** Halaman jadwal, Rencana Aksi, kegiatan, dan pengukuran membawa tanggal periode dari Master Periode yang berlaku saat ditampilkan. */
class RentangPeriodeHalamanTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    /** Nilai canary agar tidak tertukar dengan tanggal standar Triwulan I. */
    private const TANGGAL = ['bulan_mulai' => 1, 'hari_mulai' => 15, 'bulan_selesai' => 4, 'hari_selesai' => 14];

    public function test_halaman_terkait_periode_membawa_tanggal_master_saat_ini(): void
    {
        DB::table('periode')->where('id', $this->pengukuran->periode_id)->update(self::TANGGAL);
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan Triwulan I', 'anggaran' => 0, 'created_by' => $this->actor->id]);
        $this->actingAs($this->actor);

        $this->get('/jadwal/'.$this->jadwal->id)->assertOk()->assertInertia(function (Assert $page): void {
            foreach (self::TANGGAL as $kolom => $nilai) {
                $page->where("jadwal.periode.0.$kolom", $nilai);
            }
        });
        $this->get('/rencana-aksi/'.$this->plan->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('rencanaAksi.periode.0.tanggal', self::TANGGAL));
        // Permukaan kegiatan berkonteks Rencana Aksi (Q74 §74.1): tanggal periode ikut lewat props halaman RA.
        KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $kegiatan->id,
            'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $this->get("/rencana-aksi/{$this->plan->id}/kegiatan")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('RencanaAksi/Kegiatan')
                ->where('rencanaAksi.periode.0.tanggal', self::TANGGAL));
        $this->get("/rencana-aksi/{$this->plan->id}/kegiatan/{$kegiatan->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('RencanaAksi/KegiatanEdit')
                ->where('periodeOptions.0.tanggal', self::TANGGAL));
        $this->get('/pengukuran')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pengukurans.0.periode.tanggal', self::TANGGAL));
        $this->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pengukuran.periode_jadwal.tanggal', self::TANGGAL));
    }
}
