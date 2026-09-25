<?php

namespace App\Services;

use App\Models\Berkas;
use App\Models\Pengaturan;
use App\Models\Renstra;
use App\Models\User;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RenstraService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Renstra
    {
        $storedPaths = [];
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::RENSTRA_CREATE);
        $this->pastikanIzinDiizinkan($decision);

        $this->validasiRentangTahun($data);

        try {
            return DB::transaction(function () use ($data, $actor, $decision, &$storedPaths): Renstra {
                $tahunMulai = (int) $data['tahun_mulai'];
                $tahunSelesai = (int) ($data['tahun_selesai'] ?? $data['tahun_akhir'] ?? $tahunMulai);

                $kode = $data['kode'] ?? 'RENSTRA-'.$tahunMulai.'-'.$tahunSelesai;

                $deskripsi = $data['deskripsi'] ?? $data['keterangan'] ?? null;

                $renstra = Renstra::query()->create([
                    'kode' => $kode,
                    'nama' => $data['nama'],
                    'tahun_mulai' => $tahunMulai,
                    'tahun_selesai' => $tahunSelesai,
                    'deskripsi' => $deskripsi,
                    'dasar_hukum' => $data['dasar_hukum'] ?? null,
                    'regulasi_id' => $data['regulasi_id'] ?? null,
                    'status' => Renstra::STATUS_DRAFT,
                    'is_aktif' => false,
                    'created_by' => $actor->id,
                ]);

                $this->simpanLampiran(
                    $renstra,
                    $data['lampiran'] ?? [],
                    $actor,
                    $storedPaths,
                );

                $renstra->load(['berkas', 'regulasi']);
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.buat',
                    objekTipe: 'renstra',
                    objekId: $renstra->id,
                    nilaiBaru: $this->snapshot($renstra),
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $renstra;
            });
        } catch (QueryException $exception) {
            $this->hapusFile($storedPaths);

            if ($this->adalahDuplikasiKode($exception)) {
                throw ValidationException::withMessages([
                    'kode' => 'Kode Renstra sudah terdaftar pada sistem.',
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
    public function update(Renstra $renstra, array $data, User $actor): Renstra
    {
        $storedPaths = [];
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::RENSTRA_UPDATE);

        if (! $decision->allowed) {
            $renstra->load('berkas');
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'renstra.ubah_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $this->snapshot($renstra),
                alasan: $data['alasan'] ?? null,
                dasarIzin: $decision->toAuditBasis(),
            );

            $this->pastikanIzinDiizinkan($decision);
        }

        $this->validasiRentangTahun($data);

        try {
            return DB::transaction(function () use ($renstra, $data, $actor, $decision, &$storedPaths): Renstra {
                $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);
                $renstraTerkini->load(['berkas', 'regulasi']);
                $nilaiLama = $this->snapshot($renstraTerkini);

                $tahunMulai = isset($data['tahun_mulai']) ? (int) $data['tahun_mulai'] : $renstraTerkini->tahun_mulai;
                $tahunSelesai = isset($data['tahun_selesai'])
                    ? (int) $data['tahun_selesai']
                    : (isset($data['tahun_akhir']) ? (int) $data['tahun_akhir'] : $renstraTerkini->tahun_selesai);

                $deskripsi = array_key_exists('deskripsi', $data)
                    ? $data['deskripsi']
                    : (array_key_exists('keterangan', $data) ? $data['keterangan'] : $renstraTerkini->deskripsi);

                $updateData = [
                    'nama' => $data['nama'] ?? $renstraTerkini->nama,
                    'tahun_mulai' => $tahunMulai,
                    'tahun_selesai' => $tahunSelesai,
                    'deskripsi' => $deskripsi,
                    'dasar_hukum' => array_key_exists('dasar_hukum', $data) ? $data['dasar_hukum'] : $renstraTerkini->dasar_hukum,
                    'regulasi_id' => array_key_exists('regulasi_id', $data) ? $data['regulasi_id'] : $renstraTerkini->regulasi_id,
                ];

                if (! empty($data['kode'])) {
                    $updateData['kode'] = $data['kode'];
                }

                $renstraTerkini->fill($updateData);
                $renstraTerkini->save();

                if (! empty($data['lampiran'])) {
                    $this->simpanLampiran(
                        $renstraTerkini,
                        $data['lampiran'],
                        $actor,
                        $storedPaths,
                    );
                }

                $renstraTerkini->load(['berkas', 'regulasi']);
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.ubah',
                    objekTipe: 'renstra',
                    objekId: $renstraTerkini->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $this->snapshot($renstraTerkini),
                    alasan: $data['alasan'] ?? null,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $renstraTerkini;
            });
        } catch (QueryException $exception) {
            $this->hapusFile($storedPaths);

            if ($this->adalahDuplikasiKode($exception)) {
                throw ValidationException::withMessages([
                    'kode' => 'Kode Renstra sudah terdaftar pada sistem.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->hapusFile($storedPaths);

            throw $exception;
        }
    }

    public function delete(Renstra $renstra, string $alasan, User $actor): void
    {
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::RENSTRA_DELETE);

        if (! $decision->allowed) {
            $renstra->load('berkas');
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'renstra.hapus_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $this->snapshot($renstra),
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            $this->pastikanIzinDiizinkan($decision);
        }

        DB::transaction(function () use ($renstra, $alasan, $actor, $decision): void {
            $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);

            if ($renstraTerkini->status !== Renstra::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'renstra' => 'Hanya Renstra berstatus draft yang dapat dihapus.',
                ]);
            }

            if ($renstraTerkini->sasaranStrategis()->exists() || $renstraTerkini->renstraPk()->exists() || $renstraTerkini->jadwalTahunan()->exists()) {
                throw ValidationException::withMessages([
                    'renstra' => 'Renstra tidak dapat dihapus karena telah memiliki data sasaran, perjanjian kinerja, atau jadwal terkait.',
                ]);
            }

            $renstraTerkini->load(['berkas', 'regulasi']);
            $nilaiLama = $this->snapshot($renstraTerkini);

            $paths = [];
            foreach ($renstraTerkini->berkas as $berkas) {
                if ($berkas->mode === 'file' && is_string($berkas->path)) {
                    $paths[] = $berkas->path;
                }

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

            $renstraTerkini->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'renstra.hapus',
                objekTipe: 'renstra',
                objekId: $renstraTerkini->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            if (! empty($paths)) {
                DB::afterCommit(fn () => $this->hapusFile($paths));
            }
        });
    }

    public function deleteAttachment(Renstra $renstra, Berkas $berkas, string $alasan, User $actor): void
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

        $penolakan = DB::transaction(function () use ($renstra, $berkas, $alasan, $actor, $decision): ?array {
            $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);
            $berkasTerkini = Berkas::query()->lockForUpdate()->findOrFail($berkas->id);

            // Batas imutabilitas: lampiran Renstra tidak dapat dihapus setelah Renstra berstatus aktif.
            if ($renstraTerkini->status === Renstra::STATUS_AKTIF || $renstraTerkini->is_aktif) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'berkas.hapus_ditolak',
                    objekTipe: 'berkas',
                    objekId: $berkasTerkini->id,
                    nilaiLama: $this->metadataBerkasUntukAudit($berkasTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'renstra_aktif_lampiran_imutable',
                        'status_renstra' => $renstraTerkini->status,
                    ],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'pesan' => 'Lampiran Renstra yang berstatus aktif tidak dapat dihapus karena telah mencapai batas imutabilitas.',
                ];
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

        if ($penolakan !== null) {
            throw ValidationException::withMessages([
                'berkas' => $penolakan['pesan'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validasiRentangTahun(array $data): void
    {
        if (! isset($data['tahun_mulai'])) {
            return;
        }

        $tahunMulai = (int) $data['tahun_mulai'];
        $tahunSelesai = isset($data['tahun_selesai'])
            ? (int) $data['tahun_selesai']
            : (isset($data['tahun_akhir']) ? (int) $data['tahun_akhir'] : null);

        if ($tahunSelesai !== null && $tahunSelesai < $tahunMulai) {
            throw ValidationException::withMessages([
                'tahun_akhir' => 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.',
                'tahun_selesai' => 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lampiran
     * @param  list<string>  $storedPaths
     */
    private function simpanLampiran(
        Renstra $renstra,
        array $lampiran,
        User $actor,
        array &$storedPaths,
    ): void {
        if (empty($lampiran)) {
            return;
        }

        $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
        $this->pastikanIzinDiizinkan($uploadDecision);

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

                if (! $isUploadActive) {
                    throw ValidationException::withMessages([
                        'lampiran' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.',
                    ]);
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
    private function snapshot(Renstra $renstra): array
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
    private function metadataBerkasUntukAudit(Berkas $berkas): array
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
                'path' => $berkas->path,
            ];
        } elseif ($berkas->mode === 'tautan') {
            $meta['tautan'] = $berkas->tautan;
        } else {
            $meta['panjang_teks'] = mb_strlen((string) $berkas->isi_teks);
        }

        return $meta;
    }

    /**
     * @param  list<string>  $paths
     */
    private function hapusFile(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    private function adalahDuplikasiKode(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'renstras_kode_unique')
            || str_contains($exception->getMessage(), '23505');
    }

    private function pastikanIzinDiizinkan(PermissionDecision $decision): void
    {
        if (! $decision->allowed) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
    }
}
