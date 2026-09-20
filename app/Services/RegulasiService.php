<?php

namespace App\Services;

use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\User;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RegulasiService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Regulasi
    {
        $storedPaths = [];
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::REGULASI_CREATE);

        try {
            return DB::transaction(function () use ($data, $actor, $decision, &$storedPaths): Regulasi {
                $regulasi = Regulasi::query()->create([
                    ...Arr::except($data, ['lampiran', 'alasan']),
                    'created_by' => $actor->id,
                ]);

                $this->simpanLampiran(
                    $regulasi,
                    $data['lampiran'] ?? [],
                    $actor,
                    $decision,
                    $storedPaths,
                );

                $regulasi->load('berkas');
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'regulasi.buat',
                    objekTipe: 'regulasi',
                    objekId: $regulasi->id,
                    nilaiBaru: $this->snapshot($regulasi),
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $regulasi;
            });
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Regulasi $regulasi, array $data, User $actor): Regulasi
    {
        $storedPaths = [];
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::REGULASI_UPDATE);

        try {
            return DB::transaction(function () use ($regulasi, $data, $actor, $decision, &$storedPaths): Regulasi {
                $regulasi->load('berkas');
                $nilaiLama = $this->snapshot($regulasi);

                $regulasi->update(Arr::except($data, ['lampiran', 'alasan']));

                $this->simpanLampiran(
                    $regulasi,
                    $data['lampiran'] ?? [],
                    $actor,
                    $decision,
                    $storedPaths,
                );

                $regulasi->refresh()->load('berkas');
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'regulasi.ubah',
                    objekTipe: 'regulasi',
                    objekId: $regulasi->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $this->snapshot($regulasi),
                    alasan: $data['alasan'],
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $regulasi;
            });
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    public function delete(Regulasi $regulasi, string $alasan, User $actor): void
    {
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::REGULASI_DELETE);
        $referensiAktif = $this->referensiAktif($regulasi);
        $jumlahRenstraAktif = $referensiAktif['jumlah_renstra_aktif'];
        $jumlahIndikatorAktif = $referensiAktif['jumlah_indikator_aktif'];

        if ($jumlahRenstraAktif > 0 || $jumlahIndikatorAktif > 0) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'regulasi.hapus_ditolak',
                objekTipe: 'regulasi',
                objekId: $regulasi->id,
                nilaiLama: $regulasi->withoutRelations()->toArray(),
                nilaiBaru: [
                    'alasan_penolakan' => 'masih_dirujuk_data_aktif',
                    'jumlah_renstra_aktif' => $jumlahRenstraAktif,
                    'jumlah_indikator_aktif' => $jumlahIndikatorAktif,
                ],
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            throw ValidationException::withMessages([
                'regulasi' => 'Regulasi tidak dapat dihapus karena masih dirujuk oleh Renstra atau Indikator aktif.',
            ]);
        }

        DB::transaction(function () use ($regulasi, $alasan, $actor, $decision): void {
            $regulasi->load('berkas');
            $nilaiLama = $this->snapshot($regulasi);
            $paths = $regulasi->berkas
                ->where('mode', 'file')
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values()
                ->all();

            foreach ($regulasi->berkas as $berkas) {
                $berkas->dihapus_oleh = $actor->id;
                $berkas->save();
                $berkas->delete();
            }

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'regulasi.hapus',
                objekTipe: 'regulasi',
                objekId: $regulasi->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            $regulasi->delete();

            DB::afterCommit(fn () => $this->hapusFile($paths));
        });
    }

    public function deleteAttachment(Regulasi $regulasi, Berkas $berkas, string $alasan, User $actor): void
    {
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_DELETE);
        $referensiAktif = $this->referensiAktif($regulasi);

        if ($referensiAktif['jumlah_renstra_aktif'] > 0 || $referensiAktif['jumlah_indikator_aktif'] > 0) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $this->metadataBerkasUntukAudit($berkas),
                nilaiBaru: [
                    'alasan_penolakan' => 'regulasi_masih_dirujuk_data_aktif',
                    ...$referensiAktif,
                ],
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            throw ValidationException::withMessages([
                'berkas' => 'Lampiran tidak dapat dihapus karena regulasi masih dirujuk oleh Renstra atau Indikator aktif.',
            ]);
        }

        DB::transaction(function () use ($berkas, $alasan, $actor, $decision): void {
            $path = $berkas->mode === 'file' && is_string($berkas->path) ? $berkas->path : null;
            $nilaiLama = $this->metadataBerkasUntukAudit($berkas);

            $berkas->dihapus_oleh = $actor->id;
            $berkas->save();
            $berkas->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            if ($path !== null) {
                DB::afterCommit(fn () => $this->hapusFile([$path]));
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    private function simpanLampiran(
        Regulasi $regulasi,
        array $lampiran,
        User $actor,
        PermissionDecision $decision,
        array &$storedPaths,
    ): void {
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

    /** @return array{jumlah_renstra_aktif: int, jumlah_indikator_aktif: int} */
    private function referensiAktif(Regulasi $regulasi): array
    {
        return [
            'jumlah_renstra_aktif' => $regulasi->renstras()->where('is_aktif', true)->count(),
            'jumlah_indikator_aktif' => $regulasi->indikatorKinerjas()->where('is_aktif', true)->count(),
        ];
    }

    /** @param list<string> $paths */
    private function hapusFile(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Regulasi $regulasi): array
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
    private function metadataBerkasUntukAudit(Berkas $berkas): array
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
