<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Validation\ValidationException;

class SubmissionPrerequisites
{
    public function __construct(private EvaluateEvidence $evidence, private CalculatePengukuran $calculator) {}

    /**
     * Rekomendasi berasal dari pembacaan yang sama dengan tampilan/token dan versi beku.
     * Mode mutasi menerima RA yang dikunci sebelum indikator; target tetap berasal dari versi sah.
     */
    public function handle(PengukuranKinerja $pengukuran, ?array $rekomendasi, bool $lock = false, ?RencanaAksi $lockedPlan = null, ?PeriodeJadwal $lockedPeriod = null): array
    {
        $errors = [];
        $target = null;
        $withoutTarget = false;
        $version = null;
        $snapshot = $pengukuran->jadwalSnapshot;
        $snapshot?->jadwal->loadMissing('periode.periode');
        $period = $lock ? $lockedPeriod : $snapshot?->jadwal->periode->firstWhere('periode_id', $pengukuran->periode_id);
        if (! $snapshot || $snapshot->indikator_id !== $pengukuran->indikator_id || $snapshot->jadwal->tahun !== $pengukuran->tahun) {
            $errors[] = 'Konteks snapshot jadwal tidak cocok dengan indikator/tahun pengukuran.';
        }
        // Null dari lookup terkunci tetap berarti RA belum ada; jangan mengambil kunci RA baru setelah indikator.
        $ra = $lock ? $lockedPlan : RencanaAksi::where('indikator_id', $pengukuran->indikator_id)->where('tahun', $pengukuran->tahun)->first();
        if (! $ra || $ra->status_alur !== 'disahkan') {
            $errors[] = 'Rencana aksi indikator/tahun ini harus disahkan sebelum pengajuan.';
        } else {
            $version = RencanaAksiVersi::where('rencana_aksi_id', $ra->id)->orderByDesc('nomor')->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $version || ! $version->disahkan_at || ! $version->disahkan_by || $version->jadwal_snapshot_id !== $pengukuran->jadwal_snapshot_id
                || $ra->unit_id !== $pengukuran->targetUnitId()) {
                $errors[] = 'Versi rencana aksi sah harus cocok dengan snapshot dan unit pengukuran.';
            } else {
                $targets = $version->snapshot['target_periode'] ?? [];
                $periodTarget = collect($targets)->firstWhere('periode_id', $pengukuran->periode_id);
                if (! $periodTarget || ! array_key_exists('nilai', $periodTarget)) {
                    $errors[] = 'Target periode belum tersedia pada versi rencana aksi yang disahkan.';
                } else {
                    try {
                        $components = collect($periodTarget['komponen'] ?? []);
                        $values = $components->pluck('nilai', 'komponen_id')->all();
                        $definitions = $snapshot->komponen->toArray();
                        $calculated = $this->calculator->handle($snapshot->tipe_perhitungan, $snapshot->presisi, $definitions, $values,
                            $snapshot->tipe_perhitungan === 'manual' ? $periodTarget['nilai'] : null);
                        $sameValue = $calculated['nilai'] === null || $periodTarget['nilai'] === null
                            ? $calculated['nilai'] === $periodTarget['nilai']
                            : BigDecimal::of($calculated['nilai'])->isEqualTo((string) $periodTarget['nilai']);
                        // Status versi RA membedakan target kosong yang sah dari target yang belum dilengkapi.
                        $withoutTarget = ($periodTarget['status_perhitungan'] ?? null) === 'tidak_ditargetkan' && $periodTarget['nilai'] === null
                            && $calculated['status_perhitungan'] === 'belum_diisi'
                            && $components->count() === count($definitions) && count($values) === count($definitions)
                            && $components->every(fn ($component) => array_key_exists('nilai', $component) && $component['nilai'] === null)
                            && $period && PeriodeTanpaTarget::bolehTanpaTarget($snapshot, $period, $snapshot->jadwal->periode);
                        if (! $withoutTarget && ($calculated['status_perhitungan'] === 'belum_diisi' || $calculated['status_perhitungan'] !== ($periodTarget['status_perhitungan'] ?? null)
                            || ! $sameValue)) {
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
        // Nilai belum tersedia mengecualikan kelengkapan komponen; narasi dan gerbang lain tetap berlaku.
        if ($pengukuran->status_perhitungan === 'belum_dapat_diukur' && $pengukuran->periode->is_nilai_akhir) {
            $errors[] = 'Status Belum dapat diukur tidak tersedia pada periode nilai akhir.';
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
                $errors[] = ! $requirement['izinkan_file'] && ! $requirement['izinkan_tautan'] && ! $requirement['izinkan_teks']
                    ? 'Persyaratan bukti '.$requirement['nama'].' tidak memiliki mode aktif. Hubungi Tim Perencanaan.'
                    : 'Bukti wajib belum lengkap: '.$requirement['nama'].'.';
            }
        }
        $narrativeErrors = [];
        if (! $snapshot || ! PeriodeTanpaTarget::isPeriodeLampau($snapshot->jadwal, $period)) {
            foreach (['progres_kegiatan' => 'Progres kegiatan', 'kendala_masalah' => 'Kendala/masalah', 'strategi_tindak_lanjut' => 'Tindak lanjut/strategi penyelesaian'] as $field => $label) {
                if (trim((string) $pengukuran->{$field}) === '') {
                    $narrativeErrors[$field] = $label.' wajib diisi sebelum pengajuan.';
                }
            }
            if ($rekomendasi !== null && trim((string) $pengukuran->tindak_lanjut_rekomendasi) === '') {
                $narrativeErrors['tindak_lanjut_rekomendasi'] = 'Tindak lanjut rekomendasi wajib diisi sebelum pengajuan.';
            }
        }

        $allErrors = [...$errors, ...array_values($narrativeErrors)];

        return ['siap' => $allErrors === [], 'alasan' => $allErrors, 'errors' => [...($errors === [] ? [] : ['pengajuan' => $errors]), ...$narrativeErrors],
            'target' => $target, 'tanpa_target_periode' => (bool) $withoutTarget, 'rencana_aksi_versi' => $version, 'persyaratan_bukti' => $requirements];
    }
}
