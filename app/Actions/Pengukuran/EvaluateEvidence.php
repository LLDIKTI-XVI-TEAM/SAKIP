<?php

namespace App\Actions\Pengukuran;

use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\PengukuranKinerja;

class EvaluateEvidence
{
    public function settings(): array
    {
        $values = Pengaturan::where('grup', 'berkas')->pluck('nilai', 'kunci');

        return ['unggahan_aktif' => filter_var($values['berkas.unggahan_aktif'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'ukuran_maks_kb' => (int) ($values['berkas.ukuran_maks_kb'] ?? 10240),
            'format_diizinkan' => $values['berkas.format_diizinkan'] ?? 'pdf,docx,xlsx,jpg,jpeg,png'];
    }

    /** Waiver hanya mode file; mode nonfile tetap wajib sesuai kontrak persyaratan. */
    public function handle(PengukuranKinerja $pengukuran): array
    {
        $settings = $this->settings();
        $evidence = $pengukuran->buktiDukungs()->get();

        return JenisBerkas::where('aktif', true)->where('tahap', 'pengukuran')->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $pengukuran->indikator_id))
            ->orderBy('urutan')->orderBy('id')->get()->map(function ($requirement) use ($settings, $evidence) {
                $modes = array_values(array_filter(['file', 'tautan', 'teks'], fn ($mode) => $requirement->{'izinkan_'.$mode}));
                $fulfilled = $evidence->where('jenis_berkas_id', $requirement->id)->pluck('mode')->unique()->intersect($modes)->values()->all();
                $waived = ! $settings['unggahan_aktif'] && in_array('file', $modes, true) && ! in_array('file', $fulfilled, true) ? ['file'] : [];
                $available = array_values(array_diff($modes, $waived));
                $missing = array_values(array_diff($available, $fulfilled));
                $complete = $requirement->semua_mode_wajib ? $missing === [] : ($available === [] || array_intersect($available, $fulfilled) !== []);

                return [...$requirement->only(['id', 'nama', 'wajib', 'semua_mode_wajib', 'izinkan_file', 'izinkan_tautan', 'izinkan_teks']),
                    'format_diizinkan' => $requirement->format_diizinkan ?: $settings['format_diizinkan'],
                    'ukuran_maks_kb' => $requirement->ukuran_maks_kb ?? $settings['ukuran_maks_kb'],
                    'pemenuhan' => ['terpenuhi' => $complete, 'mode_terpenuhi' => $fulfilled, 'mode_kurang' => $missing, 'mode_dikecualikan' => $waived,
                        'alasan_pengecualian' => $waived ? 'Unggahan file dinonaktifkan pada setelan aplikasi.' : null]];
            })->all();
    }
}
