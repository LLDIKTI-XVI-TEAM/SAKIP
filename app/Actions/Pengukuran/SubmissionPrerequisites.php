<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Validation\ValidationException;

class SubmissionPrerequisites
{
    public function __construct(private EvaluateEvidence $evidence, private CalculatePengukuran $calculator) {}

    /** RA versi sah dan target periode diambil dari snapshot versi, bukan draft RA hidup. */
    public function handle(PengukuranKinerja $pengukuran, bool $lock = false): array
    {
        $errors = [];
        $target = null;
        $version = null;
        $snapshot = $pengukuran->jadwalSnapshot;
        if (! $snapshot || $snapshot->indikator_id !== $pengukuran->indikator_id || $snapshot->jadwal->tahun !== $pengukuran->tahun) {
            $errors[] = 'Konteks snapshot jadwal tidak cocok dengan indikator/tahun pengukuran.';
        }
        $ra = RencanaAksi::where('indikator_id', $pengukuran->indikator_id)->where('tahun', $pengukuran->tahun)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $ra || $ra->status_alur !== 'disahkan') {
            $errors[] = 'Rencana aksi indikator/tahun ini harus disahkan sebelum pengajuan.';
        } else {
            $version = RencanaAksiVersi::where('rencana_aksi_id', $ra->id)->orderByDesc('nomor')->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $version || ! $version->disahkan_at || ! $version->disahkan_by || $version->jadwal_snapshot_id !== $pengukuran->jadwal_snapshot_id
                || $ra->jadwal_snapshot_id !== $pengukuran->jadwal_snapshot_id || $ra->unit_id !== $pengukuran->targetUnitId()) {
                $errors[] = 'Versi rencana aksi sah harus cocok dengan snapshot dan unit pengukuran.';
            } else {
                $targets = $version->snapshot['target_periode'] ?? [];
                $periodTarget = collect($targets)->firstWhere('periode_id', $pengukuran->periode_id);
                if (! $periodTarget || ! array_key_exists('nilai', $periodTarget)) {
                    $errors[] = 'Target periode belum tersedia pada versi rencana aksi yang disahkan.';
                } else {
                    try {
                        $values = collect($periodTarget['komponen'] ?? [])->pluck('nilai', 'komponen_id')->all();
                        $calculated = $this->calculator->handle($snapshot->tipe_perhitungan, $snapshot->presisi, $snapshot->komponen->toArray(), $values,
                            $snapshot->tipe_perhitungan === 'manual' ? $periodTarget['nilai'] : null);
                        $sameValue = $calculated['nilai'] === null || $periodTarget['nilai'] === null
                            ? $calculated['nilai'] === $periodTarget['nilai']
                            : BigDecimal::of($calculated['nilai'])->isEqualTo((string) $periodTarget['nilai']);
                        if ($calculated['status_perhitungan'] === 'belum_diisi' || $calculated['status_perhitungan'] !== ($periodTarget['status_perhitungan'] ?? null)
                            || ! $sameValue) {
                            $errors[] = 'Target pada versi RA belum lengkap atau tidak konsisten dengan komponen snapshot.';
                        } else {
                            $target = $calculated['nilai'];
                        }
                    } catch (\InvalidArgumentException|ValidationException|MathException $exception) {
                        $errors[] = 'Target versi RA tidak dapat divalidasi: '.$exception->getMessage();
                    }
                }
            }
        }
        if ($pengukuran->status_perhitungan === 'belum_diisi') {
            $errors[] = 'Nilai manual atau seluruh komponen snapshot wajib diisi.';
        }
        if ($pengukuran->status_perhitungan === 'tidak_dapat_dihitung' && trim((string) $pengukuran->alasan_tidak_dapat_dihitung) === '') {
            $errors[] = 'Alasan penyebut nol wajib diisi.';
        }
        if ($pengukuran->sumber_nilai === 'historis') {
            $errors[] = 'Nilai historis memerlukan alur backfill resmi, bukan pengajuan normal.';
        }
        $requirements = $this->evidence->handle($pengukuran);
        foreach ($requirements as $requirement) {
            if ($requirement['wajib'] && ! $requirement['pemenuhan']['terpenuhi']) {
                $errors[] = 'Bukti wajib belum lengkap: '.$requirement['nama'].'.';
            }
        }
        if ($pengukuran->indikator->wajib_catatan && trim((string) $pengukuran->catatan) === '') {
            $errors[] = 'Catatan diwajibkan pada indikator ini.';
        }
        // Pembanding terakhir yang disahkan secara kronologis; null bukan angka nol.
        $previous = PengukuranKinerja::where('indikator_id', $pengukuran->indikator_id)->where('pengukuran_kinerjas.id', '!=', $pengukuran->id)->whereHas('ratifiedVersion')
            ->where(fn ($q) => $q->where('tahun', '<', $pengukuran->tahun)->orWhere(fn ($q) => $q->where('tahun', $pengukuran->tahun)->whereHas('periode', fn ($p) => $p->where('urutan', '<', $pengukuran->periode->urutan))))
            ->join('periode', 'periode.id', '=', 'pengukuran_kinerjas.periode_id')->orderByDesc('tahun')->orderByDesc('periode.urutan')->select('pengukuran_kinerjas.*')->first();
        $previousValue = $previous?->ratifiedVersion?->snapshot['nilai'] ?? null;
        if ($previousValue !== null && $pengukuran->nilai !== null && $snapshot) {
            $value = BigDecimal::of($pengukuran->nilai);
            $worse = $snapshot->arah === 'naik_baik' ? $value->isLessThan((string) $previousValue) : $value->isGreaterThan((string) $previousValue);
            if ($worse && trim((string) $pengukuran->catatan) === '') {
                $errors[] = 'Nilai memburuk dibanding periode sah sebelumnya; catatan wajib diisi.';
            }
        }

        return ['siap' => $errors === [], 'alasan' => $errors, 'target' => $target, 'rencana_aksi_versi' => $version, 'persyaratan_bukti' => $requirements];
    }
}
