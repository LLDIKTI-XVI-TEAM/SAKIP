<?php

namespace App\Actions\Pengukuran;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\PengukuranKinerja;
use App\Services\Storage\UploadLimits;
use Illuminate\Support\Collection;

/**
 * Gerbang kelengkapan bukti dukung bersama (Plan 13.4): satu evaluator untuk
 * tahap pengukuran dan rencana aksi agar aturan mode dan `semua_mode_wajib`
 * tidak diduplikasi per induk.
 */
class EvaluateEvidence
{
    public function settings(): array
    {
        return UploadLimits::resolve();
    }

    public function handle(PengukuranKinerja $pengukuran): array
    {
        return $this->untuk('pengukuran', (string) $pengukuran->indikator_id, $pengukuran->buktiDukungs()->current()->get());
    }

    /**
     * Evaluasi baru selalu memeriksa seluruh mode; pengecualian unggahan lama hanya dibaca dari histori.
     *
     * @param  'pengukuran'|'rencana_aksi'|'kegiatan'|'kegiatan_perencanaan'  $tahap
     * @param  Collection<int, BuktiDukung>  $evidence  Bukti berlaku milik induk (sudah difilter `current`).
     * @return list<array<string, mixed>>
     */
    public function untuk(string $tahap, string $indikatorId, Collection $evidence): array
    {
        return JenisBerkas::where('aktif', true)->where('tahap', $tahap)->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $indikatorId))
            ->orderBy('urutan')->orderBy('id')->get()->map(function ($requirement) use ($evidence) {
                $modes = array_values(array_filter(['file', 'tautan', 'teks'], fn ($mode) => $requirement->{'izinkan_'.$mode}));
                $fulfilled = $evidence->where('jenis_berkas_id', $requirement->id)->pluck('mode')->unique()->intersect($modes)->values()->all();
                $missing = array_values(array_diff($modes, $fulfilled));
                $complete = $modes !== [] && ($requirement->semua_mode_wajib ? $missing === [] : $fulfilled !== []);

                return [...$requirement->only(['id', 'nama', 'keterangan', 'wajib', 'semua_mode_wajib', 'izinkan_file', 'izinkan_tautan', 'izinkan_teks']),
                    ...UploadLimits::resolve($requirement->ukuran_maks_kb, $requirement->format_diizinkan),
                    'pemenuhan' => ['terpenuhi' => $complete, 'mode_terpenuhi' => $fulfilled, 'mode_kurang' => $missing,
                        'mode_dikecualikan' => [], 'tidak_dapat_dipenuhi' => false, 'alasan_pengecualian' => null]];
            })->all();
    }

    /**
     * Ringkasan untuk gerbang pengajuan: hanya persyaratan wajib yang dihitung.
     *
     * @param  list<array<string, mixed>>  $evaluasi
     * @return array{lengkap: bool, total_wajib: int, terpenuhi_wajib: int}
     */
    public function ringkasan(array $evaluasi): array
    {
        $wajib = array_filter($evaluasi, fn (array $item) => $item['wajib']);
        $terpenuhi = array_filter($wajib, fn (array $item) => $item['pemenuhan']['terpenuhi']);

        return ['lengkap' => count($terpenuhi) === count($wajib), 'total_wajib' => count($wajib), 'terpenuhi_wajib' => count($terpenuhi)];
    }
}
