<?php

namespace Tests\Concerns;

use App\Actions\Jadwal\TambahIndikatorJadwal;
use App\Actions\PerjanjianKinerja\SetujuiRevisiPerjanjianKinerja;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use App\Models\TargetKinerja;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Jadwal aktif TW I–IV dengan PK terkunci, pengguna Perencanaan, dan dua indikator yang dibekukan lewat jalur produksi:
 * IKU-1 berkomponen (target 80) dan IKU-2 manual (target 60). Indikator bawaan fixture pengukuran diarsipkan.
 */
trait CreatesRevisiPkFixture
{
    use CreatesDashboardFixture;
    use CreatesPengukuranFixture { setUp as fixtureSetUp; }

    private const ALASAN = 'Revisi PK sesuai surat persetujuan pimpinan.';

    private PeriodeJadwal $tw1;

    private PeriodeJadwal $tw2;

    private PeriodeJadwal $tw3;

    private PeriodeJadwal $tw4;

    private RenstraPk $pk;

    private User $perencanaan;

    private JadwalSnapshot $iku1;

    private JadwalSnapshot $iku2;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        Storage::fake('local');
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->sole();
        $this->tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->tw3 = $this->tambahPeriode(3, '2026-09-01', '2026-09-15');
        $this->tw4 = $this->tambahPeriode(4, '2026-12-01', '2026-12-15');
        $this->pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        $this->perencanaan = $this->userWithRole('perencanaan');
        // Header TW I indikator bawaan masih draft; setelah diarsipkan header itu tidak boleh menarik periode mulai.
        IndikatorKinerja::whereKey($this->context->indikator_id)->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->iku1 = $this->terjadwal('IKU-1', '80', ['tipe_perhitungan' => 'penjumlahan'], [['A', '1', 1], ['B', '2.5', 2]]);
        $this->iku2 = $this->terjadwal('IKU-2', '60');
    }

    /**
     * Indikator resmi yang dibekukan lewat jalur produksi penambahan indikator, sehingga versi 1 sama dengan master.
     *
     * @param  list<array{0: string, 1: string, 2: int}>  $komponen  kode, bobot, urutan
     */
    private function terjadwal(string $kode, string $target, array $atribut = [], array $komponen = [], ?PeriodeJadwal $mulai = null): JadwalSnapshot
    {
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->pengukuran->indikator->sasaran_strategis_id, 'unit_id' => $this->unit->id,
            'kode' => $kode, 'nama' => 'Indikator '.$kode, 'definisi_operasional' => 'Definisi '.$kode, 'satuan' => 'persen',
            'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'status' => 'aktif',
            'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan', ...$atribut]);
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => $target, 'baseline' => '10']);
        foreach ($komponen as [$kodeKomponen, $bobot, $urutan]) {
            IndikatorKomponen::create(['indikator_id' => $indikator->id, 'kode' => $kodeKomponen, 'label' => 'Komponen '.$kodeKomponen, 'peran' => 'penjumlah',
                'bobot' => $bobot, 'urutan' => $urutan, 'aktif' => true, 'created_by' => $this->actor->id]);
        }
        app(TambahIndikatorJadwal::class)->handle($this->actor, $this->jadwal->id,
            ['indikator_id' => $indikator->id, 'periode_mulai_id' => ($mulai ?? $this->tw1)->periode_id, 'alasan' => 'Fixture versi 1']);

        return JadwalSnapshot::where('indikator_id', $indikator->id)->sole();
    }

    /** Versi koreksi snapshot yang terbit langsung di DB dengan target dan periode mulai tertentu. */
    private function koreksiSnapshot(JadwalSnapshot $pendahulu, string $target, PeriodeJadwal $mulai, array $tambahan = []): JadwalSnapshot
    {
        return JadwalSnapshot::create([...$pendahulu->only(['jadwal_id', 'indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'dinilai_tahunan']),
            'nomor_versi' => $pendahulu->nomor_versi + 1, 'menggantikan_id' => $pendahulu->id, 'alasan_koreksi' => 'Koreksi resmi target PK.', 'rujukan_koreksi' => 'SK-UJI-002',
            'komposisi_final' => true, 'periode_mulai_id' => $mulai->periode_id, 'target' => $target, ...$tambahan]);
    }

    /** Header pengukuran indikator × periode; `disahkan` memakai versi pengajuan sah, status lain diatur langsung. */
    private function header(JadwalSnapshot $snapshot, PeriodeJadwal $periode, string $status = 'disahkan'): PengukuranKinerja
    {
        $header = $this->headerUntuk($snapshot, $periode->periode_id);
        if ($status === 'disahkan') {
            $this->sahkan($header, []);
        } else {
            $header->forceFill(['status_alur' => $status])->save();
        }

        return $header;
    }

    private function sahkanSampai(int $urutan, JadwalSnapshot ...$snapshots): void
    {
        foreach (array_slice([$this->tw1, $this->tw2, $this->tw3, $this->tw4], 0, $urutan) as $periode) {
            foreach ($snapshots as $snapshot) {
                $this->header($snapshot, $periode);
            }
        }
    }

    /** @return array{indikator_id: string, nilai: string} */
    private function entri(JadwalSnapshot $snapshot, string $nilai): array
    {
        return ['indikator_id' => $snapshot->indikator_id, 'nilai' => $nilai];
    }

    /** Payload revisi dengan token terkini; `target` hanya dikirim bila ada entri. @return array<string, mixed> */
    private function payload(array $target = [], array $override = []): array
    {
        return [...['expected_nomor_versi' => (int) RenstraPkVersi::where('renstra_pk_id', $this->pk->id)->max('nomor_versi'),
            'expected_dasar' => JadwalTahunan::tokenDasar($this->jadwal->snapshotDasar()),
            'nomor_surat' => 'B-12/2026', 'tanggal_surat' => '2026-11-01', 'alasan' => self::ALASAN,
            'lampiran' => [['file' => UploadedFile::fake()->create('pk-akhir.pdf', 20, 'application/pdf'), 'keterangan' => 'PK akhir bertanda tangan']]],
            ...($target === [] ? [] : ['target' => $target]), ...$override];
    }

    private function revisi(array $target = [], array $override = [], ?User $aktor = null): RenstraPkVersi
    {
        return app(SetujuiRevisiPerjanjianKinerja::class)->handle($aktor ?? $this->perencanaan, $this->pk->id, $this->payload($target, $override));
    }
}
