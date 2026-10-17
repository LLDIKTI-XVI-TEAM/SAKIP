<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Services\Audit\AuditLogPresenter;

class TampilkanAuditLog
{
    /**
     * Detail satu baris audit. Pemanggil sudah memeriksa `audit:read` sebelum baris dicari,
     * sehingga pengguna tanpa izin tidak dapat membedakan ID yang ada dari yang tidak ada.
     *
     * @return array{audit: array<string, mixed>}
     */
    public function handle(string $id): array
    {
        return ['audit' => AuditLogPresenter::detail(AuditLog::with('actor:id,nama,email')->findOrFail($id))];
    }
}
