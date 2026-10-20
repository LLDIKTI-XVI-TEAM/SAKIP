<?php

namespace App\Actions\Dashboard;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Actions\Pengukuran\PresentPengukuran;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
use App\Support\StatusTampilanPengukuran as Status;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class StatusIndikatorDashboard
{
    private const ALUR = ['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'];

    private const STATUS = [Status::BELUM_DIBUKA, Status::TIDAK_BERLAKU, ...Status::DIHITUNG];

    public function __construct(
        private DaftarKewajibanPengukuran $kewajiban,
        private PresentPengukuran $present,
        private CalculatePengukuran $calculator,
        private PermissionResolver $permissions,
    ) {}

    /**
     * Status indikator × periode dari daftar kewajiban periode yang sudah dibuka. Angka resmi (realisasi, target, target PK,
     * selisih) hanya dari isi versi disahkan; baris lain hanya membawa status dan target dari RA disahkan.
     *
     * @param  array<string, mixed>  $konteks  hasil ResolveDashboardKonteks
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $konteks, string $today): array
    {
        $jadwal = $konteks['jadwal'];
        /** @var Collection<int, PeriodeJadwal> $dibuka */
        $dibuka = $konteks['periodeDibuka']->keyBy('id');
        $rows = $jadwal === null || $dibuka->isEmpty() ? collect() : $this->kewajiban->query($dibuka->keys()->all(), $konteks['unitDitolak'], $today)
            ->when($konteks['filter']['sasaran_id'], fn ($q, $id) => $q->where('i.sasaran_strategis_id', $id))
            ->when($konteks['filter']['unit_id'], fn ($q, $id) => $q->where('cs.unit_id', $id))->get();
        $headers = PengukuranKinerja::with(['ratifiedVersion', 'latestVersion'])->whereIn('id', $rows->pluck('pengukuran_id')->filter())->get()->keyBy('id');
        if ($headers->isNotEmpty()) {
            // Penanda F2 dan reviu terlambat tetap tampil di dashboard (Workflow F2); dibaca sekaligus dari audit.
            $this->present->prepareSummary($headers);
        }
        $resmi = fn ($row): ?array => ($header = $headers->get($row->pengukuran_id ?? '')) && $header->status_alur === 'disahkan' ? $header->ratifiedVersion?->snapshot : null;
        $bekuDari = fn ($row): ?array => ($header = $headers->get($row->pengukuran_id ?? '')) && in_array($header->status_alur, PresentPengukuran::STATUS_BEKU, true)
            ? ($resmi($row) ?? $header->latestVersion?->snapshot) : null;
        $targetRows = $rows->filter(fn ($row) => $row->berlaku && $bekuDari($row) === null)->values();
        $targets = $targetRows->map(fn ($row) => $row->jadwal_snapshot_id.':'.$row->periode_id)->combine($this->present->periodTargets($targetRows->map(fn ($row) => [
            'indikator_id' => $row->indikator_id, 'tahun' => (int) $row->tahun, 'periode_id' => $row->periode_id, 'jadwal_snapshot_id' => $row->jadwal_snapshot_id, 'unit_id' => $row->unit_id,
        ])->all()));
        $canRead = $this->permissions->allows($actor, 'pengukuran:read');
        $readDenies = $this->permissions->unitDitolak($actor, 'pengukuran:read')->pluck('unit_id')->all();

        $batas = $konteks['batas'];
        $cells = $rows->map(function ($row) use ($jadwal, $dibuka, $headers, $resmi, $bekuDari, $targets, $batas, $canRead, $readDenies): array {
            $periode = $dibuka[$row->periode_jadwal_id];
            $header = $headers->get($row->pengukuran_id ?? '');
            $versi = $resmi($row);
            // Jendela dinilai terhadap batas jadwal (tanggal penutupan bila sudah ditutup), bukan hari ini.
            $status = Status::tentukan(true, (bool) $row->berlaku, $header?->status_alur, $batas > $periode->pengisian_selesai->toDateString(),
                PeriodeTanpaTarget::isPeriodeLampau($jadwal, $periode), $versi);
            // Pengajuan yang sedang direviu memakai PIC dan target beku saat diajukan, sama dengan halaman Pengukuran.
            $beku = $bekuDari($row);
            $target = $beku ?? $targets->get($row->jadwal_snapshot_id.':'.$row->periode_id, ['target' => null, 'tanpa_target_periode' => false]);
            $indikator = $versi['indikator'] ?? [];
            $picNama = $beku === null ? $row->pic_nama : ($beku['pic']['nama'] ?? null);

            return [
                'periode_jadwal_id' => $row->periode_jadwal_id, 'periode_id' => $row->periode_id, 'alur' => $header?->status_alur,
                'baris' => [
                    'indikator' => ['id' => $row->indikator_id, 'kode' => $indikator['kode'] ?? $row->kode, 'nama' => $indikator['nama'] ?? $row->nama,
                        'satuan' => $indikator['satuan'] ?? $row->satuan, 'desimal_tampilan' => (int) ($indikator['desimal_tampilan'] ?? $row->desimal_tampilan),
                        'arah' => $indikator['arah'] ?? $row->arah],
                    'unit' => ['nama' => $versi['unit_kerja']['nama'] ?? $row->unit_nama],
                    'pic' => $picNama ? ['nama' => $picNama] : null,
                    'status' => $status, 'status_alur' => $header?->status_alur,
                    ...($header ? $this->present->penanda($header) : ['self_approval' => false, 'reviu_terlambat' => false]),
                    'realisasi' => self::angka($versi['nilai'] ?? null), 'status_perhitungan' => $versi['status_perhitungan'] ?? null,
                    'target' => self::angka($target['target'] ?? null), 'tanpa_target_periode' => (bool) ($target['tanpa_target_periode'] ?? false),
                    'target_pk' => self::angka($versi === null ? $row->target_pk : ($versi['target_pk'] ?? null)),
                    'selisih' => $versi === null ? null : self::angka($this->calculator->differenceFromPk($versi['nilai'] ?? null, $versi['target_pk'] ?? null)),
                    'action' => $header && $canRead && ! in_array($row->unit_id, $readDenies, true)
                        ? ['href' => route('pengukuran.edit', $header->id), 'label' => 'Lihat pengukuran'] : null,
                ],
            ];
        });
        $terpilih = $konteks['periodeJadwal']?->id;
        $periodeIni = $cells->where('periode_jadwal_id', $terpilih);

        return [
            'stats' => $this->tally($periodeIni),
            'progresPeriode' => $konteks['semuaPeriode']->map(fn (PeriodeJadwal $p) => ['periode_id' => $p->periode_id, 'nama' => $p->periode->nama,
                'dibuka' => $dibuka->has($p->id), ...$this->tally($cells->where('periode_jadwal_id', $p->id))])->values()->all(),
            'ringkasanStatus' => array_merge(array_fill_keys(self::STATUS, 0), $periodeIni->countBy('baris.status')->all()),
            'statusIndikator' => $periodeIni->pluck('baris')->values()->all(),
            'grafik' => $this->grafik($cells, $konteks['periodeDibuka']),
        ];
    }

    /** @return array<string, int> hitungan status alur kewajiban; baris tanpa header dihitung "belum mengisi" */
    private function tally(Collection $cells): array
    {
        $dihitung = $cells->filter(fn (array $cell) => in_array($cell['baris']['status'], Status::DIHITUNG, true));
        $alur = $dihitung->countBy(fn (array $cell) => $cell['alur'] ?? 'belum_mengisi');

        return ['total' => $dihitung->count(), 'belum_mengisi' => $alur->get('belum_mengisi', 0),
            ...collect(self::ALUR)->mapWithKeys(fn ($s) => [$s => $alur->get($s, 0)])->all()];
    }

    /**
     * Seri per indikator lintas periode dibuka. Koreksi snapshot dapat mengubah satuan/presisi di tengah tahun, jadi kunci
     * seri memuat keduanya agar nilai beda satuan tidak digambar pada satu sumbu.
     */
    private function grafik(Collection $cells, Collection $periodeDibuka): array
    {
        $urutan = $periodeDibuka->pluck('id')->flip();
        $nama = $periodeDibuka->mapWithKeys(fn (PeriodeJadwal $p) => [$p->id => $p->periode->nama]);
        $kelompok = $cells->sortBy(fn (array $cell) => $urutan[$cell['periode_jadwal_id']])
            ->groupBy(fn (array $cell) => implode(':', Arr::only($cell['baris']['indikator'], ['id', 'satuan', 'desimal_tampilan'])));

        return [
            'indikator' => $kelompok->map(fn (Collection $items, string $kunci) => ['id' => $kunci,
                ...Arr::only($items->first()['baris']['indikator'], ['kode', 'nama', 'satuan', 'desimal_tampilan'])])->sortBy('kode')->values()->all(),
            'seri' => $kelompok->map(fn (Collection $items) => $items->map(fn (array $cell) => [
                'periode_id' => $cell['periode_id'], 'nama' => $nama[$cell['periode_jadwal_id']],
                'target' => $cell['baris']['target'], 'tanpa_target' => $cell['baris']['tanpa_target_periode'],
                'realisasi' => $cell['baris']['realisasi'], 'status' => $cell['baris']['status'],
            ])->values()->all())->all(),
        ];
    }

    /** Angka dikirim sebagai string desimal apa adanya agar presisi tidak hilang di jalan. */
    private static function angka(mixed $nilai): ?string
    {
        return $nilai === null ? null : (string) $nilai;
    }
}
