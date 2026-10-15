<?php

namespace App\Actions\Rekomendasi;

use App\Models\RekomendasiPimpinan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TetapkanRekomendasi
{
    private const KONFLIK = 'Rekomendasi telah diperbarui pengguna lain. Muat ulang halaman sebelum menyimpan.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /**
     * Simpan draf atau setujui rekomendasi satu indikator × tahun × periode; keduanya memakai izin yang sama dan
     * dibedakan oleh status (Q51 §51.2 butir 1). Draf terbuka diubah di tempat dengan token versi (Q54); baris
     * disetujui tidak pernah diubah, sehingga perubahan berikutnya membentuk baris baru.
     *
     * @param  array{indikator_id: string, tahun: int|string, periode_id: string, status: string, isi: string, rujukan_sumber?: ?string, versi_draf?: int|string|null}  $data
     */
    public function handle(User $actor, array $data): RekomendasiPimpinan
    {
        /** @var PermissionDecision|null $decision */
        $decision = null;
        $kunci = ['indikator_id' => strtolower($data['indikator_id']), 'tahun' => (int) $data['tahun'], 'periode_id' => strtolower($data['periode_id'])];
        try {
            try {
                return DB::transaction(function () use ($actor, $data, $kunci, &$decision): RekomendasiPimpinan {
                    ['aktor' => $aktor, 'keputusan' => $decision] = $this->lockedActor->handle($actor, PermissionCodes::REKOMENDASI_TETAPKAN);
                    if (! $decision->allowed || ! $aktor) {
                        throw new AuthorizationException('Anda tidak memiliki izin menetapkan rekomendasi pimpinan.');
                    }
                    if (! $this->diukur($kunci)) {
                        throw ValidationException::withMessages(['periode_id' => 'Indikator tidak diukur pada tahun dan periode yang dipilih.']);
                    }
                    $draf = RekomendasiPimpinan::where($kunci)->where('status', RekomendasiPimpinan::STATUS_DRAF)->lockForUpdate()->first();
                    $versi = isset($data['versi_draf']) ? (int) $data['versi_draf'] : null;
                    if ($draf?->versi !== $versi) {
                        throw ValidationException::withMessages(['konflik' => self::KONFLIK])->status(409);
                    }

                    $lama = $draf?->only(['isi', 'rujukan_sumber', 'status', 'versi']);
                    $row = $draf ?? new RekomendasiPimpinan([...$kunci, 'ditetapkan_oleh' => $aktor->id, 'created_at' => CarbonImmutable::now()]);
                    $row->fill(['isi' => $data['isi'], 'rujukan_sumber' => $data['rujukan_sumber'] ?? null, 'versi' => $draf ? $draf->versi + 1 : 1]);
                    $setujui = $data['status'] === RekomendasiPimpinan::STATUS_DISETUJUI;
                    if ($setujui) {
                        // Satu pengguna memegang satu peran; kodenya disalin sebagai penanda persetujuan cadangan.
                        $peran = $aktor->roles()->value('kode') ?? throw new AuthorizationException('Persetujuan rekomendasi memerlukan peran pengguna.');
                        $row->fill(['status' => RekomendasiPimpinan::STATUS_DISETUJUI, 'disetujui_oleh' => $aktor->id, 'disetujui_oleh_role' => $peran, 'disetujui_pada' => CarbonImmutable::now()]);
                    }
                    $row->save();

                    $this->audit->catat(actor: $actor, tindakan: 'rekomendasi_pimpinan.'.($setujui ? 'setujui' : ($draf ? 'ubah_draf' : 'tetapkan')),
                        objekTipe: 'rekomendasi_pimpinan', objekId: $row->id, nilaiLama: $lama, nilaiBaru: [
                            ...$row->only(['indikator_id', 'tahun', 'periode_id', 'isi', 'rujukan_sumber', 'status', 'versi']),
                            ...($setujui ? [...$row->only(['disetujui_oleh', 'disetujui_oleh_role']), 'disetujui_pada' => $row->disetujui_pada->toIso8601ZuluString(), 'persetujuan_cadangan' => $row->isCadangan()] : []),
                        ], dasarIzin: $decision->toAuditBasis());

                    return $row;
                });
            } catch (QueryException $exception) {
                // Dua draf baru bersamaan: index satu draf menolak yang kalah, diperlakukan sama dengan token basi.
                if (($exception->errorInfo[0] ?? null) !== '23505' || ! str_contains($exception->getMessage(), 'rekomendasi_pimpinan_satu_draf')) {
                    throw $exception;
                }
                throw ValidationException::withMessages(['konflik' => self::KONFLIK])->status(409);
            }
        } catch (AuthorizationException|ValidationException $exception) {
            // Di luar rollback agar penolakan tetap tercatat dengan keputusan izin yang benar-benar dipakai.
            $alasan = match (true) {
                $exception instanceof AuthorizationException => 'izin_ditolak',
                $exception->status === 409 => 'konflik_versi',
                default => 'aturan_rekomendasi',
            };
            $this->audit->catat(actor: $actor, tindakan: 'rekomendasi_pimpinan.tetapkan_ditolak', objekTipe: 'rekomendasi_pimpinan', objekId: (string) Str::uuid(),
                nilaiBaru: ['alasan_penolakan' => $alasan, ...$kunci, 'status' => $data['status']], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }

    /**
     * Kombinasi sah bila indikator berada pada snapshot jadwal non-draft tahun itu dan periodenya anggota jadwal
     * tersebut, mulai periode berlakunya snapshot.
     *
     * @param  array{indikator_id: string, tahun: int, periode_id: string}  $kunci
     */
    private function diukur(array $kunci): bool
    {
        return DB::table('jadwal_snapshot as s')->join('jadwal_tahunan as j', 'j.id', '=', 's.jadwal_id')
            ->join('jadwal_periode as jp', 'jp.jadwal_id', '=', 's.jadwal_id')
            ->join('periode as pr', 'pr.id', '=', 'jp.periode_id')->join('periode as pm', 'pm.id', '=', 's.periode_mulai_id')
            ->where('s.indikator_id', $kunci['indikator_id'])->where('j.tahun', $kunci['tahun'])->whereIn('j.status', ['aktif', 'ditutup'])
            ->where('jp.periode_id', $kunci['periode_id'])->whereColumn('pm.urutan', '<=', 'pr.urutan')->exists();
    }
}
