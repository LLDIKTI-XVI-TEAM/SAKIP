<?php

namespace App\Actions\Perencanaan;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DestroySasaran
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PermissionResolver $resolver) {}

    /**
     * Menghapus Sasaran yang belum memiliki Indikator. Pemeriksaan anak dan
     * penghapusan berjalan dalam satu transaksi terkunci agar insert anak
     * konkuren tidak tersapu cascade-delete.
     *
     * Bila masih beranak, kegagalan guard dicatat sebagai audit penolakan
     * (tanpa mengubah data) lalu ValidationException dilempar seperti
     * validasi form biasa.
     *
     * @return array{kode: string}
     */
    public function handle(User $actor, SasaranStrategis $sasaran, string $alasan): array
    {
        $alasan = trim($alasan);
        $dasarIzin = $this->resolver->resolve($actor, PermissionCodes::SASARAN_DELETE)->toAuditBasis();

        $result = DB::transaction(function () use ($sasaran, $actor, $alasan, $dasarIzin) {
            /** @var SasaranStrategis $lockedSasaran */
            $lockedSasaran = SasaranStrategis::where('id', $sasaran->id)->lockForUpdate()->firstOrFail();

            if ($lockedSasaran->indikatorKinerjas()->exists()) {
                return false;
            }

            $nilaiLama = $lockedSasaran->withoutRelations()->toArray();
            $lockedSasaran->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus',
                objekTipe: 'sasaran',
                objekId: (string) $lockedSasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $dasarIzin,
            );

            return $nilaiLama;
        });

        if ($result === false) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) $sasaran->id,
                nilaiLama: $sasaran->withoutRelations()->toArray(),
                nilaiBaru: null,
                alasan: "Penolakan penghapusan sasaran '{$sasaran->kode}': sasaran masih memiliki indikator kinerja. Alasan pengguna: {$alasan}",
                dasarIzin: $dasarIzin,
            );

            throw ValidationException::withMessages([
                'sasaran' => "Sasaran '{$sasaran->kode}' tidak dapat dihapus karena masih memiliki indikator kinerja.",
            ]);
        }

        return $result;
    }
}
