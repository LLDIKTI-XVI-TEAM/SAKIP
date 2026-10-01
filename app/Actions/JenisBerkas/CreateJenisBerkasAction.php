<?php

namespace App\Actions\JenisBerkas;

use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\JenisBerkas\JenisBerkasWarnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateJenisBerkasAction
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
     * } $data
     */
    public function handle(User $actor, array $data): ?string
    {
        $isUnggahanAktif = true;
        $denied = DB::transaction(function () use (&$actor, $data, &$isUnggahanAktif): ?array {
            // Selaras dengan writer akses: pengguna, role aktif, lalu permission berurutan UUID.
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::whereIn('kode', ['jenis_berkas:create', 'pengaturan:update'])->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:create');
            if (! $decision['allowed']) {
                return $decision;
            }

            $settingsDecision = $this->resolver->decide($actor, 'pengaturan:update');
            $hasBatasTeknis = array_key_exists('format_diizinkan', $data) || array_key_exists('ukuran_maks_kb', $data);
            if (! $settingsDecision['allowed']) {
                unset($data['format_diizinkan'], $data['ukuran_maks_kb']);
                $hasBatasTeknis = false;
            }
            $dasarIzin = $hasBatasTeknis
                ? ['jenis_berkas' => $decision, 'pengaturan' => $settingsDecision]
                : $decision;

            // Serialisasikan pembacaan saklar dengan sharedLock di dalam transaksi
            // untuk mencegah race condition saat admin menonaktifkan unggahan global
            $saklarRow = Pengaturan::where('kunci', 'berkas.unggahan_aktif')->sharedLock()->first();
            $isUnggahanAktif = filter_var($saklarRow?->nilai ?? true, FILTER_VALIDATE_BOOLEAN);

            $data['created_by'] = $actor->id;

            $jb = JenisBerkas::create($data);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.buat',
                objekTipe: 'jenis_berkas',
                objekId: $jb->id,
                nilaiLama: null,
                nilaiBaru: $jb->toArray(),
                alasan: 'Penambahan persyaratan jenis berkas: '.$jb->nama,
                dasarIzin: $dasarIzin
            );

            if (! $isUnggahanAktif) {
                $isFileOnly = (bool) ($jb->aktif ?? true)
                    && (bool) ($jb->wajib ?? false)
                    && (bool) ($jb->izinkan_file ?? false)
                    && ! (bool) ($jb->izinkan_tautan ?? false)
                    && ! (bool) ($jb->izinkan_teks ?? false);

                if ($isFileOnly) {
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
                        alasan: 'Penandaan otomatis saat persyaratan wajib file-only dibuat ketika saklar unggahan global dinonaktifkan.',
                        dasarIzin: $dasarIzin
                    );
                }
            }

            return null;
        });

        if ($denied !== null) {
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.buat_ditolak', objekTipe: 'jenis_berkas', objekId: (string) Str::uuid(),
                alasan: 'Percobaan penambahan persyaratan jenis berkas ditolak karena tidak memiliki izin. (Nama: '.$data['nama'].')', dasarIzin: $denied,
            );
            abort(403);
        }

        return $this->warnings->forUploadState($isUnggahanAktif, $data);
    }
}
