<?php

namespace App\Actions\Renstra;

use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Renstra\RenstraAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateRenstraAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RenstraAttachments $attachments,
    ) {}

    /**
     * Pembaruan in place memeriksa pasangan tahun hasil merge sesudah induk terkunci.
     *
     * @param array{nama: string, tahun_mulai: int|numeric-string, kode?: string|null,
     *     tahun_selesai?: int|numeric-string, tahun_akhir?: int|numeric-string,
     *     deskripsi?: string|null, keterangan?: string|null, dasar_hukum?: string|null,
     *     regulasi_id?: string|null, alasan?: string|null,
     *     lampiran?: array<array-key, array{mode: 'file'|'tautan'|'teks', file?: UploadedFile, tautan?: string, isi_teks?: string}>
     * } $data
     */
    public function handle(User $actor, Renstra $renstra, array $data): Renstra
    {
        $storedPaths = [];
        $decision = null;
        try {
            $result = DB::transaction(function () use ($actor, $renstra, $data, &$storedPaths, &$decision): Renstra|PermissionDecision|array {
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                $permissions = [PermissionCodes::RENSTRA_UPDATE];
                if (! empty($data['lampiran'])) {
                    $permissions[] = PermissionCodes::BERKAS_UPLOAD;
                }
                if (array_key_exists('regulasi_id', $data)) {
                    $permissions[] = PermissionCodes::REGULASI_READ;
                }
                Permission::whereIn('kode', $permissions)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, PermissionCodes::RENSTRA_UPDATE);
                $uploadDecision = null;
                $denied = $decision->allowed ? null : $decision;
                $denialReason = null;
                if ($denied === null && ! empty($data['lampiran'])) {
                    $uploadDecision = $this->resolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
                    if (! $uploadDecision->allowed) {
                        $denied = $uploadDecision;
                        $denialReason = 'berkas_upload_denied';
                    }
                }
                if ($denied === null && array_key_exists('regulasi_id', $data)) {
                    $referenceDecision = $this->resolver->resolve($actor, PermissionCodes::REGULASI_READ);
                    if (! $referenceDecision->allowed) {
                        $denied = $referenceDecision;
                        $denialReason = 'regulasi_read_denied';
                    }
                }
                if ($denied !== null) {
                    if ($denialReason === null) {
                        $renstra->load(['berkas', 'regulasi']);
                    }
                    $this->audit->catat(
                        actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                        nilaiLama: $denialReason === null ? $this->attachments->snapshot($renstra) : null,
                        nilaiBaru: $denialReason === null ? null : ['alasan_penolakan' => $denialReason],
                        alasan: AuditReason::sanitize($data['alasan'] ?? null), dasarIzin: $denied->toAuditBasis(),
                    );

                    return $denied;
                }

                // Urutan Regulasi → Renstra mengikuti penghapusan Regulasi dan menjaga status target hingga commit.
                $regulasiTujuan = isset($data['regulasi_id'])
                    ? Regulasi::whereKey($data['regulasi_id'])->sharedLock()->first()
                    : null;
                $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);
                // Pengecualian rujukan nonaktif mengikuti FK terkunci, bukan model dari route binding.
                if (isset($data['regulasi_id']) && ($regulasiTujuan === null
                    || (! $regulasiTujuan->aktif && $regulasiTujuan->id !== $renstraTerkini->regulasi_id))) {
                    throw ValidationException::withMessages(['regulasi_id' => 'Dasar aturan regulasi yang dipilih tidak ditemukan.']);
                }
                $renstraTerkini->load(['berkas', 'regulasi']);
                $nilaiLama = $this->attachments->snapshot($renstraTerkini);
                $regulasiIdLama = $renstraTerkini->regulasi_id;

                $tahunMulai = isset($data['tahun_mulai']) ? (int) $data['tahun_mulai'] : $renstraTerkini->tahun_mulai;
                $tahunSelesai = isset($data['tahun_selesai'])
                    ? (int) $data['tahun_selesai']
                    : (isset($data['tahun_akhir']) ? (int) $data['tahun_akhir'] : $renstraTerkini->tahun_selesai);

                if ($tahunSelesai < $tahunMulai) {
                    throw ValidationException::withMessages([
                        'tahun_akhir' => 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.',
                        'tahun_selesai' => 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.',
                    ]);
                }

                $deskripsi = array_key_exists('deskripsi', $data)
                    ? $data['deskripsi']
                    : (array_key_exists('keterangan', $data) ? $data['keterangan'] : $renstraTerkini->deskripsi);

                $dasarHukum = array_key_exists('dasar_hukum', $data) ? $data['dasar_hukum'] : $renstraTerkini->dasar_hukum;
                $penolakan = null;

                if ($renstraTerkini->status === Renstra::STATUS_DIARSIPKAN) {
                    $penolakan = [
                        'field' => 'renstra',
                        'pesan' => 'Renstra yang telah diarsipkan bersifat permanen dan tidak dapat diubah.',
                        'alasan_penolakan' => 'status_diarsipkan',
                    ];
                } elseif (! empty($data['lampiran']) && $renstraTerkini->status !== Renstra::STATUS_DRAFT) {
                    $penolakan = [
                        'field' => 'lampiran',
                        'pesan' => 'Lampiran baru hanya dapat ditambahkan pada Renstra berstatus draft.',
                        'alasan_penolakan' => 'renstra_bukan_draft_lampiran_imutabel',
                    ];
                } elseif ($renstraTerkini->status === Renstra::STATUS_AKTIF && trim((string) $dasarHukum) === '') {
                    $penolakan = [
                        'field' => 'dasar_hukum',
                        'pesan' => 'Dasar hukum Renstra aktif wajib terisi.',
                        'alasan_penolakan' => 'dasar_hukum_kosong',
                    ];
                } elseif ($renstraTerkini->status === Renstra::STATUS_AKTIF && Renstra::query()
                    ->whereKeyNot($renstraTerkini->id)
                    ->where('status', Renstra::STATUS_AKTIF)
                    ->where('tahun_mulai', '<=', $tahunSelesai)
                    ->where('tahun_selesai', '>=', $tahunMulai)
                    ->exists()) {
                    $penolakan = [
                        'field' => 'tahun_mulai',
                        'pesan' => 'Rentang tahun Renstra aktif beririsan dengan Renstra aktif lain.',
                        'alasan_penolakan' => 'rentang_aktif_beririsan',
                    ];
                }

                if ($penolakan !== null) {
                    $this->audit->catat(
                        actor: $actor,
                        tindakan: 'renstra.ubah_ditolak',
                        objekTipe: 'renstra',
                        objekId: $renstraTerkini->id,
                        nilaiLama: $nilaiLama,
                        nilaiBaru: ['alasan_penolakan' => $penolakan['alasan_penolakan']],
                        alasan: AuditReason::sanitize($data['alasan'] ?? null),
                        dasarIzin: $decision->toAuditBasis(),
                    );

                    return $penolakan;
                }

                $updateData = [
                    'nama' => $data['nama'] ?? $renstraTerkini->nama,
                    'tahun_mulai' => $tahunMulai,
                    'tahun_selesai' => $tahunSelesai,
                    'deskripsi' => $deskripsi,
                    'dasar_hukum' => $dasarHukum,
                    'regulasi_id' => array_key_exists('regulasi_id', $data) ? $data['regulasi_id'] : $renstraTerkini->regulasi_id,
                ];

                if (! empty($data['kode'])) {
                    $updateData['kode'] = $data['kode'];
                }

                $renstraTerkini->fill($updateData);
                $renstraTerkini->save();

                if ($uploadDecision !== null) {
                    $this->attachments->simpanLampiran(
                        $renstraTerkini,
                        $data['lampiran'],
                        $actor,
                        $uploadDecision,
                        $storedPaths,
                    );
                }

                $renstraTerkini->load(['berkas', 'regulasi']);
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'renstra.ubah',
                    objekTipe: 'renstra',
                    objekId: $renstraTerkini->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $this->attachments->snapshot($renstraTerkini),
                    alasan: $data['alasan'] ?? null,
                    dasarIzin: $decision->toAuditBasis(),
                );

                if (array_key_exists('regulasi_id', $data) && $renstraTerkini->regulasi_id !== $regulasiIdLama) {
                    $this->audit->catat(
                        actor: $actor,
                        tindakan: 'renstra.ubah_regulasi',
                        objekTipe: 'renstra',
                        objekId: $renstraTerkini->id,
                        nilaiLama: ['regulasi_id' => $regulasiIdLama],
                        nilaiBaru: ['regulasi_id' => $renstraTerkini->regulasi_id],
                        alasan: $data['alasan'] ?? null,
                        dasarIzin: $decision->toAuditBasis(),
                    );
                }

                return $renstraTerkini;
            });
        } catch (Throwable $exception) {
            $this->attachments->hapusFile($storedPaths, 'renstra.kompensasi_unggahan');
            if ($exception instanceof QueryException) {
                if (($exception->errorInfo[0] ?? null) === '23P01' && str_contains($exception->getMessage(), 'renstras_active_years_exclude')) {
                    $this->audit->catat(
                        actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                        nilaiBaru: ['alasan_penolakan' => 'rentang_aktif_beririsan'],
                        alasan: AuditReason::sanitize($data['alasan'] ?? null), dasarIzin: $decision?->toAuditBasis(),
                    );
                    throw ValidationException::withMessages(['tahun_mulai' => 'Rentang tahun Renstra aktif beririsan dengan Renstra aktif lain.']);
                }
                if (str_contains($exception->getMessage(), 'renstras_kode_unique') || str_contains($exception->getMessage(), '23505')) {
                    throw ValidationException::withMessages(['kode' => 'Kode Renstra sudah terdaftar pada sistem.']);
                }
            }
            throw $exception;
        }
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if (is_array($result)) {
            throw ValidationException::withMessages([$result['field'] => $result['pesan']]);
        }

        return $result;
    }
}
