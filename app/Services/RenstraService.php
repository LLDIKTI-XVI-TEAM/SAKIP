<?php

namespace App\Services;

use App\Models\Berkas;
use App\Models\Pengaturan;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        } catch (AuthorizationException $exception) {
            $this->hapusFile($storedPaths);
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);

            if (! empty($data['lampiran']) && ! $uploadDecision->allowed) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.buat_ditolak',
                    objekTipe: 'renstra',
                    objekId: (string) Str::uuid(),
                    nilaiBaru: ['alasan_penolakan' => 'berkas_upload_denied'],
                    dasarIzin: $uploadDecision->toAuditBasis(),
                );
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
        // Nilai nullable dinormalkan tanpa menghapus key, sehingga izin baca FK tetap wajib.
        if (is_string($data['regulasi_id'] ?? null) && trim($data['regulasi_id']) === '') {
            $data['regulasi_id'] = null;
        }
        $storedPaths = [];
        $auditReason = is_string($data['alasan'] ?? null) ? mb_substr(trim($data['alasan']), 0, 1000) : null;
        $denialRecorded = false;
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::RENSTRA_UPDATE);

        if (! $decision->allowed) {
            $renstra->load('berkas');
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'renstra.ubah_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $this->snapshot($renstra),
                alasan: $auditReason,
                dasarIzin: $decision->toAuditBasis(),
            );

            $this->pastikanIzinDiizinkan($decision);
        }

        $authorizationDenied = false;

        try {
            $hasil = DB::transaction(function () use ($renstra, $data, &$actor, &$decision, &$authorizationDenied, &$storedPaths, &$auditReason): Renstra|array {
                $authorizationDenied = false;
                // Retry deadlock membuang berkas attempt yang telah rollback sebelum menyimpan ulang.
                $this->hapusFile($storedPaths);
                $storedPaths = [];
                // Writer akses mengunci pengguna; urutan aktor lalu master menjaga keputusan izin tetap live.
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $decision = $this->permissionResolver->resolve($actor, PermissionCodes::RENSTRA_UPDATE);
                if ($decision->allowed && array_key_exists('regulasi_id', $data)) {
                    $relatedDecision = $this->permissionResolver->resolve($actor, PermissionCodes::REGULASI_READ);
                    if (! $relatedDecision->allowed) {
                        $decision = $relatedDecision;
                    }
                }
                // Writer Regulasi mengunci induk sebelum Renstra; gunakan urutan yang sama.
                $regulasiPilihan = null;
                if ($decision->allowed && is_string($data['regulasi_id'] ?? null) && Str::isUuid($data['regulasi_id'])) {
                    $regulasiPilihan = Regulasi::query()->sharedLock()->find($data['regulasi_id']);
                }
                $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);
                $renstraTerkini->load(['berkas', 'regulasi']);
                $nilaiLama = $this->snapshot($renstraTerkini);
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

                if (! $decision->allowed) {
                    $authorizationDenied = true;
                    $penolakan = ['field' => 'authorization', 'pesan' => 'Izin efektif Anda tidak mengizinkan tindakan ini.', 'alasan_penolakan' => 'izin_tidak_efektif'];
                } elseif (! is_string($data['expected_state'] ?? null)
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
                        'alasan' => $isActive ? ['required', 'string', 'min:5', 'max:1000'] : ['nullable', 'string', 'max:1000'],
                        'nomor_kebijakan' => [$isActive ? 'required' : 'nullable', 'string', 'max:255'],
                        'tanggal_kebijakan' => [$isActive ? 'required' : 'nullable', 'date_format:Y-m-d'],
                    ], [
                        'tahun_selesai.gte' => 'Tahun selesai harus lebih besar atau sama dengan tahun mulai.',
                        'alasan.required' => 'Alasan revisi Renstra aktif wajib diisi.',
                        'nomor_kebijakan.required' => 'Nomor kebijakan/Kepmen wajib diisi.',
                        'tanggal_kebijakan.required' => 'Tanggal kebijakan/Kepmen wajib diisi.',
                        'tanggal_kebijakan.date_format' => 'Tanggal kebijakan harus berupa tanggal kalender yang valid.',
                    ]);
                    if ($validator->fails()) {
                        $penolakan = ['field' => 'renstra', 'pesan' => 'Data revisi tidak valid.', 'alasan_penolakan' => 'validasi_revisi', 'errors' => $validator->errors()->messages()];
                    } elseif (! empty($data['regulasi_id']) && ($regulasiPilihan === null
                        || (! $regulasiPilihan->aktif && $regulasiPilihan->id !== $regulasiIdLama))) {
                        $penolakan = ['field' => 'regulasi_id', 'pesan' => 'Regulasi harus aktif atau merupakan rujukan yang sudah tersimpan.', 'alasan_penolakan' => 'regulasi_tidak_tersedia'];
                    } elseif (($tahunMulai !== $renstraTerkini->tahun_mulai || $tahunSelesai !== $renstraTerkini->tahun_selesai)
                        && $renstraTerkini->jadwalTahunan()->where(fn ($query) => $query->where('tahun', '<', $tahunMulai)->orWhere('tahun', '>', $tahunSelesai))->exists()) {
                        // Semua status Jadwal tetap harus tercakup; histori tidak digeser mengikuti revisi master.
                        $penolakan = ['field' => 'tahun_mulai', 'pesan' => 'Rentang tahun harus tetap mencakup seluruh tahun Jadwal yang sudah ada.', 'alasan_penolakan' => 'tahun_jadwal_di_luar_rentang'];
                    }
                }

                if ($penolakan !== null) {
                    $this->auditLogger->catat(
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
                    $this->auditLogger->catat(
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
                    alasan: $auditReason,
                    dasarIzin: $decision->toAuditBasis(),
                );

                if (array_key_exists('regulasi_id', $data) && $renstraTerkini->regulasi_id !== $regulasiIdLama) {
                    $this->auditLogger->catat(
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

            if (is_array($hasil)) {
                $denialRecorded = true;
                if ($authorizationDenied) {
                    throw new AuthorizationException($hasil['pesan']);
                }
                // Audit denial sudah commit; melempar validasi di luar transaksi mencegah audit ikut rollback.
                throw ValidationException::withMessages($hasil['errors'] ?? [
                    $hasil['field'] => $hasil['pesan'],
                ]);
            }

            return $hasil;
        } catch (QueryException $exception) {
            $this->hapusFile($storedPaths);

            if ($this->adalahIrisanRentangAktif($exception)) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.ubah_ditolak',
                    objekTipe: 'renstra',
                    objekId: $renstra->id,
                    nilaiBaru: ['alasan_penolakan' => 'rentang_aktif_beririsan'],
                    alasan: $auditReason,
                    dasarIzin: $decision->toAuditBasis(),
                );

                throw ValidationException::withMessages([
                    'tahun_mulai' => 'Rentang tahun Renstra aktif beririsan dengan Renstra aktif lain.',
                ]);
            }

            if ($this->adalahDuplikasiKode($exception)) {
                $this->auditLogger->catat(
                    actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                    nilaiBaru: ['alasan_penolakan' => 'kode_duplikat'], alasan: $auditReason, dasarIzin: $decision->toAuditBasis(),
                );
                throw ValidationException::withMessages([
                    'kode' => 'Kode Renstra sudah terdaftar pada sistem.',
                ]);
            }

            throw $exception;
        } catch (AuthorizationException $exception) {
            $this->hapusFile($storedPaths);
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);

            if (! $authorizationDenied && ! empty($data['lampiran']) && ! $uploadDecision->allowed) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.ubah_ditolak',
                    objekTipe: 'renstra',
                    objekId: $renstra->id,
                    nilaiBaru: ['alasan_penolakan' => 'berkas_upload_denied'],
                    alasan: $auditReason,
                    dasarIzin: $uploadDecision->toAuditBasis(),
                );
            }

            throw $exception;
        } catch (ValidationException $exception) {
            $this->hapusFile($storedPaths);
            if (! $denialRecorded) {
                // Recheck unggahan dapat menolak setelah prevalidasi; domain telah rollback.
                $this->auditLogger->catat(
                    actor: $actor, tindakan: 'renstra.ubah_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                    nilaiBaru: ['alasan_penolakan' => 'validasi_mutasi', 'field_tidak_valid' => array_keys($exception->errors())],
                    alasan: $auditReason, dasarIzin: $decision->toAuditBasis(),
                );
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

        $penolakan = DB::transaction(function () use ($renstra, $alasan, $actor, $decision): ?array {
            $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);

            if ($renstraTerkini->status !== Renstra::STATUS_DRAFT) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.hapus_ditolak',
                    objekTipe: 'renstra',
                    objekId: $renstraTerkini->id,
                    nilaiLama: $this->snapshot($renstraTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'status_bukan_draft',
                        'status' => $renstraTerkini->status,
                    ],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'field' => 'renstra',
                    'pesan' => 'Hanya Renstra berstatus draft yang dapat dihapus.',
                ];
            }

            if ($renstraTerkini->sasaranStrategis()->exists() || $renstraTerkini->renstraPk()->exists() || $renstraTerkini->jadwalTahunan()->exists()) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'renstra.hapus_ditolak',
                    objekTipe: 'renstra',
                    objekId: $renstraTerkini->id,
                    nilaiLama: $this->snapshot($renstraTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'memiliki_dependensi',
                        'has_sasaran' => $renstraTerkini->sasaranStrategis()->exists(),
                        'has_pk' => $renstraTerkini->renstraPk()->exists(),
                        'has_jadwal' => $renstraTerkini->jadwalTahunan()->exists(),
                    ],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'field' => 'renstra',
                    'pesan' => 'Renstra tidak dapat dihapus karena telah memiliki data sasaran, perjanjian kinerja, atau jadwal terkait.',
                ];
            }

            $hasBerkas = $renstraTerkini->berkas()->exists();
            $berkasDecision = null;
            if ($hasBerkas) {
                $berkasDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_DELETE);
                if (! $berkasDecision->allowed) {
                    $this->auditLogger->catat(
                        actor: $actor,
                        tindakan: 'renstra.hapus_ditolak',
                        objekTipe: 'renstra',
                        objekId: $renstraTerkini->id,
                        nilaiLama: $this->snapshot($renstraTerkini),
                        nilaiBaru: [
                            'alasan_penolakan' => 'berkas_delete_denied',
                        ],
                        alasan: $alasan,
                        dasarIzin: $berkasDecision->toAuditBasis(),
                    );

                    return [
                        'field' => 'renstra',
                        'pesan' => 'Renstra tidak dapat dihapus karena Anda tidak memiliki izin untuk menghapus lampiran berkas yang terkait.',
                    ];
                }
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
                    dasarIzin: $berkasDecision ? $berkasDecision->toAuditBasis() : $decision->toAuditBasis(),
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

            return null;
        });

        if ($penolakan !== null) {
            throw ValidationException::withMessages([
                $penolakan['field'] => $penolakan['pesan'],
            ]);
        }
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

            // Batas imutabilitas: lampiran Renstra hanya dapat dihapus jika status masih draft.
            if ($renstraTerkini->status !== Renstra::STATUS_DRAFT) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'berkas.hapus_ditolak',
                    objekTipe: 'berkas',
                    objekId: $berkasTerkini->id,
                    nilaiLama: $this->metadataBerkasUntukAudit($berkasTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'renstra_bukan_draft_lampiran_imutabel',
                        'status_renstra' => $renstraTerkini->status,
                    ],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'pesan' => 'Lampiran Renstra hanya dapat dihapus pada status draft karena telah mencapai batas imutabilitas.',
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

    private function adalahIrisanRentangAktif(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23P01'
            && str_contains($exception->getMessage(), 'renstras_active_years_exclude');
    }

    private function pastikanIzinDiizinkan(PermissionDecision $decision): void
    {
        if (! $decision->allowed) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
    }
}
