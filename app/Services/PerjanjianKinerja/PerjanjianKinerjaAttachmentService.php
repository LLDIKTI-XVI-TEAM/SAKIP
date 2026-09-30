<?php

namespace App\Services\PerjanjianKinerja;

use App\Jobs\CleanupStorageFileJob;
use App\Models\Berkas;
use App\Models\Pengaturan;
use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PerjanjianKinerjaAttachmentService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected PermissionResolver $permissionResolver,
    ) {}

    /**
     * Menyimpan daftar lampiran ke Perjanjian Kinerja (mode file, tautan, atau teks).
     *
     * @param  array<int, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    public function simpanLampiran(
        RenstraPk $pk,
        array $lampiran,
        User $actor,
        array &$storedPaths,
        ?PermissionDecision $uploadDecision = null,
        ?PermissionDecision $pkUpdateDecision = null,
    ): void {
        if ($uploadDecision === null) {
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
        }
        if (! $uploadDecision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
        }

        $adaFile = false;
        foreach ($lampiran as $item) {
            if (($item['mode'] ?? null) === 'file') {
                $adaFile = true;
                break;
            }
        }

        $isUploadActive = true;
        $maxKb = 10240;
        $allowedExtensions = [];
        $allowedFormatsStr = '';

        if ($adaFile) {
            $settings = Pengaturan::whereIn('kunci', [
                'berkas.unggahan_aktif',
                'berkas.ukuran_maks_kb',
                'berkas.format_diizinkan',
            ])->pluck('nilai', 'kunci');

            $isUploadActive = filter_var($settings->get('berkas.unggahan_aktif') ?? true, FILTER_VALIDATE_BOOLEAN);
            if (! $isUploadActive) {
                throw ValidationException::withMessages([
                    'lampiran' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.',
                ]);
            }

            $maxKb = (int) $settings->get('berkas.ukuran_maks_kb', 10240);
            $allowedFormatsStr = (string) $settings->get('berkas.format_diizinkan', 'pdf,docx,xlsx,jpg,jpeg,png');
            $allowedExtensions = array_filter(array_map('trim', explode(',', strtolower($allowedFormatsStr))));
        }

        foreach ($lampiran as $item) {
            $mode = $item['mode'] ?? 'file';
            $attributes = [
                'jenis_berkas_id' => null,
                'mode' => $mode,
                'uploaded_by' => $actor->id,
            ];

            if ($mode === 'file') {
                $file = $item['file'] ?? null;
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'lampiran' => 'Lampiran file tidak valid atau berkas belum diunggah.',
                    ]);
                }

                if ($maxKb > 0 && ($file->getSize() > $maxKb * 1024)) {
                    throw ValidationException::withMessages([
                        'lampiran' => "Ukuran file lampiran ({$file->getClientOriginalName()}) melebihi batas maksimum ({$maxKb} KB).",
                    ]);
                }

                $ext = strtolower($file->getClientOriginalExtension());
                if (! empty($allowedExtensions) && ! in_array($ext, $allowedExtensions, true)) {
                    throw ValidationException::withMessages([
                        'lampiran' => "Format file lampiran ({$file->getClientOriginalName()}) tidak diizinkan. Format yang diperbolehkan: {$allowedFormatsStr}.",
                    ]);
                }

                $path = $file->store("berkas/renstra_pk/{$pk->id}", 'local');
                if (! is_string($path)) {
                    throw new RuntimeException('Lampiran gagal disimpan ke private storage.');
                }

                $originalName = $file->getClientOriginalName();
                if (mb_strlen($originalName) > 255) {
                    $fileExt = $file->getClientOriginalExtension();
                    $suffix = $fileExt !== '' ? '.'.$fileExt : '';
                    $maxBaseLen = 255 - mb_strlen($suffix);
                    $baseName = mb_substr(pathinfo($originalName, PATHINFO_FILENAME), 0, max(1, $maxBaseLen));
                    $namaAsli = $baseName.$suffix;
                } else {
                    $namaAsli = $originalName;
                }

                $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');
                $mime = mb_substr($mime, 0, 255);

                $storedPaths[] = $path;
                $attributes += [
                    'nama_asli' => $namaAsli,
                    'path' => $path,
                    'mime' => $mime,
                    'ukuran_bytes' => $file->getSize(),
                ];
            } elseif ($mode === 'tautan') {
                $tautan = $item['tautan'] ?? ($item['url'] ?? null);
                if (empty($tautan)) {
                    throw ValidationException::withMessages([
                        'lampiran' => 'Tautan dokumen lampiran wajib diisi untuk mode tautan.',
                    ]);
                }
                $attributes += [
                    'nama_asli' => mb_substr((string) ($item['nama_asli'] ?? ($item['nama'] ?? 'Tautan Dokumen PK')), 0, 255),
                    'tautan' => $tautan,
                ];
            } elseif ($mode === 'teks') {
                $isiTeks = $item['isi_teks'] ?? ($item['teks'] ?? null);
                if (empty($isiTeks)) {
                    throw ValidationException::withMessages([
                        'lampiran' => 'Isi catatan dokumen lampiran wajib diisi untuk mode teks.',
                    ]);
                }
                if (! PerjanjianKinerjaSupport::isValidIsiTeks($isiTeks)) {
                    throw ValidationException::withMessages([
                        'lampiran' => 'Isi teks lampiran tidak boleh mengandung karakter byte NUL dan harus berformat UTF-8 valid.',
                    ]);
                }
                if (mb_strlen((string) $isiTeks) > 10000) {
                    throw ValidationException::withMessages([
                        'lampiran' => 'Isi teks lampiran tidak boleh melebihi 10.000 karakter.',
                    ]);
                }
                $attributes += [
                    'nama_asli' => mb_substr((string) ($item['nama_asli'] ?? ($item['nama'] ?? 'Catatan Dokumen PK')), 0, 255),
                    'isi_teks' => $isiTeks,
                ];
            }

            /** @var Berkas $berkas */
            $berkas = $pk->berkas()->create($attributes);

            $dasarIzin = $uploadDecision->toAuditBasis();
            if ($pkUpdateDecision !== null) {
                $dasarIzin['parent_permission'] = PermissionCodes::PK_UPDATE;
                $dasarIzin['basis_parent'] = $pkUpdateDecision->toAuditBasis();
            }

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.unggah',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiBaru: $this->metadataBerkasUntukAudit($berkas),
                dasarIzin: $dasarIzin,
            );
        }
    }

    /**
     * Kompensasi rollback storage saat terjadi kegagalan transaksi database.
     *
     * @param  list<string>  $paths
     */
    public function hapusFile(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $deleted = Storage::disk('local')->delete($path);
                if (! $deleted && Storage::disk('local')->exists($path)) {
                    Log::warning('Storage::delete() mengembalikan false saat rollback lampiran PK, menjadwalkan CleanupStorageFileJob.', ['path' => $path]);
                    CleanupStorageFileJob::dispatch($path, 'local');
                }
            } catch (Throwable $e) {
                Log::warning('Gagal menghapus file lampiran PK dari storage saat rollback: '.$e->getMessage(), ['path' => $path]);
                CleanupStorageFileJob::dispatch($path, 'local');
            }
        }
    }

    /**
     * Format snapshot atribut Perjanjian Kinerja beserta lampirannya untuk pencatatan audit log.
     *
     * @return array<string, mixed>
     */
    public function snapshot(RenstraPk $pk): array
    {
        return [
            'id' => $pk->id,
            'renstra_id' => $pk->renstra_id,
            'tahun' => $pk->tahun,
            'nomor_pk' => $pk->nomor_pk,
            'tanggal_pk' => $pk->tanggal_pk?->toDateString(),
            'lampiran' => $pk->berkas
                ->map(fn (Berkas $berkas) => $this->metadataBerkasUntukAudit($berkas))
                ->values()
                ->all(),
        ];
    }

    /**
     * Format representasi metadata lampiran Berkas untuk payload audit log yang aman.
     *
     * @return array<string, mixed>
     */
    public function metadataBerkasUntukAudit(Berkas $berkas): array
    {
        $metadata = [
            'id' => $berkas->id,
            'mode' => $berkas->mode,
            'nama_asli' => $berkas->nama_asli,
        ];

        if ($berkas->mode === 'file') {
            return $metadata + [
                'mime' => $berkas->mime,
                'ukuran_bytes' => $berkas->ukuran_bytes,
            ];
        }

        if ($berkas->mode === 'tautan') {
            return $metadata + [
                'tautan' => $berkas->tautan,
            ];
        }

        return $metadata + [
            'panjang_teks' => mb_strlen((string) $berkas->isi_teks),
        ];
    }
}
