<?php

namespace Tests\Feature\Rekomendasi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RekomendasiPimpinan;
use App\Models\RenstraPk;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DetailRekomendasiTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_detail_memuat_metadata_dan_seluruh_rangkaian_pada_kombinasi_yang_sama_tanpa_mutasi(): void
    {
        $lama = $this->rekomendasi('Arahan lama.', now()->subDays(2));
        $berlaku = $this->rekomendasi('Arahan berlaku.', now()->subDay());
        $draf = $this->rekomendasi('Usulan perubahan.');
        $this->rekomendasi('Tahun berbeda.', now(), ['tahun' => 2025]);
        $lain = $this->periode(2);
        $this->rekomendasi('Periode berbeda.', now(), ['periode_id' => $lain->id]);
        $unitBaru = Unit::create(['nama' => 'Unit master terbaru', 'created_by' => $this->actor->id]);
        $this->pengukuran->indikator->update(['unit_id' => $unitBaru->id, 'nama' => 'Nama master terbaru']);
        $audit = DB::table('audit_log')->count();
        $sebelum = $lama->getAttributes();

        $this->actingAs($this->actor)->get('/rekomendasi/'.$lama->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Rekomendasi/Show')
            ->where('indikator.kode', 'I-UJI')->where('indikator.nama', 'Indikator Uji')->where('indikator.unit', 'Unit Pengujian')
            ->where('periode', ['nama' => 'Triwulan I', 'tahun' => 2026])
            ->where('rekomendasi.id', $lama->id)->where('rekomendasi.status_tampilan', 'digantikan')
            ->where('rekomendasi.isi', 'Arahan lama.')->where('rekomendasi.penyusun', $this->actor->nama)
            ->where('rekomendasi.rujukan_sumber', 'Laporan sintetis')
            ->where('rekomendasi.penyetuju', $this->actor->nama)->where('rekomendasi.persetujuan_cadangan', true)
            ->where('rekomendasi.disetujui_oleh_role', 'superadmin')
            ->where('rekomendasi.created_at', $lama->created_at->toIso8601String())
            ->where('rekomendasi.disetujui_pada', $lama->disetujui_pada->toIso8601String())
            ->where('berlaku_url', '/rekomendasi/'.$berlaku->id)
            ->where('kelola_url', '/rekomendasi?jadwal_id='.$this->jadwal->id.'&periode_id='.$this->pengukuran->periode_id)
            ->has('riwayat.data', 3)->where('riwayat.data.0.id', $lama->id)
            ->where('riwayat.data.1.id', $berlaku->id)->where('riwayat.data.2.id', $draf->id)
            ->where('riwayat.data.2.status_tampilan', 'draf')->where('tindak_lanjut', null));

        $this->assertEquals($sebelum, $lama->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_log', $audit);
        $this->assertDatabaseCount('rekomendasi_pimpinan', 5);
        $this->deny('rekomendasi:tetapkan', 'sama');
        $this->get('/rekomendasi/'.$lama->id)->assertForbidden();
    }

    public function test_tautan_draf_tetap_valid_setelah_disetujui_dan_persetujuan_pimpinan_bukan_cadangan(): void
    {
        $draf = $this->rekomendasi('Usulan.');
        $this->actingAs($this->actor)->get('/rekomendasi/'.$draf->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rekomendasi.status_tampilan', 'draf')->where('rekomendasi.penyetuju', null)->where('berlaku_url', null));
        $draf->update(['status' => 'disetujui', 'disetujui_oleh' => $this->actor->id, 'disetujui_oleh_role' => 'pimpinan', 'disetujui_pada' => now()]);
        $this->get('/rekomendasi/'.$draf->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rekomendasi.status_tampilan', 'berlaku')->where('rekomendasi.persetujuan_cadangan', false));
    }

    public function test_tindak_lanjut_memilih_pengajuan_terbaru_yang_cocok_dan_memisahkan_status_header(): void
    {
        $r1 = $this->rekomendasi('R1', now()->subDays(2));
        $r2 = $this->rekomendasi('R2', now()->subDay());
        $p = $this->pengukuranBerikut();
        $this->versi($p, 1, $r1, 'Jawaban R1 pertama.');
        $this->versi($p, 2, $r1, 'Jawaban R1 terbaru.');
        $this->versi($p, 3, $r2, 'Jawaban R2.');
        $p->update(['status_alur' => 'disahkan', 'tindak_lanjut_rekomendasi' => 'Header hidup bukan sumber.']);

        $this->actingAs($this->actor)->get('/rekomendasi/'.$r1->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tindak_lanjut.isi', 'Jawaban R1 terbaru.')->where('tindak_lanjut.nomor', 2)
            ->where('tindak_lanjut.diajukan_pada', now()->toIso8601String())
            ->where('tindak_lanjut.status_pengukuran', 'disahkan')->where('tindak_lanjut.pengajuan_terdahulu', true)
            ->where('tindak_lanjut.periode', 'Triwulan II')->where('tindak_lanjut.tahun', 2026));
        $this->get('/rekomendasi/'.$r2->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tindak_lanjut.isi', 'Jawaban R2.')->where('tindak_lanjut.nomor', 3)->where('tindak_lanjut.pengajuan_terdahulu', false));
    }

    public function test_draf_pengukuran_dan_pengajuan_yang_membekukan_rekomendasi_lain_tidak_dianggap_tindak_lanjut(): void
    {
        $r1 = $this->rekomendasi('R1', now()->subDays(2));
        $r2 = $this->rekomendasi('R2', now()->subDay());
        $p = $this->pengukuranBerikut();
        $p->update(['tindak_lanjut_rekomendasi' => 'Pekerjaan yang belum diajukan.']);
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r1->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('tindak_lanjut', null));
        $this->versi($p, 1, $r1, 'Jawaban R1.');
        $p->update(['status_alur' => 'dikembalikan']);
        $this->get('/rekomendasi/'.$r2->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('tindak_lanjut', null));
        $this->get('/rekomendasi/'.$r1->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tindak_lanjut.isi', 'Jawaban R1.')->where('tindak_lanjut.status_pengukuran', 'dikembalikan'));
    }

    public function test_periode_berikutnya_tidak_melompati_periode_tanpa_pengukuran(): void
    {
        $r = $this->rekomendasi('R1', now());
        $this->periode(2);
        $p = $this->pengukuranBerikut(3);
        $this->versi($p, 1, $r, 'Bukan periode tepat berikutnya.');
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('tindak_lanjut', null));
    }

    public function test_tindak_lanjut_periode_terakhir_dapat_dibaca_lintas_tahun(): void
    {
        $this->pengukuran->periode->update(['nama' => 'Triwulan IV', 'urutan' => 4]);
        $r = $this->rekomendasi('Arahan akhir tahun.', now());
        $pk = RenstraPk::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2027, 'nomor_pk' => 'PK-2027', 'tanggal_pk' => '2027-01-01', 'created_by' => $this->actor->id]);
        $jadwal = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2027, 'renstra_pk_id' => $pk->id, 'penutupan' => '2027-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        $periode = $this->periode(1, $jadwal);
        $unitBaru = Unit::create(['nama' => 'Unit tahun berikutnya', 'created_by' => $this->actor->id]);
        $snapshot = JadwalSnapshot::create([...$this->context->only(['indikator_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']), 'unit_id' => $unitBaru->id, 'jadwal_id' => $jadwal->id, 'periode_mulai_id' => $periode->id]);
        $p = PengukuranKinerja::create(['indikator_id' => $r->indikator_id, 'tahun' => 2027, 'periode_id' => $periode->id, 'jadwal_snapshot_id' => $snapshot->id, 'sumber_nilai' => 'manual', 'status_alur' => 'diajukan', 'created_by' => $this->actor->id]);
        $this->versi($p, 1, $r, 'Jawaban awal tahun berikutnya.');
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tindak_lanjut.isi', 'Jawaban awal tahun berikutnya.')->where('tindak_lanjut.tahun', 2027));
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:read')->value('id'), 'unit_id' => $unitBaru->id,
            'alasan' => 'Pembatasan unit tujuan.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->get('/rekomendasi/'.$r->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('can.viewFollowUp', false)->missing('tindak_lanjut'));
    }

    #[DataProvider('cakupanDeny')]
    public function test_panel_tindak_lanjut_mematuhi_deny_pengukuran_tanpa_menolak_detail(string $scope, bool $diizinkan): void
    {
        $r = $this->rekomendasi('Arahan.', now());
        $p = $this->pengukuranBerikut();
        $this->versi($p, 1, $r, 'Jawaban yang dilindungi.');
        $this->deny('pengukuran:read', $scope);
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r->id)->assertOk()->assertInertia(fn (Assert $page) => $diizinkan
            ? $page->where('can.viewFollowUp', true)->where('tindak_lanjut.isi', 'Jawaban yang dilindungi.')
            : $page->where('can.viewFollowUp', false)->missing('tindak_lanjut'));
    }

    public static function cakupanDeny(): array
    {
        return ['global' => ['global', false], 'unit sama' => ['sama', false], 'unit lain' => ['lain', true]];
    }

    public function test_detail_menolak_izin_tidak_ada_akun_nonaktif_dan_deny_yang_cocok(): void
    {
        $r = $this->rekomendasi('Arahan.', now());
        foreach (['pegawai', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/rekomendasi/'.$r->id)->assertForbidden();
        }
        $this->actor->update(['status' => 'nonaktif']);
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r->id)->assertRedirect(route('auth.pending'));
        $this->actor->update(['status' => 'aktif']);
        $this->deny('rekomendasi:tetapkan', 'lain');
        $this->get('/rekomendasi/'.$r->id)->assertOk();
        $this->deny('rekomendasi:tetapkan', 'sama');
        $this->get('/rekomendasi/'.$r->id)->assertForbidden();
    }

    public function test_uuid_invalid_dan_record_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->actor)->get('/rekomendasi/bukan-uuid')->assertNotFound();
        $this->get('/rekomendasi/'.Str::uuid())->assertNotFound();
    }

    public function test_rangkaian_dipaginasi_dengan_draf_tetap_pada_akhir(): void
    {
        $r = $this->rekomendasi('Pertama.', now()->subDays(30));
        for ($i = 1; $i <= 20; $i++) {
            $this->rekomendasi('Arahan '.$i, now()->subDays(25)->addMinutes($i));
        }
        $draf = $this->rekomendasi('Draf terakhir.');
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('riwayat.data', 20)->where('riwayat.total', 22)->where('riwayat.data.0.id', $r->id));
        $this->get('/rekomendasi/'.$r->id.'?riwayat_page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('riwayat.data', 2)->where('riwayat.data.1.id', $draf->id));
    }

    public function test_pilih_iku_memakai_konteks_yang_sama_dan_menyaring_deny_unit_snapshot_terbaru(): void
    {
        $dibuka = $this->rekomendasi('Dibuka.', now()->subDays(2));
        $terbaru = $this->rekomendasi('Berlaku.', now()->subDay());
        $this->rekomendasi('Draf tidak menggantikan yang berlaku.');
        $periodeLain = $this->periode(2);
        $ditolak = Unit::create(['nama' => 'Unit ditolak', 'created_by' => $this->actor->id]);
        $this->deny('rekomendasi:tetapkan', 'lain');
        DB::table('user_permission_denied')->where('user_id', $this->actor->id)->update(['unit_id' => $ditolak->id]);
        $draf = null;
        foreach (['DRAF', 'DENY', 'TAHUN', 'PERIODE', 'KOSONG', 'MENDATANG'] as $kode) {
            $indikator = $this->pengukuran->indikator->replicate();
            $indikator->kode = $kode;
            $indikator->save();
            $snapshot = $this->context->replicate();
            $snapshot->indikator_id = $indikator->id;
            if ($kode === 'MENDATANG') {
                $snapshot->periode_mulai_id = $periodeLain->id;
            }
            $snapshot->save();
            if ($kode === 'DENY') {
                $baru = $snapshot->replicate();
                $baru->nomor_versi = 2;
                $baru->unit_id = $ditolak->id;
                $baru->save();
            }
            if ($kode !== 'KOSONG') {
                $r = $this->rekomendasi('Isi '.$kode, null, ['indikator_id' => $indikator->id,
                    'tahun' => $kode === 'TAHUN' ? 2025 : 2026,
                    'periode_id' => $kode === 'PERIODE' ? $periodeLain->id : $dibuka->periode_id]);
                if ($kode === 'DRAF') {
                    $draf = $r;
                }
            }
        }

        $this->actingAs($this->actor)->get('/rekomendasi/'.$dibuka->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('indikator.id', $dibuka->indikator_id)->has('pilihan_iku.data', 2)
            ->where('pilihan_iku.data.0.kode', 'DRAF')->where('pilihan_iku.data.0.url', '/rekomendasi/'.$draf->id)
            ->where('pilihan_iku.data.0.status', 'draf')->where('pilihan_iku.data.1.url', '/rekomendasi/'.$terbaru->id));
    }

    public function test_pilih_iku_dipaginasi_dan_dapat_dicari_berdasarkan_kode_atau_nama_snapshot(): void
    {
        $r = $this->rekomendasi('Dibuka.');
        for ($i = 1; $i <= 21; $i++) {
            $indikator = $this->pengukuran->indikator->replicate();
            $indikator->kode = sprintf('IKU-%02d', $i);
            $indikator->save();
            $snapshot = $this->context->replicate();
            $snapshot->indikator_id = $indikator->id;
            $snapshot->nama = 'Nama beku '.$i;
            $snapshot->save();
            $this->rekomendasi('Draf.', null, ['indikator_id' => $indikator->id]);
        }
        $this->actingAs($this->actor)->get('/rekomendasi/'.$r->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('pilihan_iku.data', 20)->where('pilihan_iku.per_page', 20));
        $this->get('/rekomendasi/'.$r->id.'?iku_page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('pilihan_iku.data', 2));
        foreach (['IKU-21', 'Nama beku 21'] as $cari) {
            $this->get('/rekomendasi/'.$r->id.'?cari_iku='.urlencode($cari))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('pilihan_iku.data', 1)->where('pilihan_iku.data.0.kode', 'IKU-21')->where('pencarian_iku', $cari));
        }
    }

    private function rekomendasi(string $isi, mixed $disetujuiPada = null, array $extra = []): RekomendasiPimpinan
    {
        return RekomendasiPimpinan::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'isi' => $isi, 'rujukan_sumber' => 'Laporan sintetis', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()->subDays(3),
            ...($disetujuiPada ? ['status' => 'disetujui', 'disetujui_oleh' => $this->actor->id, 'disetujui_oleh_role' => 'superadmin', 'disetujui_pada' => $disetujuiPada] : []), ...$extra]);
    }

    private function periode(int $urutan, ?JadwalTahunan $jadwal = null): Periode
    {
        $nama = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'][$urutan];
        $periode = Periode::create(['nama' => 'Triwulan '.$nama, 'urutan' => $urutan, 'aktif' => true, 'is_nilai_akhir' => $urutan === 4]);
        PeriodeJadwal::create(['jadwal_id' => ($jadwal ?? $this->jadwal)->id, 'periode_id' => $periode->id,
            'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-15']);

        return $periode;
    }

    private function pengukuranBerikut(int $urutan = 2): PengukuranKinerja
    {
        $periode = $this->periode($urutan);

        return PengukuranKinerja::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $periode->id,
            'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
    }

    private function versi(PengukuranKinerja $p, int $nomor, RekomendasiPimpinan $r, string $isi): void
    {
        KinerjaSnapshot::create(['pengukuran_id' => $p->id, 'jadwal_snapshot_id' => $p->jadwal_snapshot_id, 'nomor' => $nomor,
            'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['rekomendasi_sebelumnya' => ['id' => $r->id], 'tindak_lanjut_rekomendasi' => $isi]]);
    }

    private function deny(string $permission, string $scope): void
    {
        $unitId = match ($scope) {
            'global' => null,
            'sama' => $this->unit->id,
            default => Unit::create(['nama' => 'Unit lain', 'created_by' => $this->actor->id])->id,
        };
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Pembatasan sintetis.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
