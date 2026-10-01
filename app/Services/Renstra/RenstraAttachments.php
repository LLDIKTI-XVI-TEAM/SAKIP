<?php

namespace App\Services\Renstra;

use App\Models\Berkas;
use App\Models\Pengaturan;
use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionDecision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Helper bersama Action Renstra untuk unggahan/pembersihan file privat
 * serta metadata dan snapshot lampiran, termasuk pencatatan audit unggahan.
 * Transaksi, keputusan izin, dan alur mutasi tetap dikelola oleh pemanggil.
 */
class RenstraAttachments
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<array-key, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    public function simpanLampiran(
        Renstra $renstra,
        array $lampiran,
        User $actor,
        PermissionDecision $uploadDecision,
        array &$storedPaths,
    ): void {
        if (empty($lampiran)) {
            return;
        }

        $adaFile = false;
        foreach ($lampiran as $item) {
            if (($item['mode'] ?? null) === 'file') {
                $adaFile = true;
                break;
            }
        }

        $maxKb = 10240;
        $allowedExtensions = [];
        $allowedFormatsStr = '';

        if ($adaFile) {
            $pengaturan = Pengaturan::query()
                ->whereIn('kunci', [
                    'berkas.unggahan_aktif',
                    'berkas.ukuran_maks_kb',
                    'berkas.format_diizinkan',
                ])
                ->pluck('nilai', 'kunci');

            $isUploadActive = filter_var($pengaturan->get('berkas.unggahan_aktif', 'true'), FILTER_VALIDATE_BOOLEAN);
            if (! $isUploadActive) {
                throw ValidationException::withMessages([
                    'lampiran' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.',
                ]);
            }

            $maxKb = (int) $pengaturan->get('berkas.ukuran_maks_kb', 10240);
            $allowedFormatsStr = (string) $pengaturan->get('berkas.format_diizinkan', 'pdf,docx,xlsx,jpg,jpeg,png');
            $allowedExtensions = array_values(array_filter(array_map('trim', explode(',', strtolower($allowedFormatsStr)))));
        }

        foreach ($lampiran as $item) {
            $attributes = [
                'jenis_berkas_id' => null,
                'mode' => $item['mode'],
                'uploaded_by' => $actor->id,
            ];

            if ($item['mode'] === 'file') {
                $file = $item['file'] ?? null;

                if (! $file instanceof UploadedFile) {
                    throw new RuntimeException('Lampiran file tidak valid.');
                }

                if ($maxKb > 0 && ($file->getSize() > $maxKb * 1024)) {
                    throw ValidationException::withMessages([
                        'lampiran' => "Ukuran file lampiran ({$file->getClientOriginalName()}) melebihi batas maksimum yang diizinkan ({$maxKb} KB).",
                    ]);
                }

                $ext = strtolower($file->getClientOriginalExtension());
                if (! empty($allowedExtensions) && ! in_array($ext, $allowedExtensions, true)) {
                    throw ValidationException::withMessages([
                        'lampiran' => "Format file lampiran ({$file->getClientOriginalName()}) tidak diizinkan. Format yang diperbolehkan: {$allowedFormatsStr}.",
                    ]);
                }

                $path = $file->store("berkas/renstra/{$renstra->id}", 'local');

                if (! is_string($path)) {
                    throw new RuntimeException('Lampiran gagal disimpan ke private storage.');
                }

                $storedPaths[] = $path;
                $attributes += [
                    'nama_asli' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'ukuran_bytes' => $file->getSize(),
                ];
            } elseif ($item['mode'] === 'tautan') {
                $attributes['tautan'] = $item['tautan'];
            } else {
                $attributes['isi_teks'] = $item['isi_teks'];
            }

            $berkas = $renstra->berkas()->create($attributes);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.unggah',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiBaru: $this->metadataBerkasUntukAudit($berkas),
                dasarIzin: $uploadDecision->toAuditBasis(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Renstra $renstra): array
    {
        return [
            'id' => $renstra->id,
            'kode' => $renstra->kode,
            'nama' => $renstra->nama,
            'tahun_mulai' => $renstra->tahun_mulai,
            'tahun_selesai' => $renstra->tahun_selesai,
            'tahun_akhir' => $renstra->tahun_selesai,
            'status' => $renstra->status,
            'is_aktif' => $renstra->is_aktif,
            'dasar_hukum' => $renstra->dasar_hukum,
            'deskripsi' => $renstra->deskripsi,
            'keterangan' => $renstra->deskripsi,
            'regulasi_id' => $renstra->regulasi_id,
            'rujukan_regulasi' => $renstra->regulasi ? [
                'id' => $renstra->regulasi->id,
                'jenis' => $renstra->regulasi->jenis,
                'nomor' => $renstra->regulasi->nomor,
                'tahun' => $renstra->regulasi->tahun,
                'tentang' => $renstra->regulasi->tentang,
            ] : null,
            'lampiran' => $renstra->berkas->map(fn (Berkas $b) => $this->metadataBerkasUntukAudit($b))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataBerkasUntukAudit(Berkas $berkas): array
    {
        $meta = [
            'id' => $berkas->id,
            'mode' => $berkas->mode,
            'jenis_berkas_id' => $berkas->jenis_berkas_id,
            'uploaded_by' => $berkas->uploaded_by,
        ];

        if ($berkas->mode === 'file') {
            $meta += [
                'nama_asli' => $berkas->nama_asli,
                'mime' => $berkas->mime,
                'ukuran_bytes' => $berkas->ukuran_bytes,
            ];
        } elseif ($berkas->mode === 'tautan') {
            $meta['tautan'] = $berkas->tautan;
        } else {
            $meta['panjang_teks'] = mb_strlen((string) $berkas->isi_teks);
        }

        return $meta;
    }

    /**
     * Kegagalan fisik dilaporkan tanpa menutupi error transaksi atau mengubah hasil commit.
     * Pemanggil hanya memasukkan unggahan baru saat kompensasi, atau file terhapus sesudah commit.
     *
     * @param  list<string>  $paths
     */
    public function hapusFile(array $paths, string $operasi): void
    {
        if ($paths === []) {
            return;
        }

        try {
            if (Storage::disk('local')->delete($paths)) {
                return;
            }
            $failure = 'storage_mengembalikan_false';
        } catch (Throwable $exception) {
            $failure = $exception::class;
        }

        // Path privat, isi lampiran, dan pesan exception tidak masuk log diagnostik.
        Log::warning('Pembersihan file Renstra belum selesai.', [
            'operasi' => $operasi,
            'jumlah_file' => count($paths),
            'jenis_kegagalan' => $failure,
        ]);
    }
}
