<?php

namespace App\Actions\Laporan;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Laporan\PembacaRekapIndikatorPeriode;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class EksporRekapIndikatorPeriode
{
    private const TIDAK_TERCATAT = 'Tidak tercatat pada versi ini';

    private const STATUS = [
        'belum_disahkan' => 'Belum disahkan', 'belum_dapat_diukur' => 'Belum dapat diukur',
        'tanpa_target_periode' => 'Tanpa target periode', 'tidak_dapat_dihitung' => 'Tidak dapat dihitung',
        'disahkan' => 'Disahkan', 'konteks_tidak_lengkap' => 'Konteks versi tidak lengkap',
    ];

    private const KOLOM_KEGIATAN = ['Kunci Rekap', 'Kode Indikator', 'Periode', 'Unit', 'Kegiatan', 'Status Kegiatan',
        'Anggaran (Rp)', 'Kegiatan Bersama', 'Sumber Klaim', 'Uraian Pelaksanaan', 'Kendala Kegiatan', 'Strategi Tindak Lanjut Kegiatan'];

    public function __construct(private readonly PembacaRekapIndikatorPeriode $pembaca, private readonly AuditLogger $audit) {}

    /**
     * Berkas selesai dibentuk di memori sebelum audit; kegagalan tidak mengirim unduhan parsial.
     *
     * @return array{nama: string, isi: string}
     */
    public function handle(User $actor, array $filter): array
    {
        $book = new Spreadsheet;
        $rekap = $book->getActiveSheet()->setTitle('Rekap');
        $kegiatan = $book->createSheet()->setTitle('Kegiatan');
        $kegiatan->fromArray(self::KOLOM_KEGIATAN, null, 'A8');
        $nomorRekap = $nomorKegiatan = 9;
        $stream = null;
        try {
            $meta = $this->pembaca->baca($actor, $filter, function (array $baris) use ($rekap, $kegiatan, &$nomorRekap, &$nomorKegiatan): void {
                foreach ($baris['komponen'] ?: [null] as $komponen) {
                    $this->tulisBaris($rekap, $nomorRekap++, $this->rekap($baris, $komponen));
                }
                foreach ($baris['kegiatan'] ?? [] as $item) {
                    $this->tulisBaris($kegiatan, $nomorKegiatan++, $this->kegiatan($baris, $item));
                }
            });
            foreach ([$rekap, $kegiatan] as $sheet) {
                $this->tataSheet($sheet, $meta);
            }
            // php://memory tidak menumpahkan berkas privat ke direktori sementara bersama.
            $stream = fopen('php://memory', 'w+b');
            if ($stream === false) {
                throw new RuntimeException('Memori pembentuk laporan tidak tersedia.');
            }
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save($stream);
            rewind($stream);
            $isi = stream_get_contents($stream);
            if ($isi === false) {
                throw new RuntimeException('Berkas laporan tidak dapat dibaca.');
            }
            // Transaksi READ ONLY sudah selesai; audit menandai pembentukan, bukan penerimaan browser.
            $this->audit->catat($actor, 'laporan.ekspor', 'jadwal_tahunan', $meta['jadwal']['id'], nilaiBaru: [
                'jenis' => 'rekap', 'filter' => $meta['filters'],
            ]);

            return ['nama' => 'rekap-kinerja-'.$meta['jadwal']['tahun'].'-'.now(config('app.business_timezone'))->format('Ymd-His').'.xlsx', 'isi' => $isi];
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $book->disconnectWorksheets();
        }
    }

