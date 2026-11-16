<?php

namespace Tests\Feature\Rekomendasi;

use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class HalamanRekomendasiTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_halaman_memuat_rekomendasi_berlaku_draf_dan_riwayat_per_indikator(): void
    {
        $pimpinan = $this->userWithRole('pimpinan');
        $this->rekomendasi('Arahan lama.', now()->subDay(), $pimpinan->id);
        $berlaku = $this->rekomendasi('Arahan terbaru.', now(), $this->actor->id, 'superadmin', 'Laporan Kinerja Triwulan I 2026');
        $draf = $this->rekomendasi('Usulan perubahan.');

        $this->actingAs($this->userWithRole('perencanaan'))->get('/rekomendasi')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Rekomendasi/Index')
            ->where('filters', ['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->periode_id])
            ->where('pilihan_jadwal.0.periode.0.id', $this->pengukuran->periode_id)
            ->where('persetujuan_cadangan', true)
            ->has('rekomendasi', 1)
            ->where('rekomendasi.0.indikator', ['id' => $this->pengukuran->indikator_id, 'kode' => 'I-UJI', 'nama' => 'Indikator Uji', 'unit' => 'Unit Pengujian'])
            ->where('rekomendasi.0.tahun', 2026)
            ->where('rekomendasi.0.berlaku.id', $berlaku)
            ->where('rekomendasi.0.berlaku.isi', 'Arahan terbaru.')
            ->where('rekomendasi.0.berlaku.disetujui_oleh', $this->actor->nama)
            ->where('rekomendasi.0.berlaku.persetujuan_cadangan', true)
            ->where('rekomendasi.0.berlaku.rujukan_sumber', 'Laporan Kinerja Triwulan I 2026')
            ->where('rekomendasi.0.draf.id', $draf)
            ->where('rekomendasi.0.draf.versi', 1)
            ->where('rekomendasi.0.draf.penyusun', $this->actor->nama)
            ->has('rekomendasi.0.riwayat', 1)
            ->where('rekomendasi.0.riwayat.0.isi', 'Arahan lama.')
            ->where('rekomendasi.0.riwayat.0.persetujuan_cadangan', false));

        $this->actingAs($pimpinan)->get('/rekomendasi')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('persetujuan_cadangan', false));
    }

    public function test_halaman_hanya_untuk_pemegang_izin_dan_capability_menu_mengikutinya(): void
    {
        foreach (['pegawai', 'admin'] as $kode) {
            $this->actingAs($this->userWithRole($kode))->get('/rekomendasi')->assertForbidden();
        }
        $this->actingAs($this->userWithRole('pimpinan'))->get('/rekomendasi')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.rekomendasi', true));
        $this->actingAs($this->userWithRole('pegawai'))->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.rekomendasi', false));
    }

    public function test_pasangan_jadwal_dan_periode_tidak_sah_ditolak(): void
    {
        $bukanAnggota = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);

        $this->actingAs($this->userWithRole('perencanaan'))->from('/rekomendasi')
            ->get('/rekomendasi?jadwal_id='.$this->jadwal->id.'&periode_id='.$bukanAnggota->id)->assertSessionHasErrors('periode_id');
    }

    private function rekomendasi(string $isi, mixed $disetujuiPada = null, ?string $penyetuju = null, string $peran = 'pimpinan', ?string $rujukan = null): string
    {
        $id = (string) Str::uuid();
        DB::table('rekomendasi_pimpinan')->insert(['id' => $id, 'indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'isi' => $isi, 'ditetapkan_oleh' => $this->actor->id, 'rujukan_sumber' => $rujukan, 'created_at' => now(),
            ...($disetujuiPada === null ? [] : ['status' => 'disetujui', 'disetujui_oleh' => $penyetuju, 'disetujui_oleh_role' => $peran, 'disetujui_pada' => $disetujuiPada])]);

        return $id;
    }
}
