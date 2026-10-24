<?php

namespace App\Actions\JenisBerkas;

use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Storage\UploadLimits;
use App\Support\AuditReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateJenisBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Data hasil validasi; key opsional tetap dibedakan dari nilai null.
     *
     * @param array{
     *     nama: string,
     *     tahap: 'rencana_aksi'|'pengukuran'|'kegiatan_perencanaan',
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
     *     alasan?: string|null,
     * } $data
     */
    public function handle(User $actor, array $data): void
    {
        $denied = DB::transaction(function () use (&$actor, $data): ?array {
            // Izin dievaluasi ulang setelah aktor, peran, dan permission terkunci.
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::where('kode', 'jenis_berkas:create')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:create');
            if (! $decision['allowed']) {
                return $decision;
            }
            Validator::make($data, ['ukuran_maks_kb' => ['nullable', 'integer', 'min:100', 'max:'.UploadLimits::maxKb()]])->validate();
            $data['created_by'] = $actor->id;
            // Alasan opsional (Q43 §43.3 butir 3); teks baku menjaga audit tetap terbaca bila kosong.
            $alasan = trim(AuditReason::sanitize($data['alasan'] ?? null));
            unset($data['alasan']);
            $jb = JenisBerkas::create($data);
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.buat', objekTipe: 'jenis_berkas', objekId: $jb->id,
                nilaiLama: null, nilaiBaru: $jb->toArray(),
                alasan: $alasan !== '' ? $alasan : 'Penambahan persyaratan jenis berkas: '.$jb->nama,
                dasarIzin: $decision,
            );

            return null;
        });

        if ($denied !== null) {
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.buat_ditolak', objekTipe: 'jenis_berkas', objekId: (string) Str::uuid(),
                alasan: 'Percobaan penambahan persyaratan jenis berkas ditolak karena tidak memiliki izin. (Nama: '.$data['nama'].')', dasarIzin: $denied,
            );
            abort(403);
        }
    }
}