    /** Nilai indikator diulang; target komponen tetap berasal dari pasangan RA resmi. */
    private function rekap(array $r, ?array $c): array
    {
        $belumSah = $r['versi'] === null;
        $status = self::STATUS[$r['status_laporan']];
        $nilaiKosong = $r['status_laporan'] === 'disahkan' ? self::TIDAK_TERCATAT : $status;
        $kosong = $belumSah ? 'Belum disahkan' : self::TIDAK_TERCATAT;
        $desimal = (int) $r['indikator']['desimal_tampilan'];
        $angka = fn ($nilai, int $skala = -1) => ['angka' => $nilai, 'desimal' => $skala < 0 ? $desimal : $skala];
        $target = $r['target_periode'] ?? ($r['tanpa_target_periode'] ? 'Tanpa target periode' : $kosong);
        $persen = $r['capaian_persen'] ?? $r['keterangan_capaian'] ?? ($r['tanpa_target_periode'] && $r['status_laporan'] !== 'belum_dapat_diukur' ? 'Tanpa target periode' : $nilaiKosong);

        return [
            'Kunci Rekap' => $r['id'], 'Kode Indikator' => $r['indikator']['kode'] ?? self::TIDAK_TERCATAT,
            'Indikator' => $r['indikator']['nama'] ?? self::TIDAK_TERCATAT, 'Periode' => $r['periode']['nama'],
            'Unit' => $r['unit']['nama'] ?? self::TIDAK_TERCATAT, 'Kode Sasaran' => $r['sasaran']['kode'] ?? self::TIDAK_TERCATAT,
            'Sasaran' => $r['sasaran']['deskripsi'] ?? self::TIDAK_TERCATAT, 'PIC Historis' => $r['pic']['nama'] ?? $kosong,
            'PIC Saat Ini' => $r['pic_saat_ini']['nama'] ?? 'Belum ditetapkan', 'Cara Hitung' => $r['indikator']['tipe_perhitungan'],
            'Definisi' => $r['indikator']['definisi'] ?? self::TIDAK_TERCATAT, 'Satuan' => $r['indikator']['satuan'] ?? self::TIDAK_TERCATAT,
            'Baseline' => $angka($r['baseline'] ?? 'Belum diisi'), 'Target PK' => $angka($r['target_pk'] ?? self::TIDAK_TERCATAT),
            'Target Periode' => $angka($target), 'Realisasi' => $angka($r['nilai'] ?? $nilaiKosong), 'Capaian (%)' => $angka($persen, 2),
            'Selisih terhadap Target PK' => $angka($r['selisih_target_pk'] ?? ($belumSah ? 'Belum disahkan' : ($r['nilai'] === null ? $nilaiKosong : self::TIDAK_TERCATAT))),
            'Kode Komponen' => $c ? ($c['kode'] ?? $kosong) : 'Tidak berlaku', 'Komponen' => $c ? ($c['label'] ?? $kosong) : 'Tidak berlaku',
            'Peran Komponen' => $c ? ($c['peran'] ?? $kosong) : 'Tidak berlaku', 'Bobot' => $angka($c ? ($c['bobot'] ?? $kosong) : 'Tidak berlaku', 12),
            'Target Komponen' => $angka($c ? ($c['target'] ?? ($r['tanpa_target_periode'] ? 'Tanpa target periode' : $kosong)) : 'Tidak berlaku'),
            'Realisasi Komponen' => $angka($c ? ($c['realisasi'] ?? ($belumSah ? 'Belum disahkan' : ($r['status_laporan'] === 'belum_dapat_diukur' ? $status : self::TIDAK_TERCATAT))) : 'Tidak berlaku'),
            'Status Laporan' => $status, 'Status Perhitungan' => $r['status_perhitungan'] ?? $kosong, 'Status Capaian' => $r['status_capaian'],
            'Keterangan Capaian' => $r['keterangan_capaian'] ?? 'Tidak ada', 'Alasan Tidak Dapat Dihitung' => $r['alasan_tidak_dapat_dihitung'] ?? 'Tidak ada',
            'Progres Kegiatan' => $r['narasi']['progres_kegiatan'] ?? $kosong, 'Kendala / Masalah' => $r['narasi']['kendala_masalah'] ?? $kosong,
            'Strategi Tindak Lanjut' => $r['narasi']['strategi_tindak_lanjut'] ?? $kosong, 'Tindak Lanjut Rekomendasi' => $r['narasi']['tindak_lanjut_rekomendasi'] ?? $kosong,
            'Rekomendasi Pimpinan' => $r['rekomendasi']['isi'] ?? 'Belum ada rekomendasi disetujui',
            'Persetujuan Rekomendasi' => $r['rekomendasi'] ? ($r['rekomendasi']['cadangan'] ? 'Persetujuan cadangan' : 'Pimpinan') : 'Belum ada rekomendasi disetujui',
            'Status Bukti' => $r['bukti'] ? (['lengkap' => 'Lengkap', 'belum_lengkap' => 'Belum lengkap', 'tidak_tercatat' => self::TIDAK_TERCATAT][$r['bukti']['status']]) : $kosong,
            'Bukti Wajib Terpenuhi' => $angka($r['bukti']['terpenuhi'] ?? $kosong, 0), 'Bukti Wajib Total' => $angka($r['bukti']['total'] ?? $kosong, 0),
            'Histori Bukti Tidak Dapat Dipenuhi' => ! $r['bukti'] || $r['bukti']['status'] === 'tidak_tercatat' ? $kosong : ($r['bukti']['histori_tidak_dapat_dipenuhi'] ? 'Ya' : 'Tidak'),
            'Detail Kegiatan' => ! $r['can']['lihat_kegiatan'] ? 'Detail kegiatan dibatasi sesuai akses unit' : ($r['kegiatan'] === null ? $kosong : 'Lihat sheet Kegiatan'),
            'Versi Pengukuran' => $r['versi'] ? (string) $r['versi']['nomor'] : 'Belum disahkan',
            'Disahkan Pada' => $r['versi']['disahkan_pada'] ?? 'Belum disahkan',
        ];
    }

