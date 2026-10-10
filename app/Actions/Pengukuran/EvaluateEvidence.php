<?php

namespace App\Actions\Pengukuran;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\PengukuranKinerja;
use Illuminate\Support\Collection;

/**
 * Gerbang kelengkapan bukti dukung bersama (Plan 13.4): satu evaluator untuk
 * tahap pengukuran dan rencana aksi agar aturan mode, `semua_mode_wajib`,
 * dan waiver anti-macet tidak diduplikasi per induk.
 */
class EvaluateEvidence
{
    public function settings(): array
    {
        $values = Pengaturan::where('grup', 'berkas')->pluck('nilai', 'kunci');

        return ['unggahan_aktif' => filter_var($values['berkas.unggahan_aktif'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'ukuran_maks_kb' => (int) ($values['berkas.ukuran_maks_kb'] ?? 10240),
            'format_diizinkan' => $values['berkas.format_diizinkan'] ?? 'pdf,docx,xlsx,jpg,jpeg,png'];
    }

    public function handle(PengukuranKinerja $pengukuran): array
    {
        return $this->untuk('pengukuran', (string) $pengukuran->indikator_id, $pengukuran->buktiDukungs()->current()->get());
    }

    /**
     * Waiver hanya mode file pada persyaratan wajib (Plan 13.7); mode nonfile tetap wajib sesuai kontrak persyaratan.
     *
     * @param  'pengukuran'|'rencana_aksi'|'kegiatan'  $tahap
     * @param  Collection<int, BuktiDukung>  $evidence  Bukti berlaku milik induk (sudah difilter `current`).
     * @return list<array<string, mixed>>
     */
    public function untuk(string $tahap, string $indikatorId, Collection $evidence): array
    {
        $settings = $this->settings();

        return JenisBerkas::where('aktif', true)->where('tahap', $tahap)->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $indikatorId))
            ->orderBy('urutan')->orderBy('id')->get()->map(function ($requirement) use ($settings, $evidence) {
                $modes = array_values(array_filter(['file', 'tautan', 'teks'], fn ($mode) => $requirement->{'izinkan_'.$mode}));
                $fulfilled = $evidence->where('jenis_berkas_id', $requirement->id)->pluck('mode')->unique()->intersect($modes)->values()->all();
                $waived = $requirement->wajib && ! $settings['unggahan_aktif'] && in_array('file', $modes, true) && ! in_array('file', $fulfilled, true) ? ['file'] : [];
                $available = array_values(array_diff($modes, $waived));
                $missing = array_values(array_diff($available, $fulfilled));
                // Konfigurasi tanpa mode bukan waiver file-only yang memang diizinkan.
                $complete = $modes !== [] && ($requirement->semua_mode_wajib ? $missing === [] : ($available === [] || array_intersect($available, $fulfilled) !== []));

                return [...$requirement->only(['id', 'nama', 'keterangan', 'wajib', 'semua_mode_wajib', 'izinkan_file', 'izinkan_tautan', 'izinkan_teks']),
                    'format_diizinkan' => $requirement->format_diizinkan ?: $settings['format_diizinkan'],
                    'ukuran_maks_kb' => $requirement->ukuran_maks_kb ?? $settings['ukuran_maks_kb'],
                    'pemenuhan' => ['terpenuhi' => $complete, 'mode_terpenuhi' => $fulfilled, 'mode_kurang' => $missing, 'mode_dikecualikan' => $waived,
                        // Penanda anti-macet §18.7: seluruh mode yang diizinkan ikut dikecualikan.
                        'tidak_dapat_dipenuhi' => $waived !== [] && $available === [],
                        'alasan_pengecualian' => $waived ? 'Unggahan file dinonaktifkan pada setelan aplikasi.' : null]];
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
