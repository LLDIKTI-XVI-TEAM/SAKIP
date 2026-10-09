<?php

namespace App\Actions\RencanaAksi;

use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\RencanaAksi;

class EvaluateRencanaAksiEvidence
{
    /**
     * Membaca pengaturan global penyimpanan berkas.
     *
     * @return array{unggahan_aktif: bool, ukuran_maks_kb: int, format_diizinkan: string}
     */
    public function settings(): array
    {
        $values = Pengaturan::where('grup', 'berkas')->pluck('nilai', 'kunci');

        return [
            'unggahan_aktif' => filter_var($values['berkas.unggahan_aktif'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'ukuran_maks_kb' => (int) ($values['berkas.ukuran_maks_kb'] ?? 10240),
            'format_diizinkan' => (string) ($values['berkas.format_diizinkan'] ?? 'pdf,docx,xlsx,jpg,jpeg,png'),
        ];
    }

    /**
     * Mengevaluasi pemenuhan bukti dukung untuk Rencana Aksi.
     * Menerapkan aturan mode, semua_mode_wajib, dan waiver anti-macet (tidak_dapat_dipenuhi).
     *
     * @return list<array<string, mixed>>
     */
    public function handle(RencanaAksi $rencanaAksi): array
    {
        $settings = $this->settings();
        $evidence = $rencanaAksi->buktiDukungs()->current()->get();

        return JenisBerkas::where('aktif', true)
            ->where('tahap', 'rencana_aksi')
            ->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $rencanaAksi->indikator_id))
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->map(function (JenisBerkas $requirement) use ($settings, $evidence) {
                $modes = array_values(array_filter(
                    ['file', 'tautan', 'teks'],
                    fn (string $mode) => (bool) $requirement->{'izinkan_'.$mode}
                ));

                $fulfilled = $evidence
                    ->where('jenis_berkas_id', $requirement->id)
                    ->pluck('mode')
                    ->unique()
                    ->intersect($modes)
                    ->values()
                    ->all();

                $waived = ! $settings['unggahan_aktif'] && in_array('file', $modes, true) && ! in_array('file', $fulfilled, true)
                    ? ['file']
                    : [];

                $available = array_values(array_diff($modes, $waived));
                $missing = array_values(array_diff($available, $fulfilled));

                $isComplete = false;
                if ($modes !== []) {
                    if (! $requirement->wajib) {
                        $isComplete = true;
                    } elseif ($requirement->semua_mode_wajib) {
                        $isComplete = ($missing === []);
                    } else {
                        $isComplete = ($available === [] || array_intersect($available, $fulfilled) !== []);
                    }
                }

                $isWaiverTotal = ($waived !== [] && $available === []);

                return [
                    'id' => $requirement->id,
                    'nama' => $requirement->nama,
                    'keterangan' => $requirement->keterangan,
                    'wajib' => (bool) $requirement->wajib,
                    'semua_mode_wajib' => (bool) $requirement->semua_mode_wajib,
                    'izinkan_file' => (bool) $requirement->izinkan_file,
                    'izinkan_tautan' => (bool) $requirement->izinkan_tautan,
                    'izinkan_teks' => (bool) $requirement->izinkan_teks,
                    'format_diizinkan' => $requirement->format_diizinkan ?: $settings['format_diizinkan'],
                    'ukuran_maks_kb' => $requirement->ukuran_maks_kb ?? $settings['ukuran_maks_kb'],
                    'pemenuhan' => [
                        'terpenuhi' => $isComplete,
                        'mode_terpenuhi' => $fulfilled,
                        'mode_kurang' => $missing,
                        'mode_dikecualikan' => $waived,
                        'tidak_dapat_dipenuhi' => $isWaiverTotal,
                        'alasan_pengecualian' => $waived !== []
                            ? 'Unggahan file dinonaktifkan pada setelan aplikasi.'
                            : null,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Menghitung ringkasan kelengkapan bukti dukung Rencana Aksi untuk gerbang pengajuan (ISS-05.03).
     *
     * @param  list<array<string, mixed>>|null  $persyaratan
     * @return array{lengkap: bool, total_wajib: int, terpenuhi_wajib: int}
     */
    public function summary(RencanaAksi $rencanaAksi, ?array $persyaratan = null): array
    {
        $evaluasi = $persyaratan ?? $this->handle($rencanaAksi);
        $wajibItems = array_filter($evaluasi, fn (array $item) => $item['wajib']);
        $totalWajib = count($wajibItems);
        $terpenuhiWajib = count(array_filter($wajibItems, fn (array $item) => $item['pemenuhan']['terpenuhi']));

        return [
            'lengkap' => $terpenuhiWajib === $totalWajib,
            'total_wajib' => $totalWajib,
            'terpenuhi_wajib' => $terpenuhiWajib,
        ];
    }
}