    /** Key anggaran hilang, null dan nol memiliki arti berbeda pada klaim beku. */
    private function kegiatan(array $r, array $k): array
    {
        return array_combine(self::KOLOM_KEGIATAN, [
            $r['id'], $r['indikator']['kode'] ?? self::TIDAK_TERCATAT, $r['periode']['nama'], $r['unit']['nama'] ?? self::TIDAK_TERCATAT,
            $k['nama'] ?? self::TIDAK_TERCATAT, $k['status'] ?? self::TIDAK_TERCATAT,
            ['angka' => ! $k['anggaran_tercatat'] ? self::TIDAK_TERCATAT : ($k['anggaran'] ?? 'Belum diisi'), 'desimal' => 0, 'rupiah' => true],
            $k['kegiatan_bersama'] === null ? self::TIDAK_TERCATAT : ($k['kegiatan_bersama'] ? 'Ya' : 'Tidak'),
            $k['sumber_klaim'] ?? self::TIDAK_TERCATAT, $k['uraian_pelaksanaan'] ?? self::TIDAK_TERCATAT,
            $k['kendala'] ?? self::TIDAK_TERCATAT, $k['strategi_tindaklanjut'] ?? self::TIDAK_TERCATAT,
        ]);
    }

    /** Teks eksplisit mencegah kode, narasi, dan awalan formula dievaluasi Excel. */
    private function tulisBaris(Worksheet $sheet, int $nomor, array $values): void
    {
        if ($nomor === 9) {
            $sheet->fromArray(array_keys($values), null, 'A8');
        }
        foreach (array_values($values) as $index => $value) {
            $cell = $sheet->getCell([$index + 1, $nomor]);
            $numeric = false;
            if (is_array($value)) {
                $format = ($value['rupiah'] ?? false) ? '"Rp" #,##0' : '#,##0'.($value['desimal'] > 0 ? '.'.str_repeat('0', $value['desimal']) : '');
                $cell->getStyle()->getNumberFormat()->setFormatCode($format);
                $value = (string) $value['angka'];
                if (preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
                    // Padding desimal tidak menambah presisi; hitung digit sebelum konversi float oleh writer.
                    $normal = (string) BigDecimal::of($value)->strippedOfTrailingZeros();
                    $digits = ltrim(str_replace(['-', '.'], '', $normal), '0');
                    $numeric = strlen($digits) <= 15;
                }
            }
            if (is_string($value) && mb_strlen($value) > 32767) {
                throw ValidationException::withMessages(['ekspor' => 'Teks laporan melampaui batas sel Excel. Hubungi pengelola untuk memeriksa data.']);
            }
            $cell->setValueExplicit($value, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
    }

    /** Metadata dan kunci yang sama menjaga keterbacaan serta hubungan kedua sheet. */
    private function tataSheet(Worksheet $sheet, array $meta): void
    {
        $periode = collect($meta['pilihan_periode'])->firstWhere('id', $meta['filters']['periode_id']);
        foreach ([
            1 => 'Rekapitulasi Kinerja - '.$sheet->getTitle(),
            2 => 'Jadwal: '.$meta['jadwal']['tahun'].' - '.$meta['jadwal']['renstra'],
            3 => 'Periode: '.($periode['nama'] ?? 'Semua periode efektif'),
            4 => 'Waktu unduh: '.now(config('app.business_timezone'))->format('d-m-Y H:i:s').' WITA',
            5 => $meta['unit_dibatasi'] ? 'Sebagian unit tidak dimuat sesuai hak akses Anda' : 'Cakupan sesuai hak akses Anda',
            6 => 'Nilai indikator diulang per komponen; jangan dijumlahkan. Angka lebih dari 15 digit signifikan disimpan sebagai teks.',
        ] as $row => $text) {
            $sheet->setCellValueExplicit('B'.$row, $text, DataType::TYPE_STRING);
        }
        $last = $sheet->getHighestColumn();
        $end = max(8, $sheet->getHighestRow());
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A8:'.$last.'8')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A8:'.$last.'8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF122E92');
        $sheet->getStyle('A8:'.$last.$end)->getAlignment()->setWrapText(true)->setVertical('top');
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setWidth(24);
        }
        $sheet->getColumnDimension('A')->setVisible(false);
        $sheet->getColumnDimension($sheet->getTitle() === 'Rekap' ? 'C' : 'E')->setWidth(48);
        $sheet->freezePane('F9');
        $sheet->setAutoFilter('A8:'.$last.$end);
    }
}
