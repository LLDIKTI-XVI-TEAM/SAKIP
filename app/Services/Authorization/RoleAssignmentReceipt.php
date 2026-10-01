<?php

namespace App\Services\Authorization;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim hasil minimal Assign Role melalui receipt terikat aktor dan sesi.
 * Delivery cache terpisah dari transaksi Action yang sudah commit agar kegagalan tidak mengulang mutasi.
 */
class RoleAssignmentReceipt
{
    /**
     * Simpan hanya hasil minimal sesudah commit; delivery gagal tidak memutar ulang mutasi.
     *
     * @param  array{status:'assigned'|'changed'|'unchanged',has_active_pj:bool}  $outcome
     */
    public function issue(string $actorId, string $sessionId, array $outcome): ?string
    {
        $reference = (string) Str::uuid();
        try {
            $stored = Cache::store('database')->put($this->key($actorId, $sessionId, $reference), [
                'receipt_id' => $reference, 'actor_id' => $actorId,
                'status' => $outcome['status'], 'has_active_pj' => $outcome['has_active_pj'],
                'expires_at' => now()->addSeconds(300)->timestamp,
            ], 300);

            return $stored ? $reference : null;
        } catch (Throwable) {
            Log::warning('Konfirmasi Assign Role tidak tersedia saat menyimpan receipt.');

            return null;
        }
    }

    /**
     * Lock per operasi melindungi read+forget; kegagalan atau mismatch selalu unknown.
     *
     * @return array{receipt_id:string,status:'assigned'|'changed'|'unchanged',has_active_pj:bool}|null
     */
    public function consume(string $actorId, string $sessionId, string $reference): ?array
    {
        if (! Str::isUuid($reference)) {
            return null;
        }
        try {
            $cache = Cache::store('database');
            $store = $cache->getStore();
            if (! $store instanceof LockProvider) {
                return null;
            }
            $key = $this->key($actorId, $sessionId, $reference);
            $outcome = $store->lock($key.':consume', 300)->get(function () use ($cache, $key, $actorId, $reference): ?array {
                $receipt = $cache->get($key);
                if (! is_array($receipt) || count($receipt) !== 5
                    || ($receipt['receipt_id'] ?? null) !== $reference || ($receipt['actor_id'] ?? null) !== $actorId
                    || ! in_array($receipt['status'] ?? null, ['assigned', 'changed', 'unchanged'], true)
                    || ! is_bool($receipt['has_active_pj'] ?? null)
                    || ! is_int($receipt['expires_at'] ?? null) || $receipt['expires_at'] <= now()->timestamp
                    || ! $cache->forget($key)) {
                    return null;
                }

                return ['receipt_id' => $reference, 'status' => $receipt['status'], 'has_active_pj' => $receipt['has_active_pj']];
            });

            return is_array($outcome) ? $outcome : null;
        } catch (Throwable) {
            Log::warning('Konfirmasi Assign Role tidak tersedia saat membaca receipt.');

            return null;
        }
    }

    /** Session mentah tidak disimpan; aktor/session lain tidak membaca atau menghapus key pemilik. */
    private function key(string $actorId, string $sessionId, string $reference): string
    {
        return 'role-assignment:'.$actorId.':'.hash('sha256', $sessionId).':'.$reference;
    }
}
