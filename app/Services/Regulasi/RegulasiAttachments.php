<?php

namespace App\Services\Regulasi;

use App\Models\Berkas;
use App\Models\Pengaturan;
use App\Models\Regulasi;
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
 * Helper bersama Action dan policy Regulasi untuk unggahan/pembersihan file privat
 * serta metadata dan snapshot lampiran, termasuk pencatatan audit unggahan.
 * Transaksi, keputusan izin, dan alur mutasi tetap dikelola oleh pemanggil.
 */
class RegulasiAttachments
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<array-key, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    public function simpanLampiran(
        Regulasi $regulasi,
        array $lampiran,
        User $actor,
        PermissionDecision $decision,
        array &$storedPaths,
    ): void {
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
            $settings = Pengaturan::whereIn('kunci', [
                'berkas.unggahan_aktif',
                'berkas.ukuran_maks_kb',
                'berkas.format_diizinkan',
            ])->pluck('nilai', 'kunci');

            if (! filter_var($settings->get('berkas.unggahan_aktif', 'true'), FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'lampiran' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.',
                ]);
            }

            $maxKb = (int) $settings->get('berkas.ukuran_maks_kb', 10240);
            $allowedFormatsStr = (string) $settings->get('berkas.format_diizinkan', 'pdf,docx,xlsx,jpg,jpeg,png');
            $allowedExtensions = array_filter(array_map('trim', explode(',', strtolower($allowedFormatsStr))));
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

                $path = $file->store("berkas/regulasi/{$regulasi->id}", 'local');

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

            $berkas = $regulasi->berkas()->create($attributes);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.unggah',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiBaru: $this->metadataBerkasUntukAudit($berkas),
                dasarIzin: $decision->toAuditBasis(),
            );
        }
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
        Log::warning('Pembersihan file Regulasi belum selesai.', [
            'operasi' => $operasi,
            'jumlah_file' => count($paths),
            'jenis_kegagalan' => $failure,
        ]);
    }

    /** @return array<string, mixed> */
    public function snapshot(Regulasi $regulasi): array
    {
        return [
            ...$regulasi->withoutRelations()->toArray(),
            'lampiran' => $regulasi->berkas
                ->map(fn (Berkas $berkas) => $this->metadataBerkasUntukAudit($berkas))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function metadataBerkasUntukAudit(Berkas $berkas): array
    {
        $metadata = [
            'id' => $berkas->id,
            'mode' => $berkas->mode,
            'jenis_berkas_id' => $berkas->jenis_berkas_id,
        ];

        if ($berkas->mode === 'file') {
            return $metadata + [
                'nama_asli' => $berkas->nama_asli,
                'mime' => $berkas->mime,
                'ukuran_bytes' => $berkas->ukuran_bytes,
            ];
        }

        if ($berkas->mode === 'tautan') {
            return $metadata + ['tautan' => $berkas->tautan];
        }

        return $metadata + ['panjang_teks' => mb_strlen((string) $berkas->isi_teks)];
    }
}
