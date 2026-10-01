<?php

namespace App\Actions\JenisBerkas;

use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\JenisBerkas\JenisBerkasWarnings;
use App\Support\AuditReason;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateJenisBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $auditLogger,
        private readonly JenisBerkasWarnings $warnings,
    ) {}

    /**
     * Data hasil validasi; key opsional tetap dibedakan dari nilai null.
     *
     * @param array{
     *     nama: string,
     *     tahap: 'pengukuran',
     *     indikator_id?: string|null,
     *     wajib?: bool|0|1|'0'|'1',
     *     keterangan?: string|null,
     *     izinkan_file?: bool|0|1|'0'|'1',
     *     izinkan_tautan?: bool|0|1|'0'|'1',
     *     izinkan_teks?: bool|0|1|'0'|'1',
     *     semua_mode_wajib?: bool|0|1|'0'|'1',
     *     urutan?: int|numeric-string,
     *     format_diizinkan?: string|null,
     *     ukuran_maks_kb?: int|numeric-string|null,
     *     aktif?: bool|0|1|'0'|'1',
     *     alasan: string,
     *     expected_updated_at: string,
     * } $data
     */
    public function handle(User $actor, string $id, array $data): ?string
    {
        $isUnggahanAktif = true;
        $formatWarning = null;
        $denied = DB::transaction(function () use (&$actor, $id, $data, &$isUnggahanAktif, &$formatWarning): ?array {
            // Selaras dengan writer akses: pengguna, role aktif, lalu permission berurutan UUID.
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::whereIn('kode', ['jenis_berkas:update', 'pengaturan:update'])->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:update');
            if (! $decision['allowed']) {
                return $decision;
            }

            $settingsDecision = $this->resolver->decide($actor, 'pengaturan:update');

            // Serialisasikan pembacaan saklar dengan sharedLock di dalam transaksi
            // untuk mencegah race condition saat admin menonaktifkan unggahan global
            $saklarRow = Pengaturan::where('kunci', 'berkas.unggahan_aktif')->sharedLock()->first();
            $isUnggahanAktif = filter_var($saklarRow?->nilai ?? true, FILTER_VALIDATE_BOOLEAN);

            $jb = JenisBerkas::where('id', $id)->lockForUpdate()->firstOrFail();

            $expectedUpdatedAt = (string) $data['expected_updated_at'];
            $currentTimestamp = $jb->updated_at ?? $jb->created_at;
            try {
                $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                $currentIso = $currentTimestamp !== null ? $currentTimestamp->toISOString() : null;
                if ($currentIso === null || $currentIso !== $expectedIso) {
                    throw ValidationException::withMessages([
                        'konflik' => 'Data persyaratan telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                    ]);
                }
            } catch (\Exception $e) {
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                throw ValidationException::withMessages([
                    'expected_updated_at' => 'Format timestamp versi tidak valid.',
                ]);
            }

            $nilaiLama = $jb->toArray();
            $alasan = $data['alasan'];
            unset($data['alasan'], $data['expected_updated_at']);

            if (! $settingsDecision['allowed']) {
                unset($data['format_diizinkan'], $data['ukuran_maks_kb']);
            }

            if (array_key_exists('format_diizinkan', $data) && $data['format_diizinkan'] !== $nilaiLama['format_diizinkan']) {
                $formatWarning = $this->warnings->forFormatChange($jb, $data['format_diizinkan']);
            }

            $jb->fill($data);

            if (! $jb->isDirty()) {
                return null;
            }

            $jb->save();
            $nilaiBaru = $jb->fresh()->toArray();

            $isFormatOrSizeChanged = ($nilaiBaru['format_diizinkan'] !== $nilaiLama['format_diizinkan'])
                || ($nilaiBaru['ukuran_maks_kb'] !== $nilaiLama['ukuran_maks_kb']);

            $dasarIzin = $decision;
            if ($isFormatOrSizeChanged && $settingsDecision['allowed']) {
                $dasarIzin = [
                    'jenis_berkas' => $decision,
                    'pengaturan' => $settingsDecision,
                ];
            }

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.ubah',
                objekTipe: 'jenis_berkas',
                objekId: $jb->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $nilaiBaru,
                alasan: $alasan,
                dasarIzin: $dasarIzin
            );

            // Lifecycle penanda saat saklar unggahan nonaktif (§18.7 & Plan Pengembangan §10.6)
            // Menggunakan relasi eksplisit penanda_audit_id agar urutan total lifecycle
            // tidak bergantung pada keunikan timestamp (bebas race condition saat frozen clock / rapid toggle)
            $activeMarking = DB::table('audit_log as a')
                ->where('a.objek_tipe', 'jenis_berkas')
                ->where('a.objek_id', $jb->id)
                ->where('a.tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('audit_log as a2')
                        ->whereColumn('a2.objek_id', 'a.objek_id')
                        ->whereColumn('a2.objek_tipe', 'a.objek_tipe')
                        ->where('a2.tindakan', 'berkas.cabut_tidak_dapat_dipenuhi')
                        ->where(function ($sub) {
                            $sub->whereRaw("(a2.nilai_lama->>'penanda_audit_id') = a.id::text")
                                ->orWhereRaw("(a2.nilai_baru->>'penanda_audit_id') = a.id::text")
                                ->orWhere(function ($fallback) {
                                    $fallback->whereNull(DB::raw("a2.nilai_lama->>'penanda_audit_id'"))
                                        ->whereNull(DB::raw("a2.nilai_baru->>'penanda_audit_id'"))
                                        ->whereColumn('a2.waktu', '>', 'a.waktu');
                                });
                        });
                })
                ->first(['a.id']);

            $isCurrentlyMarked = $activeMarking !== null;

            $isNowFileOnly = (bool) ($nilaiBaru['aktif'] ?? false)
                && (bool) ($nilaiBaru['wajib'] ?? false)
                && (bool) ($nilaiBaru['izinkan_file'] ?? false)
                && ! (bool) ($nilaiBaru['izinkan_tautan'] ?? false)
                && ! (bool) ($nilaiBaru['izinkan_teks'] ?? false);

            if (! $isUnggahanAktif) {
                if ($isNowFileOnly && ! $isCurrentlyMarked) {
                    $prevCount = DB::table('audit_log')
                        ->where('objek_tipe', 'jenis_berkas')
                        ->where('objek_id', $jb->id)
                        ->where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
                        ->count();

                    $this->auditLogger->catat(
                        actor: $actor,
                        tindakan: 'berkas.tandai_tidak_dapat_dipenuhi',
                        objekTipe: 'jenis_berkas',
                        objekId: $jb->id,
                        nilaiLama: [
                            'nama' => $jb->nama,
                            'tahap' => $jb->tahap,
                            'status_pemenuhan' => 'normal',
                        ],
                        nilaiBaru: [
                            'nama' => $jb->nama,
                            'tahap' => $jb->tahap,
                            'status_pemenuhan' => 'tidak_dapat_dipenuhi',
                            'sebab' => 'saklar_unggahan_global_nonaktif',
                            'kunci_setelan' => 'berkas.unggahan_aktif',
                            'siklus_penandaan' => $prevCount + 1,
                        ],
                        alasan: 'Penandaan otomatis saat persyaratan wajib diubah menjadi file-only ketika saklar unggahan global dinonaktifkan.',
                        dasarIzin: $dasarIzin
                    );
                } elseif (! $isNowFileOnly && $isCurrentlyMarked) {
                    $this->auditLogger->catat(
                        actor: $actor,
                        tindakan: 'berkas.cabut_tidak_dapat_dipenuhi',
                        objekTipe: 'jenis_berkas',
                        objekId: $jb->id,
                        nilaiLama: [
                            'nama' => $jb->nama,
                            'tahap' => $jb->tahap,
                            'status_pemenuhan' => 'tidak_dapat_dipenuhi',
                            'sebab' => 'saklar_unggahan_global_nonaktif',
                            'penanda_audit_id' => $activeMarking->id,
                        ],
                        nilaiBaru: [
                            'nama' => $jb->nama,
                            'tahap' => $jb->tahap,
                            'status_pemenuhan' => 'normal',
                            'sebab' => 'persyaratan_diperbarui_non_file_only',
                            'penanda_audit_id' => $activeMarking->id,
                        ],
                        alasan: 'Pencabutan penanda tidak dapat dipenuhi karena persyaratan jenis berkas diperbarui menjadi tidak wajib atau mendukung mode non-file.',
                        dasarIzin: $dasarIzin
                    );
                }
            } elseif ($isCurrentlyMarked) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'berkas.cabut_tidak_dapat_dipenuhi',
                    objekTipe: 'jenis_berkas',
                    objekId: $jb->id,
                    nilaiLama: [
                        'nama' => $jb->nama,
                        'tahap' => $jb->tahap,
                        'status_pemenuhan' => 'tidak_dapat_dipenuhi',
                        'sebab' => 'saklar_unggahan_global_nonaktif',
                        'penanda_audit_id' => $activeMarking->id,
                    ],
                    nilaiBaru: [
                        'nama' => $jb->nama,
                        'tahap' => $jb->tahap,
                        'status_pemenuhan' => 'normal',
                        'sebab' => 'saklar_unggahan_global_aktif',
                        'penanda_audit_id' => $activeMarking->id,
                    ],
                    alasan: 'Pencabutan penanda tidak dapat dipenuhi karena saklar unggahan global aktif.',
                    dasarIzin: $dasarIzin
                );
            }

            return null;
        });

        if ($denied !== null) {
            $alasan = AuditReason::sanitize($data['alasan']);
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.ubah_ditolak', objekTipe: 'jenis_berkas', objekId: $id,
                alasan: trim($alasan) !== '' ? $alasan : 'Percobaan pembaruan persyaratan jenis berkas ditolak karena tidak memiliki izin.', dasarIzin: $denied,
            );
            abort(403);
        }

        return $formatWarning ?? $this->warnings->forUploadState($isUnggahanAktif, $data);
    }
}
