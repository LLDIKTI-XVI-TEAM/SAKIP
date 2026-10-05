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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateRenstraAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RenstraAttachments $attachments,
    ) {}

    /** Pembaruan in place memeriksa state, rujukan resmi, dan rentang setelah induk terkunci.
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Renstra $renstra, array $data): Renstra
    {
        // Pertahankan key nullable agar perubahan FK tetap memerlukan izin baca Regulasi.
        if (is_string($data['regulasi_id'] ?? null) && trim($data['regulasi_id']) === '') {
            $data['regulasi_id'] = null;
        }
        $auditReason = mb_substr(trim(AuditReason::sanitize($data['alasan'] ?? null)), 0, 1000) ?: null;
        $storedPaths = [];
        $decision = null;
        try {
            $result = DB::transaction(function () use ($actor, $renstra, $data, &$storedPaths, &$decision, &$auditReason): Renstra|PermissionDecision|array {
                // Buang unggahan attempt yang rollback sebelum retry deadlock.
                $this->attachments->hapusFile($storedPaths, 'renstra.kompensasi_unggahan');
                $storedPaths = [];
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
                        alasan: $auditReason, dasarIzin: $denied->toAuditBasis(),
                    );

                    return $denied;
                }

                // Urutan Regulasi → Renstra mengikuti penghapusan Regulasi dan menjaga status target hingga commit.
                $regulasiTujuan = is_string($data['regulasi_id'] ?? null) && Str::isUuid($data['regulasi_id'])
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

                $deskripsi = array_key_exists('deskripsi', $data)
                    ? $data['deskripsi']
                    : (array_key_exists('keterangan', $data) ? $data['keterangan'] : $renstraTerkini->deskripsi);

                $dasarHukum = array_key_exists('dasar_hukum', $data) ? $data['dasar_hukum'] : $renstraTerkini->dasar_hukum;
                $penolakan = null;

                if (! is_string($data['expected_state'] ?? null)
                    || ! hash_equals($renstraTerkini->stateToken(), $data['expected_state'])) {
                    $penolakan = ['field' => 'expected_state', 'pesan' => 'Renstra telah berubah. Muat data terbaru sebelum mengirim perubahan kembali.', 'alasan_penolakan' => 'state_berubah'];
                } elseif ($renstraTerkini->status === Renstra::STATUS_NONAKTIF) {
                    $penolakan = ['field' => 'renstra', 'pesan' => 'Renstra nonaktif hanya dapat dibaca dan diarsipkan.', 'alasan_penolakan' => 'status_nonaktif'];
                } elseif ($renstraTerkini->status === Renstra::STATUS_DIARSIPKAN) {
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
                } elseif ($renstraTerkini->status === Renstra::STATUS_AKTIF && (! is_string($dasarHukum) || trim($dasarHukum) === '')) {
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

                if ($penolakan === null) {
                    // Validasi hasil gabungan, termasuk ketika request hanya mengganti satu batas tahun.
                    $isActive = $renstraTerkini->status === Renstra::STATUS_AKTIF;
                    $validator = Validator::make(array_replace($data, [
                        'tahun_mulai' => $data['tahun_mulai'] ?? $renstraTerkini->tahun_mulai,
                        'tahun_selesai' => $data['tahun_selesai'] ?? $data['tahun_akhir'] ?? $renstraTerkini->tahun_selesai,
                        'deskripsi' => $deskripsi,
                        'dasar_hukum' => $dasarHukum,
                    ]), [
                        'nama' => ['sometimes', 'required', 'string', 'max:255'],
                        'kode' => ['sometimes', 'nullable', 'string', 'max:50'],
                        'tahun_mulai' => ['required', 'integer', 'between:2000,2100'],
                        'tahun_selesai' => ['required', 'integer', 'between:2000,2100', 'gte:tahun_mulai'],
                        'deskripsi' => ['nullable', 'string', 'max:5000'],
                        'dasar_hukum' => ['nullable', 'string', 'max:5000'],
                        'regulasi_id' => ['nullable', 'uuid'],
                        'alasan' => [$isActive ? 'required' : 'nullable', 'string', ...($isActive ? ['min:5'] : []), 'max:1000', AuditReason::validate(...)],
                        'nomor_kebijakan' => [$isActive ? 'required' : 'nullable', 'string', 'max:255', AuditReason::validate(...)],
                        'tanggal_kebijakan' => [$isActive ? 'required' : 'nullable', 'date_format:Y-m-d'],
                    ], [
                        'tahun_selesai.gte' => 'Tahun selesai harus lebih besar atau sama dengan tahun mulai.',
                        'alasan.required' => 'Alasan revisi Renstra aktif wajib diisi.',
                        'nomor_kebijakan.required' => 'Nomor kebijakan/Kepmen wajib diisi.',
                        'tanggal_kebijakan.required' => 'Tanggal kebijakan/Kepmen wajib diisi.',
                        'tanggal_kebijakan.date_format' => 'Tanggal kebijakan harus berupa tanggal kalender yang valid.',
                    ]);
                    if ($validator->fails()) {
                        $errors = $validator->errors()->messages();
                        if (isset($errors['tahun_selesai'])) {
                            $errors['tahun_akhir'] = $errors['tahun_selesai'];
                        }
                        $penolakan = ['field' => 'renstra', 'pesan' => 'Data revisi tidak valid.', 'alasan_penolakan' => 'validasi_revisi', 'errors' => $errors];
                    } elseif (($tahunMulai !== $renstraTerkini->tahun_mulai || $tahunSelesai !== $renstraTerkini->tahun_selesai)
                        && $renstraTerkini->jadwalTahunan()->where(fn ($query) => $query->where('tahun', '<', $tahunMulai)->orWhere('tahun', '>', $tahunSelesai))->exists()) {
                        // Semua status Jadwal tetap harus tercakup; histori tidak digeser mengikuti revisi master.
                        $penolakan = ['field' => 'tahun_mulai', 'pesan' => 'Rentang tahun harus tetap mencakup seluruh tahun Jadwal yang sudah ada.', 'alasan_penolakan' => 'tahun_jadwal_di_luar_rentang'];
                    } elseif (($tahunMulai !== $renstraTerkini->tahun_mulai || $tahunSelesai !== $renstraTerkini->tahun_selesai)
                        && $renstraTerkini->renstraPk()->where(fn ($query) => $query->where('tahun', '<', $tahunMulai)->orWhere('tahun', '>', $tahunSelesai))->exists()) {
                        // PK dapat mendahului Jadwal; shared lock writer PK diserialkan dengan lock master ini.
                        $penolakan = ['field' => 'tahun_mulai', 'pesan' => 'Rentang tahun harus tetap mencakup seluruh tahun Perjanjian Kinerja yang sudah ada.', 'alasan_penolakan' => 'tahun_pk_di_luar_rentang'];
                    } elseif (($tahunMulai !== $renstraTerkini->tahun_mulai || $tahunSelesai !== $renstraTerkini->tahun_selesai)
                        && DB::table('target_kinerjas as target')
                            ->join('indikator_kinerjas as indikator', 'indikator.id', '=', 'target.indikator_kinerja_id')
                            ->join('sasaran_strategis as sasaran', 'sasaran.id', '=', 'indikator.sasaran_strategis_id')
                            ->where('sasaran.renstra_id', $renstraTerkini->id)
                            ->where(fn ($query) => $query->where('target.tahun', '<', $tahunMulai)->orWhere('target.tahun', '>', $tahunSelesai))->exists()) {
                        // EXISTS tanpa lock anak menjaga urutan writer target indikator → sasaran → shared Renstra.
                        // Baris yang dikosongkan tetap histori; nilainya bukan syarat guard rentang.
                        $penolakan = ['field' => 'tahun_mulai', 'pesan' => 'Rentang tahun harus tetap mencakup seluruh tahun target yang sudah ada.', 'alasan_penolakan' => 'tahun_target_di_luar_rentang'];
                    } elseif ($tahunSelesai !== $renstraTerkini->tahun_selesai
                        && DB::table('indikator_kinerjas as indikator')
                            ->join('sasaran_strategis as sasaran', 'sasaran.id', '=', 'indikator.sasaran_strategis_id')
                            ->where('sasaran.renstra_id', $renstraTerkini->id)
                            ->where('indikator.tahun_mulai_berlaku', '>', $tahunSelesai)->exists()) {
                        // Semua indikator tetap memiliki tahun yang tercakup, termasuk yang diarsipkan dan belum memiliki target.
                        $penolakan = ['field' => 'tahun_selesai', 'pesan' => 'Tahun selesai tidak boleh mendahului tahun mulai berlaku indikator. Pilih tahun selesai yang mencakup seluruh indikator.', 'alasan_penolakan' => 'tahun_selesai_sebelum_indikator_berlaku'];
                    }
                }

                if ($penolakan !== null) {
                    $this->audit->catat(
                        actor: $actor,
                        tindakan: 'renstra.ubah_ditolak',
                        objekTipe: 'renstra',
                        objekId: $renstraTerkini->id,
                        nilaiLama: $nilaiLama,
                        nilaiBaru: ['alasan_penolakan' => $penolakan['alasan_penolakan']],
                        alasan: $auditReason,
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

                if ($renstraTerkini->status === Renstra::STATUS_AKTIF) {
                    // Rujukan resmi hanya metadata audit; tidak membentuk versi atau kolom master baru.
                    $auditReason = trim($data['alasan'])."\nRujukan: ".trim($data['nomor_kebijakan']).' tanggal '.$data['tanggal_kebijakan'];
                }

                $renstraTerkini->fill($updateData);
                if (! $renstraTerkini->isDirty() && empty($data['lampiran'])) {
                    $this->audit->catat(
                        actor: $actor,
                        tindakan: 'renstra.ubah_tanpa_perubahan',
                        objekTipe: 'renstra',
                        objekId: $renstraTerkini->id,
                        nilaiLama: $nilaiLama,
                        nilaiBaru: $nilaiLama + ['hasil' => 'tidak_berubah'],
                        alasan: $auditReason ?? 'Tidak ada perubahan master yang disimpan.',
                        dasarIzin: $decision->toAuditBasis(),
                    );

                    return $renstraTerkini;
                }
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
                    alasan: $auditReason,
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
                        alasan: $auditReason,
                        dasarIzin: $decision->toAuditBasis(),
                    );
                }

                return $renstraTerkini;
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->attachments->hapusFile($storedPaths, 'renstra.kompensasi_unggahan');
            if ($exception instanceof ValidationException) {
                // Penolakan dari recheck rujukan/unggahan diaudit setelah domain rollback.
                $this->audit->catat(
                    actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                    nilaiBaru: ['alasan_penolakan' => 'validasi_mutasi', 'field_tidak_valid' => array_keys($exception->errors())],
                    alasan: $auditReason, dasarIzin: $decision?->toAuditBasis(),
                );
            }
            if ($exception instanceof QueryException) {
                if (($exception->errorInfo[0] ?? null) === '23P01' && str_contains($exception->getMessage(), 'renstras_active_years_exclude')) {
                    $this->audit->catat(
                        actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                        nilaiBaru: ['alasan_penolakan' => 'rentang_aktif_beririsan'],
                        alasan: $auditReason, dasarIzin: $decision?->toAuditBasis(),
                    );
                    throw ValidationException::withMessages(['tahun_mulai' => 'Rentang tahun Renstra aktif beririsan dengan Renstra aktif lain.']);
                }
                if (str_contains($exception->getMessage(), 'renstras_kode_unique') || str_contains($exception->getMessage(), '23505')) {
                    $this->audit->catat(
                        actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                        nilaiBaru: ['alasan_penolakan' => 'kode_duplikat'], alasan: $auditReason, dasarIzin: $decision?->toAuditBasis(),
                    );
                    throw ValidationException::withMessages(['kode' => 'Kode Renstra sudah terdaftar pada sistem.']);
                }
            }
            throw $exception;
        }
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if (is_array($result)) {
            throw ValidationException::withMessages($result['errors'] ?? [$result['field'] => $result['pesan']]);
        }

        return $result;
    }
}
