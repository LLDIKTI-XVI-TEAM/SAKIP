<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\Jadwal\TambahIndikatorJadwal;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\PeriodeJadwal;
use App\Models\TargetKinerja;
use App\Services\Jadwal\JadwalSnapshotWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Versi snapshot hasil revisi PK menyalin versi pendahulu beserta komponennya dan hanya mengganti target,
 * walau data master sudah berubah sejak pendahulu terbit.
 */
class RevisiPkSnapshotWriterTest extends TestCase
{
    use CreatesDashboardFixture;
    use CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw4;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw4 = $this->tambahPeriode(4, '2026-12-01', '2026-12-15');
    }

    public function test_revisi_menyalin_pendahulu_dan_hanya_mengganti_target(): void
    {
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->pengukuran->indikator->sasaran_strategis_id, 'unit_id' => $this->unit->id,
            'kode' => 'IKU-REV', 'nama' => 'Nama awal', 'definisi_operasional' => 'Definisi awal', 'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2,
            'arah' => 'naik_baik', 'tipe_perhitungan' => 'penjumlahan', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan']);
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => '80', 'baseline' => '10']);
        foreach ([['A', '1', 1], ['B', '2.5', 2]] as [$kode, $bobot, $urutan]) {
            IndikatorKomponen::create(['indikator_id' => $indikator->id, 'kode' => $kode, 'label' => 'Komponen '.$kode, 'peran' => 'penjumlah',
                'bobot' => $bobot, 'urutan' => $urutan, 'aktif' => true, 'created_by' => $this->actor->id]);
        }
        app(TambahIndikatorJadwal::class)->handle($this->actor, $this->jadwal->id,
            ['indikator_id' => $indikator->id, 'periode_mulai_id' => $this->pengukuran->periode_id, 'alasan' => 'Fixture versi 1']);
        $pendahulu = JadwalSnapshot::where('indikator_id', $indikator->id)->sole();

        // Master berubah setelah versi 1 terbit; revisi PK tidak boleh membawanya.
        $indikator->update(['nama' => 'Nama master baru', 'definisi_operasional' => 'Definisi master baru', 'presisi' => 1, 'satuan' => 'poin']);
        IndikatorKomponen::where('indikator_id', $indikator->id)->where('kode', 'B')->update(['aktif' => false, 'label' => 'Label baru']);
        TargetKinerja::where('indikator_kinerja_id', $indikator->id)->update(['baseline' => '20']);

        $versiPkId = '0199a4a0-0000-7000-8000-000000000001';
        $baruId = DB::transaction(fn () => app(JadwalSnapshotWriter::class)->terbitkanRevisiTarget($this->actor, $pendahulu->fresh(), '85.5',
            $this->tw4->periode_id, 'Revisi PK akhir tahun', 'B-12/2026 tanggal 2026-11-01', ['pk:update' => 'allow'], $versiPkId, []));

        $lama = (array) DB::table('jadwal_snapshot')->where('id', $pendahulu->id)->first();
        $baru = (array) DB::table('jadwal_snapshot')->where('id', $baruId)->first();
        $kolomSalinan = ['jadwal_id', 'indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'dinilai_tahunan', 'baseline'];
        $this->assertSame(array_intersect_key($lama, array_flip($kolomSalinan)), array_intersect_key($baru, array_flip($kolomSalinan)));
        $this->assertSame('Nama awal', $baru['nama']);
        $this->assertSame('Definisi awal', $baru['definisi']);
        $this->assertSame('85.500000000000', $baru['target']);
        $this->assertSame(2, $baru['nomor_versi']);
        $this->assertSame($pendahulu->id, $baru['menggantikan_id']);
        $this->assertSame($this->tw4->periode_id, $baru['periode_mulai_id']);
        $this->assertSame('Revisi PK akhir tahun', $baru['alasan_koreksi']);
        $this->assertSame('B-12/2026 tanggal 2026-11-01', $baru['rujukan_koreksi']);
        $this->assertTrue($baru['komposisi_final']);

        $komponen = fn (string $id) => DB::table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $id)->orderBy('kode')
            ->get(['komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan'])->map(fn ($row) => (array) $row)->all();
        $this->assertCount(2, $komponen($baruId));
        $this->assertSame($komponen($pendahulu->id), $komponen($baruId));

        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi')->where('objek_id', $baruId)->sole();
        $this->assertSame($versiPkId, $audit->nilai_baru['renstra_pk_versi_id']);
        $this->assertSame([], $audit->nilai_baru['pengukuran_sebelum_periode_mulai']);
        $this->assertSame($pendahulu->id, $audit->nilai_baru['menggantikan_id']);
        $this->assertSame('85.5', $audit->nilai_baru['target']);
        $this->assertSame('Nama awal', $audit->nilai_baru['nama']);
        $this->assertSame('Revisi PK akhir tahun', $audit->alasan);
    }
}
