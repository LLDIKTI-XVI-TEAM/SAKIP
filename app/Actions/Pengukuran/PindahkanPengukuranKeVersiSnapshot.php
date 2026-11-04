<?php

namespace App\Actions\Pengukuran;

use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Support\AlasanAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Memindahkan pengukuran ke nomor versi terbesar yang berlaku untuk periodenya, tanpa membedakan asal versi.
 * Memakai kunciKonteks dengan urutan lock yang sama karena lock() menilai businessErrors pada snapshot lama
 * dan akan salah menolak unit lama yang nonaktif. Pemeriksaan khusus dilakukan berurutan:
 * izin kedua unit → token header → token tujuan → guard bisnis. Rekonsiliasi dan audit berada dalam satu transaksi.
 */
class PindahkanPengukuranKeVersiSnapshot
{
    public function __construct(private PengukuranMutationSteps $steps, private PermissionResolver $resolver,
        private PengukuranKinerjaPolicy $policy) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $id = strtolower($id);
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$decision) {
                [$actor, $p, $lama] = $this->steps->kunciKonteks($actor, $id, 'pindah_snapshot');
                $decisionLama = $this->resolver->decide($actor, 'pengukuran:update', $lama->unit_id);
                $decision = $decisionLama;
                $tujuan = JadwalSnapshot::versiBerlaku($lama->jadwal_id, $p->indikator_id, $p->periode->urutan);
                if ($tujuan) {
                    $tujuan->load(['komponen', 'unit'])->setRelation('jadwal', $lama->jadwal);
                }
                $decisionTujuan = $tujuan && $tujuan->unit_id !== $lama->unit_id
                    ? $this->resolver->decide($actor, 'pengukuran:update', $tujuan->unit_id) : null;
                if (! $decisionLama['allowed'] || $decisionLama['roles'] === []) {
                    throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
                }
                if ($decisionTujuan && (! $decisionTujuan['allowed'] || $decisionTujuan['roles'] === [])) {
                    // Audit penolakan harus memakai dasar izin unit yang menolak.
                    $decision = $decisionTujuan;
                    throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
                }
                $decision = $decisionTujuan ?? $decisionLama;
                if ($p->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.'])->status(409);
                }
                if ($tujuan && (strtolower($data['expected_snapshot_id']) !== $tujuan->id || (int) $data['expected_snapshot_versi'] !== $tujuan->nomor_versi)) {
                    throw ValidationException::withMessages(['expected_snapshot_id' => 'Versi terbaru indikator berubah. Muat ulang halaman sebelum memindahkan.'])->status(409);
                }
                $errors = $this->policy->pindahSnapshotErrors($actor, $p, $tujuan);
                if ($errors) {
                    throw ValidationException::withMessages(['pindah_snapshot' => $errors]);
                }
                $before = [...$this->steps->auditState($p), 'jadwal_snapshot' => $this->ringkas($lama)];
                $input = ['catatan' => $p->catatan, 'alasan_tidak_dapat_dihitung' => $p->alasan_tidak_dapat_dihitung];
                if ($p->status_perhitungan === 'belum_dapat_diukur') {
                    $input['status_perhitungan'] = 'belum_dapat_diukur';
                }
                if ($tujuan->tipe_perhitungan === 'manual') {
                    // Nilai manual hanya dibawa bila tipe sama, bukan dari hasil perhitungan komponen.
                    $tipeSama = $lama->tipe_perhitungan === $tujuan->tipe_perhitungan;
                    $input['nilai'] = $tipeSama ? $p->nilai : null;
                } else {
                    $peranLama = $lama->komponen->pluck('peran', 'komponen_id');
                    $nilaiLama = $p->komponen->pluck('nilai', 'komponen_id');
                    // Identitas komponen saja tidak cukup; nilai hanya dibawa bila perannya tetap sama.
                    $input['komponen'] = $tujuan->komponen->map(fn ($k) => ['komponen_id' => $k->komponen_id,
                        'nilai' => ($peranLama[$k->komponen_id] ?? null) === $k->peran ? ($nilaiLama[$k->komponen_id] ?? null) : null])->all();
                }
                $p->komponen()->whereNotIn('komponen_id', $tujuan->komponen->pluck('komponen_id'))->delete();
                $p->jadwal_snapshot_id = $tujuan->id;
                $p->setRelation('jadwalSnapshot', $tujuan);
                $path = null;
                $this->steps->storeInput($actor, $p, $tujuan, $input, $path, $decision);
                $alasan = AlasanAudit::sanitasi($data['alasan'] ?? null, 'Memindahkan pengukuran ke versi snapshot '.$tujuan->nomor_versi.'.');

                return $this->steps->finish($actor, $p, 'pengukuran.pindah_snapshot', $alasan, $decision, $before,
                    ['jadwal_snapshot' => $this->ringkas($tujuan)] + ($decisionTujuan ? ['dasar_izin_unit_lama' => $decisionLama] : []));
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'pindah_snapshot', null, $decision, $exception);
            throw $exception;
        }
    }

    private function ringkas(JadwalSnapshot $snapshot): array
    {
        return $snapshot->only(['id', 'nomor_versi', 'unit_id', 'tipe_perhitungan', 'presisi', 'target']);
    }
}
