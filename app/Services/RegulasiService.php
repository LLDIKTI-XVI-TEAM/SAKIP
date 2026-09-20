<?php

namespace App\Services;

use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\User;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
        $this->pastikanIzinDiizinkan($decision);

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
        } catch (QueryException $exception) {
            $this->hapusFile($storedPaths);

            if ($this->adalahDuplikasiRegulasi($exception)) {
                throw ValidationException::withMessages([
                    'nomor' => 'Kombinasi jenis, nomor, dan tahun regulasi sudah terdaftar.',
                ]);
            }

            throw $exception;
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

        if (! $decision->allowed) {
            $regulasi->load('berkas');
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'regulasi.ubah_ditolak',
                objekTipe: 'regulasi',
                objekId: $regulasi->id,
                nilaiLama: $this->snapshot($regulasi),
                alasan: $data['alasan'],
                dasarIzin: $decision->toAuditBasis(),
            );

            $this->pastikanIzinDiizinkan($decision);
        }

        try {
            return DB::transaction(function () use ($regulasi, $data, $actor, $decision, &$storedPaths): Regulasi {
                $regulasiTerkini = Regulasi::query()
                    ->with('berkas')
                    ->lockForUpdate()
                    ->findOrFail($regulasi->id);

                $versiDikirim = (int) $data['versi'];

                if ($regulasiTerkini->versi !== $versiDikirim) {
                    throw new ConflictHttpException(
                        'Dasar aturan telah diubah oleh pengguna lain. Muat ulang data terbaru sebelum menyimpan perubahan.',
                    );
                }

                $nilaiLama = $this->snapshot($regulasiTerkini);

                $regulasiTerkini->update([
                    ...Arr::except($data, ['lampiran', 'alasan', 'versi']),
                    'versi' => $regulasiTerkini->versi + 1,
                ]);

                $this->simpanLampiran(
                    $regulasiTerkini,
                    $data['lampiran'] ?? [],
                    $actor,
                    $decision,
                    $storedPaths,
                );

                $regulasiTerkini->refresh()->load('berkas');
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'regulasi.ubah',
                    objekTipe: 'regulasi',
                    objekId: $regulasiTerkini->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $this->snapshot($regulasiTerkini),
                    alasan: $data['alasan'],
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $regulasiTerkini;
            });
        } catch (ConflictHttpException $exception) {
            $regulasiTerkini = Regulasi::query()->with('berkas')->findOrFail($regulasi->id);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'regulasi.ubah_ditolak',
                objekTipe: 'regulasi',
                objekId: $regulasiTerkini->id,
                nilaiLama: $this->snapshot($regulasiTerkini),
                nilaiBaru: [
                    'alasan_penolakan' => 'versi_usang',
                    'versi_dikirim' => (int) $data['versi'],
                    'versi_saat_ini' => $regulasiTerkini->versi,
                ],
                alasan: $data['alasan'],
                dasarIzin: $decision->toAuditBasis(),
            );

            throw $exception;
        } catch (QueryException $exception) {
            $this->hapusFile($storedPaths);

            if ($this->adalahDuplikasiRegulasi($exception)) {
                throw ValidationException::withMessages([
                    'nomor' => 'Kombinasi jenis, nomor, dan tahun regulasi sudah terdaftar.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    public function delete(Regulasi $regulasi, string $alasan, User $actor): void
    {
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::REGULASI_DELETE);

        if (! $decision->allowed) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'regulasi.hapus_ditolak',
                objekTipe: 'regulasi',
                objekId: $regulasi->id,
                nilaiLama: $regulasi->withoutRelations()->toArray(),
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            $this->pastikanIzinDiizinkan($decision);
        }

        $referensiAktif = DB::transaction(function () use ($regulasi, $alasan, $actor, $decision): ?array {
            $regulasiTerkini = Regulasi::query()->lockForUpdate()->findOrFail($regulasi->id);
            $referensiAktif = $this->referensiAktifTerkunci($regulasiTerkini);

            if ($referensiAktif['jumlah_renstra_aktif'] > 0 || $referensiAktif['jumlah_indikator_aktif'] > 0) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'regulasi.hapus_ditolak',
                    objekTipe: 'regulasi',
                    objekId: $regulasiTerkini->id,
                    nilaiLama: $regulasiTerkini->withoutRelations()->toArray(),
                    nilaiBaru: [
                        'alasan_penolakan' => 'masih_dirujuk_data_aktif',
                        ...$referensiAktif,
                    ],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $referensiAktif;
            }

            $regulasiTerkini->load('berkas');
            $nilaiLama = $this->snapshot($regulasiTerkini);
            $paths = $regulasiTerkini->berkas
                ->where('mode', 'file')
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->values()
                ->all();

            foreach ($regulasiTerkini->berkas as $berkas) {
                $nilaiLamaBerkas = $this->metadataBerkasUntukAudit($berkas);
                $berkas->dihapus_oleh = $actor->id;
                $berkas->save();
                $berkas->delete();

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'berkas.hapus',
                    objekTipe: 'berkas',
                    objekId: $berkas->id,
                    nilaiLama: $nilaiLamaBerkas,
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );
            }

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'regulasi.hapus',
                objekTipe: 'regulasi',
                objekId: $regulasiTerkini->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            $regulasiTerkini->delete();

            DB::afterCommit(fn () => $this->hapusFile($paths));

            return null;
        });

        if ($referensiAktif !== null) {
            throw ValidationException::withMessages([
                'regulasi' => 'Regulasi tidak dapat dihapus karena masih dirujuk oleh Renstra atau Indikator aktif.',
            ]);
        }
    }

    public function deleteAttachment(Regulasi $regulasi, Berkas $berkas, string $alasan, User $actor): void
    {
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_DELETE);

        if (! $decision->allowed) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $this->metadataBerkasUntukAudit($berkas),
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            $this->pastikanIzinDiizinkan($decision);
        }

        $referensiAktif = DB::transaction(function () use ($regulasi, $berkas, $alasan, $actor, $decision): ?array {
            $regulasiTerkini = Regulasi::query()->lockForUpdate()->findOrFail($regulasi->id);
            $berkasTerkini = Berkas::query()->lockForUpdate()->findOrFail($berkas->id);
            $referensiAktif = $this->referensiAktifTerkunci($regulasiTerkini);

            if ($referensiAktif['jumlah_renstra_aktif'] > 0 || $referensiAktif['jumlah_indikator_aktif'] > 0) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'berkas.hapus_ditolak',
                    objekTipe: 'berkas',
                    objekId: $berkasTerkini->id,
                    nilaiLama: $this->metadataBerkasUntukAudit($berkasTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'regulasi_masih_dirujuk_data_aktif',
                        ...$referensiAktif,
                    ],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $referensiAktif;
            }

            $path = $berkasTerkini->mode === 'file' && is_string($berkasTerkini->path) ? $berkasTerkini->path : null;
            $nilaiLama = $this->metadataBerkasUntukAudit($berkasTerkini);

            $berkasTerkini->dihapus_oleh = $actor->id;
            $berkasTerkini->save();
            $berkasTerkini->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus',
                objekTipe: 'berkas',
                objekId: $berkasTerkini->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            if ($path !== null) {
                DB::afterCommit(fn () => $this->hapusFile([$path]));
            }

            return null;
        });

        if ($referensiAktif !== null) {
            throw ValidationException::withMessages([
                'berkas' => 'Lampiran tidak dapat dihapus karena regulasi masih dirujuk oleh Renstra atau Indikator aktif.',
            ]);
        }
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
    private function referensiAktifTerkunci(Regulasi $regulasi): array
    {
        // Mengunci semua rujukan, bukan hanya yang aktif, agar status/rujukan tidak berubah
        // setelah guard dievaluasi. Lock regulasi induk menahan insert rujukan baru via FK.
        $renstras = $regulasi->renstras()
            ->select(['id', 'is_aktif'])
            ->lockForUpdate()
            ->get();
        $indikatorKinerjas = $regulasi->indikatorKinerjas()
            ->select(['id', 'is_aktif'])
            ->lockForUpdate()
            ->get();

        return [
            'jumlah_renstra_aktif' => $renstras->where('is_aktif', true)->count(),
            'jumlah_indikator_aktif' => $indikatorKinerjas->where('is_aktif', true)->count(),
        ];
    }

    private function pastikanIzinDiizinkan(PermissionDecision $decision): void
    {
        if (! $decision->allowed) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
    }

    private function adalahDuplikasiRegulasi(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return $sqlState === '23505'
            && str_contains($exception->getMessage(), 'regulasi_jenis_nomor_tahun_unique');
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
