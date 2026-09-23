<?php

namespace App\Services\Storage;

use App\Models\Kegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Regulasi;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use Illuminate\Support\Facades\DB;

class StorageMetricsService
{
    /**
     * Label resmi untuk keenam induk bukti dukung sesuai PRD SAKIP.
     */
    public const INDUK_LABELS = [
        'rencana_aksi' => 'Rencana Aksi',
        'pengukuran' => 'Pengukuran Kinerja',
        'kegiatan' => 'Kegiatan',
        'renstra' => 'Rencana Strategis (Renstra)',
        'renstra_pk' => 'Perjanjian Kinerja (PK)',
        'regulasi' => 'Dasar Aturan (Regulasi)',
    ];

    /**
     * Hitung ringkasan dan breakdown penggunaan storage lintas seluruh bukti dukung aktif.
     *
     * @return array{
     *     file_count: int,
     *     file_total_bytes: int,
     *     link_count: int,
     *     text_count: int,
     *     total_evidence_count: int,
     *     by_induk: array<string, array{
     *         induk: string,
     *         label: string,
     *         file_count: int,
     *         file_bytes: int,
     *         link_count: int,
     *         text_count: int,
     *         total_count: int
     *     }>
     * }
     */
    public function calculate(): array
    {
        // 1. Agregasi global lintas mode (hanya bukti aktif: dihapus_pada IS NULL)
        $summary = DB::table('berkas')
            ->whereNull('dihapus_pada')
            ->selectRaw("
                COUNT(CASE WHEN mode = 'file' THEN 1 END) as file_count,
                COALESCE(SUM(CASE WHEN mode = 'file' THEN ukuran_bytes ELSE 0 END), 0) as file_total_bytes,
                COUNT(CASE WHEN mode = 'tautan' THEN 1 END) as link_count,
                COUNT(CASE WHEN mode = 'teks' THEN 1 END) as text_count,
                COUNT(*) as total_evidence_count
            ")
            ->first();

        $fileCount = (int) ($summary->file_count ?? 0);
        $fileTotalBytes = (int) ($summary->file_total_bytes ?? 0);
        $linkCount = (int) ($summary->link_count ?? 0);
        $textCount = (int) ($summary->text_count ?? 0);
        $totalCount = (int) ($summary->total_evidence_count ?? 0);

        // 2. Breakdown per berkasable_type (6 induk resmi)
        $rows = DB::table('berkas')
            ->whereNull('dihapus_pada')
            ->groupBy('berkasable_type')
            ->selectRaw("
                berkasable_type,
                COUNT(CASE WHEN mode = 'file' THEN 1 END) as file_count,
                COALESCE(SUM(CASE WHEN mode = 'file' THEN ukuran_bytes ELSE 0 END), 0) as file_bytes,
                COUNT(CASE WHEN mode = 'tautan' THEN 1 END) as link_count,
                COUNT(CASE WHEN mode = 'teks' THEN 1 END) as text_count,
                COUNT(*) as total_count
            ")
            ->get();

        $byInduk = [];
        foreach (self::INDUK_LABELS as $type => $label) {
            $byInduk[$type] = [
                'induk' => $type,
                'label' => $label,
                'file_count' => 0,
                'file_bytes' => 0,
                'link_count' => 0,
                'text_count' => 0,
                'total_count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $canonicalType = match ($row->berkasable_type) {
                'regulasi', Regulasi::class => 'regulasi',
                'pengukuran', PengukuranKinerja::class, 'App\Models\Pengukuran' => 'pengukuran',
                'rencana_aksi', RencanaAksi::class => 'rencana_aksi',
                'kegiatan', Kegiatan::class => 'kegiatan',
                'renstra', Renstra::class => 'renstra',
                'renstra_pk', RenstraPk::class, 'App\Models\PerjanjianKinerja' => 'renstra_pk',
                default => $row->berkasable_type,
            };

            if (isset($byInduk[$canonicalType])) {
                $byInduk[$canonicalType]['file_count'] += (int) $row->file_count;
                $byInduk[$canonicalType]['file_bytes'] += (int) $row->file_bytes;
                $byInduk[$canonicalType]['link_count'] += (int) $row->link_count;
                $byInduk[$canonicalType]['text_count'] += (int) $row->text_count;
                $byInduk[$canonicalType]['total_count'] += (int) $row->total_count;
            }
        }

        return [
            'file_count' => $fileCount,
            'file_total_bytes' => $fileTotalBytes,
            'link_count' => $linkCount,
            'text_count' => $textCount,
            'total_evidence_count' => $totalCount,
            'by_induk' => $byInduk,
        ];
    }
}
