<?php

namespace App\Services;

use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RenstraPkService
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Membuat data Perjanjian Kinerja beserta lampiran opsionalnya.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): RenstraPk
    {
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
            return DB::transaction(function () use ($data, $renstra, $tahun, $actor, &$storedPaths) {
                $pk = RenstraPk::create([
                    'renstra_id' => $renstra->id,
                    'tahun' => $tahun,
                    'nomor_pk' => $data['nomor_pk'],
                    'tanggal_pk' => $data['tanggal_pk'],
                    'created_by' => $actor->id,
                ]);

                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $this->simpanLampiran($pk, $data['lampiran'], $actor, $storedPaths);
                }

                $pk->load(['renstra', 'creator', 'berkas']);

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra_pk.buat',
                    objekTipe: 'renstra_pk',
                    objekId: $pk->id,
                    nilaiBaru: $this->snapshot($pk),
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
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan perubahan Perjanjian Kinerja wajib diisi.',
            ]);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($pk, $data, $alasan, $actor, &$storedPaths) {
                $pk->load(['berkas']);
                $nilaiLama = $this->snapshot($pk);

                if (array_key_exists('nomor_pk', $data)) {
                    $pk->nomor_pk = $data['nomor_pk'];
                }
                if (array_key_exists('tanggal_pk', $data)) {
                    $pk->tanggal_pk = $data['tanggal_pk'];
                }
                $pk->save();

                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $this->simpanLampiran($pk, $data['lampiran'], $actor, $storedPaths);
                }

                $pk->fresh(['renstra', 'creator', 'berkas']);
                $nilaiBaru = $this->snapshot($pk);

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra_pk.ubah',
                    objekTipe: 'renstra_pk',
                    objekId: $pk->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $nilaiBaru,
                    alasan: $alasan,
                );

                return $pk;
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
        if ($berkas->berkasable_id !== $pk->id || ! in_array($berkas->berkasable_type, ['renstra_pk', $pk->getMorphClass()], true)) {
            throw new RuntimeException('Berkas bukan merupakan lampiran dari Perjanjian Kinerja ini.');
        }

        $alasan = trim($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penghapusan lampiran wajib diisi.',
            ]);
        }

        $jadwalAktif = JadwalTahunan::where('renstra_id', $pk->renstra_id)
            ->where('tahun', $pk->tahun)
            ->where('status', 'aktif')
            ->exists();

        if ($jadwalAktif) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $this->metadataBerkasUntukAudit($berkas),
                alasan: $alasan,
            );

            throw ValidationException::withMessages([
                'berkas' => 'Lampiran Perjanjian Kinerja tidak dapat dihapus karena Jadwal Tahunan sudah aktif.',
            ]);
        }

        $path = $berkas->mode === 'file' && is_string($berkas->path) ? $berkas->path : null;
        $nilaiLama = $this->metadataBerkasUntukAudit($berkas);

        DB::transaction(function () use ($berkas, $actor, $nilaiLama, $alasan) {
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
            );
        });

        if ($path !== null) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    protected function simpanLampiran(RenstraPk $pk, array $lampiran, User $actor, array &$storedPaths): void
    {
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

            $isUploadActive = filter_var($settings->get('berkas.unggahan_aktif', 'true'), FILTER_VALIDATE_BOOLEAN);
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
                    throw new RuntimeException('Lampiran file tidak valid.');
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

                $storedPaths[] = $path;
                $attributes += [
                    'nama_asli' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime' => $file->getClientMimeType() ?: $file->getMimeType(),
                    'ukuran_bytes' => $file->getSize(),
                ];
            } elseif ($mode === 'tautan') {
                $attributes += [
                    'nama_asli' => $item['nama_asli'] ?? ($item['nama'] ?? 'Tautan Dokumen PK'),
                    'tautan' => $item['tautan'] ?? ($item['url'] ?? null),
                ];
            } elseif ($mode === 'teks') {
                $attributes += [
                    'nama_asli' => $item['nama_asli'] ?? ($item['nama'] ?? 'Catatan Dokumen PK'),
                    'isi_teks' => $item['isi_teks'] ?? ($item['teks'] ?? null),
                ];
            }

            /** @var Berkas $berkas */
            $berkas = $pk->berkas()->create($attributes);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.unggah',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiBaru: $this->metadataBerkasUntukAudit($berkas),
            );
        }
    }

    /**
     * @param  list<string>  $paths
     */
    protected function hapusFile(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
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
        return [
            'id' => $berkas->id,
            'mode' => $berkas->mode,
            'nama_asli' => $berkas->nama_asli,
            'path' => $berkas->path,
            'tautan' => $berkas->tautan,
            'isi_teks' => $berkas->isi_teks,
            'ukuran_bytes' => $berkas->ukuran_bytes,
            'mime' => $berkas->mime,
        ];
    }

    protected function adalahDuplikasiPk(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return $sqlState === '23505'
            && (str_contains($exception->getMessage(), 'renstra_pk_renstra_id_tahun_unique') || str_contains($exception->getMessage(), 'renstra_pk'));
    }
}
