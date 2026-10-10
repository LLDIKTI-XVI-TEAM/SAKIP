<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReturnPengukuran
{
    public function __construct(private PengukuranMutationSteps $steps) {}

    /** Pengembalian membuka kembali pengisian dari diajukan/diverifikasi dengan alasan sah sebagai riwayat dan audit; disahkan tidak pernah dibuka. */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$decision) {
                [$actor, $p, , $period, $before] = $this->steps->lock($actor, $id, 'pengukuran:kembalikan', 'kembalikan', (int) $data['versi'], $decision);
                $reason = trim((string) ($data['catatan'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages(['catatan' => 'Alasan pengembalian wajib diisi.']);
                }
                $p->status_alur = 'dikembalikan';

                return $this->steps->finish($actor, $p, 'pengukuran.kembalikan', $reason, $decision, $before,
                    ['self_approval' => false, ...$this->steps->reviewTrail($period)]);
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'kembalikan', null, $decision, $exception);
            throw $exception;
        }
    }
}
