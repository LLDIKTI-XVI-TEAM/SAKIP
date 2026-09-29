<?php

namespace App\Services;

use App\Jobs\CleanupStorageFileJob;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RenstraPkService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected PermissionResolver $permissionResolver,
    ) {}

    /**
     * Membersihkan string alasan audit dari byte NUL dan karakter kontrol ilegal.
     */
    public static function sanitizeAlasan(mixed $rawAlasan): string
    {
        if (! is_string($rawAlasan)) {
            return '';
        }

        // Pastikan encoding UTF-8 valid untuk mencegah SQLSTATE[22021]
        $clean = mb_convert_encoding($rawAlasan, 'UTF-8', 'UTF-8');

        // Hapus byte NUL untuk mencegah exception PostgreSQL SQLSTATE[22P05]
        $clean = str_replace("\0", '', $clean);

        // Hapus karakter kontrol yang tidak dapat dicetak, pertahankan newline dan tab
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean)
            ?? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean)
            ?? '';

        $clean = trim($clean);

        if ($clean === '') {
            return '';
        }

        return mb_substr($clean, 0, 1000, 'UTF-8');
    }

    /**
     * Mengunci aktor, relasi peran, peran aktif, dan permission terkait secara deterministik
     * untuk mencegah race condition / TOCTOU pada mutasi wewenang concurrent.
     *
     * @param  list<string>  $permissionCodes
     */
    protected function lockActorAndPermissions(User $actor, array $permissionCodes): User
    {
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if ($lockedActor->status !== 'aktif') {
            throw new AuthorizationException('Pengguna tidak aktif.');
        }

        $roleIds = DB::table('user_roles')
            ->where('user_id', $lockedActor->id)
            ->lockForUpdate()
            ->pluck('role_id')
            ->all();

        if ($roleIds !== []) {
            Role::query()->whereIn('id', array_unique($roleIds))->orderBy('id')->sharedLock()->get();
        }

        if ($permissionCodes !== []) {
            Permission::query()->whereIn('kode', array_unique($permissionCodes))->orderBy('id')->sharedLock()->get();
        }

        return $lockedActor;
    }

    /**
     * Membuat data Perjanjian Kinerja beserta lampiran opsionalnya.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): RenstraPk
    {
        $createDecision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_CREATE);
        if (! $createDecision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk mencatat Perjanjian Kinerja.');
        }

        if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
            if (! $uploadDecision->allowed) {
                throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
            }
        }

        $renstra = Renstra::findOrFail($data['renstra_id']);
        $tahun = (int) $data['tahun'];

        if ($tahun < $renstra->tahun_mulai || $tahun > $renstra->tahun_selesai) {
            throw ValidationException::withMessages([
                'tahun' => "Tahun Perjanjian Kinerja ({$tahun}) harus berada dalam rentang tahun Renstra ({$renstra->tahun_mulai} - {$renstra->tahun_selesai}).",
            ]);
        }

        if (RenstraPk::where('renstra_id', $renstra->id)->where('tahun', $tahun)->exists()) {
            throw ValidationException::withMessages([
                'tahun' => 'Perjanjian Kinerja untuk Renstra dan tahun tersebut sudah terdaftar.',
            ]);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $tahun, $actor, &$storedPaths) {
                $requiredCodes = [PermissionCodes::PK_CREATE];
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $requiredCodes[] = PermissionCodes::BERKAS_UPLOAD;
                }

                $lockedActor = $this->lockActorAndPermissions($actor, $requiredCodes);

                $createDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_CREATE);
                if (! $createDecision->allowed) {
                    throw new AuthorizationException('Pengguna tidak memiliki izin untuk mencatat Perjanjian Kinerja.');
                }

                $uploadDecision = null;
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $uploadDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_UPLOAD);
                    if (! $uploadDecision->allowed) {
                        throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
                    }
                }

                // Kunci Renstra dengan sharedLock untuk mencegah race condition perubahan rentang tahun Renstra
                $renstraLocked = Renstra::query()->whereKey($data['renstra_id'])->sharedLock()->firstOrFail();
                if ($tahun < $renstraLocked->tahun_mulai || $tahun > $renstraLocked->tahun_selesai) {
                    throw ValidationException::withMessages([
                        'tahun' => "Tahun Perjanjian Kinerja ({$tahun}) harus berada dalam rentang tahun Renstra ({$renstraLocked->tahun_mulai} - {$renstraLocked->tahun_selesai}).",
                    ]);
                }

                if (RenstraPk::where('renstra_id', $renstraLocked->id)->where('tahun', $tahun)->exists()) {
                    throw ValidationException::withMessages([
                        'tahun' => 'Perjanjian Kinerja untuk Renstra dan tahun tersebut sudah terdaftar.',
                    ]);
                }

                $pk = RenstraPk::create([
                    'renstra_id' => $renstraLocked->id,
                    'tahun' => $tahun,
                    'nomor_pk' => $data['nomor_pk'],
                    'tanggal_pk' => $data['tanggal_pk'],
                    'created_by' => $lockedActor->id,
                ]);

                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $this->simpanLampiran($pk, $data['lampiran'], $lockedActor, $storedPaths, $uploadDecision);
                }

                $pk->load(['renstra', 'creator', 'berkas']);

                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'renstra_pk.buat',
                    objekTipe: 'renstra_pk',
                    objekId: $pk->id,
                    nilaiBaru: $this->snapshot($pk),
                    dasarIzin: $createDecision->toAuditBasis(),
                );

                return $pk;
            });
        } catch (QueryException $exception) {
            $this->hapusFile($storedPaths);

            if ($this->adalahDuplikasiPk($exception)) {
                throw ValidationException::withMessages([
                    'tahun' => 'Perjanjian Kinerja untuk Renstra dan tahun tersebut sudah terdaftar.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    /**
     * Memperbarui rincian Perjanjian Kinerja dengan pencatatan audit beralasan.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(RenstraPk $pk, array $data, string $alasan, User $actor): RenstraPk
    {
        $alasan = self::sanitizeAlasan($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan perubahan Perjanjian Kinerja wajib diisi.',
            ]);
        }

        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_UPDATE);
        if (! $decision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.');
        }

        if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
            if (! $uploadDecision->allowed) {
                throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
            }
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($pk, $data, $alasan, $actor, &$storedPaths) {
                $requiredCodes = [PermissionCodes::PK_UPDATE];
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $requiredCodes[] = PermissionCodes::BERKAS_UPLOAD;
                }

                $lockedActor = $this->lockActorAndPermissions($actor, $requiredCodes);

                $decision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_UPDATE);
                if (! $decision->allowed) {
                    throw new AuthorizationException('Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.');
                }

                $uploadDecision = null;
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $uploadDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_UPLOAD);
                    if (! $uploadDecision->allowed) {
                        throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
                    }
                }

                /** @var RenstraPk $pkLocked */
                $pkLocked = RenstraPk::where('id', $pk->id)->lockForUpdate()->firstOrFail();
                $pkLocked->load(['berkas']);
                $nilaiLama = $this->snapshot($pkLocked);

                if (array_key_exists('nomor_pk', $data)) {
                    $pkLocked->nomor_pk = $data['nomor_pk'];
                }
                if (array_key_exists('tanggal_pk', $data)) {
                    $pkLocked->tanggal_pk = $data['tanggal_pk'];
                }
                $pkLocked->save();

                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $this->simpanLampiran($pkLocked, $data['lampiran'], $lockedActor, $storedPaths, $uploadDecision, $decision);
                }

                $pkLocked = $pkLocked->fresh(['renstra', 'creator', 'berkas']);
                $nilaiBaru = $this->snapshot($pkLocked);

                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'renstra_pk.ubah',
                    objekTipe: 'renstra_pk',
                    objekId: $pkLocked->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $nilaiBaru,
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $pkLocked;
            });
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    /**
     * Menghapus lampiran Perjanjian Kinerja dengan guard imutabilitas jadwal aktif.
     */
    public function deleteBerkas(RenstraPk $pk, Berkas $berkas, string $alasan, User $actor): void
    {
        if ($berkas->berkasable_id !== $pk->id || ! in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true)) {
            throw new RuntimeException('Berkas bukan merupakan lampiran dari Perjanjian Kinerja ini.');
        }

        $alasan = self::sanitizeAlasan($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penghapusan lampiran wajib diisi.',
            ]);
        }

        $pkUpdateDecision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_UPDATE);
        $berkasDeleteDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_DELETE);

        if (! $pkUpdateDecision->allowed || ! $berkasDeleteDecision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk menghapus lampiran Perjanjian Kinerja.');
        }

        $path = null;

        $penolakan = DB::transaction(function () use ($pk, $berkas, $actor, $alasan, &$path): ?string {
            $lockedActor = $this->lockActorAndPermissions($actor, [
                PermissionCodes::PK_UPDATE,
                PermissionCodes::BERKAS_DELETE,
            ]);

            $pkUpdateDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_UPDATE);
            $berkasDeleteDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_DELETE);

            if (! $pkUpdateDecision->allowed || ! $berkasDeleteDecision->allowed) {
                throw new AuthorizationException('Pengguna tidak memiliki izin untuk menghapus lampiran Perjanjian Kinerja.');
            }

            /** @var RenstraPk $pkLocked */
            $pkLocked = RenstraPk::where('id', $pk->id)->lockForUpdate()->firstOrFail();

            // Kunci baris jadwal_tahunan terkait untuk mencegah race condition / TOCTOU aktivasi jadwal
            $jadwalTerkait = JadwalTahunan::where(function ($q) use ($pkLocked) {
                $q->where('renstra_pk_id', $pkLocked->id)
                    ->orWhere(fn ($sub) => $sub->where('renstra_id', $pkLocked->renstra_id)->where('tahun', $pkLocked->tahun));
            })->lockForUpdate()->get();

            $isJadwalMengunci = $jadwalTerkait->contains(function ($j) {
                return $j->status === 'aktif'
                    || $j->status === 'ditutup'
                    || ! is_null($j->activated_at);
            });

            if ($isJadwalMengunci) {
                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'berkas.hapus_ditolak',
                    objekTipe: 'berkas',
                    objekId: $berkas->id,
                    nilaiLama: $this->metadataBerkasUntukAudit($berkas),
                    nilaiBaru: ['alasan_penolakan' => 'jadwal_tahunan_aktif'],
                    alasan: $alasan,
                    dasarIzin: $berkasDeleteDecision->toAuditBasis(),
                );

                return 'jadwal_tahunan_aktif';
            }

            /** @var Berkas|null $berkasLocked */
            $berkasLocked = Berkas::where('id', $berkas->id)
                ->where('berkasable_id', $pkLocked->id)
                ->whereIn('berkasable_type', ['renstra_pk', RenstraPk::class])
                ->whereNull('dihapus_pada')
                ->lockForUpdate()
                ->first();

            if (! $berkasLocked) {
                throw ValidationException::withMessages([
                    'berkas' => 'Lampiran berkas sudah dihapus atau tidak ditemukan.',
                ]);
            }

            $path = $berkasLocked->mode === 'file' && is_string($berkasLocked->path) ? $berkasLocked->path : null;
            $nilaiLama = $this->metadataBerkasUntukAudit($berkasLocked);

            $berkasLocked->dihapus_oleh = $lockedActor->id;
            $berkasLocked->save();
            $berkasLocked->delete();

            $this->auditLogger->catat(
                actor: $lockedActor,
                tindakan: 'berkas.hapus',
                objekTipe: 'berkas',
                objekId: $berkasLocked->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $berkasDeleteDecision->toAuditBasis(),
            );

            return null;
        });

        if ($penolakan === 'jadwal_tahunan_aktif') {
            throw ValidationException::withMessages([
                'berkas' => 'Lampiran Perjanjian Kinerja tidak dapat dihapus karena Jadwal Tahunan sudah aktif.',
            ]);
        }

        if ($path !== null) {
            DB::afterCommit(function () use ($path) {
                try {
                    $deleted = Storage::disk('local')->delete($path);
                    if (! $deleted && Storage::disk('local')->exists($path)) {
                        Log::warning('Storage::delete() mengembalikan false untuk lampiran PK, menjadwalkan CleanupStorageFileJob.', ['path' => $path]);
                        CleanupStorageFileJob::dispatch($path, 'local');
                    }
                } catch (Throwable $e) {
                    Log::warning('Gagal menghapus file lampiran PK dari storage setelah commit: '.$e->getMessage(), ['path' => $path]);
                    CleanupStorageFileJob::dispatch($path, 'local');
                }
            });
        }
    }

    /**
     * Mengunggah lampiran ke Perjanjian Kinerja secara aman concurrency.
     *
     * @param  array<int, array<string, mixed>>  $lampiran
     * @return list<Berkas>
     */
    public function uploadBerkas(RenstraPk $pk, array $lampiran, User $actor): array
    {
        $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
        if (! $uploadDecision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
        }

        $pkUpdateDecision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_UPDATE);
        if (! $pkUpdateDecision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.');
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($pk, $lampiran, $actor, &$storedPaths) {
                $lockedActor = $this->lockActorAndPermissions($actor, [
                    PermissionCodes::PK_UPDATE,
                    PermissionCodes::BERKAS_UPLOAD,
                ]);

                $pkUpdateDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_UPDATE);
                if (! $pkUpdateDecision->allowed) {
                    throw new AuthorizationException('Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.');
                }

                $uploadDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_UPLOAD);
                if (! $uploadDecision->allowed) {
                    throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
                }

                /** @var RenstraPk $pkLocked */
                $pkLocked = RenstraPk::where('id', $pk->id)->lockForUpdate()->firstOrFail();
                $this->simpanLampiran($pkLocked, $lampiran, $lockedActor, $storedPaths, $uploadDecision, $pkUpdateDecision);

                return $pkLocked->fresh(['berkas'])->berkas->all();
            });
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    protected function simpanLampiran(
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
     * @param  list<string>  $paths
     */
    protected function hapusFile(array $paths): void
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
     * @return array<string, mixed>
     */
    protected function snapshot(RenstraPk $pk): array
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
     * @return array<string, mixed>
     */
    protected function metadataBerkasUntukAudit(Berkas $berkas): array
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

    protected function adalahDuplikasiPk(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return $sqlState === '23505'
            && (str_contains($exception->getMessage(), 'renstra_pk_renstra_id_tahun_unique') || str_contains($exception->getMessage(), 'renstra_pk'));
    }
}
