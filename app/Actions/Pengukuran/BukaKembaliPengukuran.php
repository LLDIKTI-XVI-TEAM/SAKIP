<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\StatusCapaian;
use App\Models\User;
use App\Support\AuditReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class BukaKembaliPengukuran
{
    public function __construct(private PengukuranMutationSteps $steps) {}

    /**
     * Transisi `disahkan → dikembalikan` dengan alasan wajib (Q43 §43.2 butir 1, Workflow §13 Lapis 2).
     * Versi resmi dan status capaiannya tidak diubah: koreksi berikutnya membentuk versi baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$decision) {
                [$actor, $p, , , $before] = $this->steps->lock($actor, $id, 'pengukuran:buka_kembali', 'buka_kembali', (int) $data['versi'], $decision);
                Validator::make($data, ['alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)]])->validate();
                $alasan = trim(AuditReason::sanitize($data['alasan'] ?? null));
                if ($alasan === '') {
                    throw ValidationException::withMessages(['alasan' => 'Alasan buka kembali wajib diisi.']);
                }
                $versiResmi = $p->ratifiedVersion;
                $statusCapaian = $versiResmi ? StatusCapaian::berlaku($versiResmi->id)->first() : null;
                $p->status_alur = 'dikembalikan';
                // Nilai lama diperkaya hanya untuk audit tindakan ini agar bentuk audit mutasi lain tidak berubah.
                $before += ['versi_resmi' => $versiResmi?->only(['id', 'nomor']), 'status_capaian_berlaku' => $this->statusRingkas($statusCapaian)];

                return $this->steps->finish($actor, $p, 'pengukuran.buka_kembali', $alasan, $decision, $before,
                    ['versi_resmi_dibuka' => $versiResmi?->only(['id', 'nomor']), 'status_capaian_saat_dibuka' => $this->statusRingkas($statusCapaian), 'self_approval' => false]);
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'buka_kembali', null, $decision, $exception);
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function statusRingkas(?StatusCapaian $status): ?array
    {
        return $status ? ['id' => $status->id, 'status' => $status->status, 'sumber' => $status->sumber,
            'penetap' => $status->ditetapkan_oleh, 'ditetapkan_pada' => $status->created_at?->toIso8601String()] : null;
    }
}
