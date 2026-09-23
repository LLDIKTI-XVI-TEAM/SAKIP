<?php

namespace App\Services\Authorization;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RolePermissionReceipt
{
    private const TTL = 300;

    /** Hasil ephemeral setelah commit; kegagalan delivery tidak mengulang mutasi. */
    public function issue(string $actorId, string $sessionId, string $status): ?string
    {
        if (! in_array($status, ['added', 'revoked', 'unchanged'], true)) {
            return null;
        }
        $reference = (string) Str::uuid();
        try {
            $stored = Cache::store('database')->add($this->key($actorId, $sessionId, $reference), [
                'receipt_id' => $reference, 'actor_id' => $actorId, 'status' => $status,
                'expires_at' => now()->timestamp + self::TTL,
            ], self::TTL);

            return $stored ? $reference : null;
        } catch (Throwable) {
            Log::warning('Receipt izin peran tidak dapat disimpan; hasil delivery tidak tersedia.');

            return null;
        }
    }

    /**
     * Konsumsi tepat satu operasi, tanpa menghapus hasil milik actor/session lain.
     * Lease minimal TTL mencegah dua konsumen membaca receipt valid setelah lock habis.
     *
     * @return array{receipt_id: string, status: string}|null
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
            $lock = $store->lock($key.':consume', self::TTL);
            if (! $lock->get()) {
                return null;
            }
            try {
                $receipt = $cache->get($key);
                if (! is_array($receipt) || ($receipt['receipt_id'] ?? null) !== $reference
                    || ($receipt['actor_id'] ?? null) !== $actorId
                    || ! in_array($receipt['status'] ?? null, ['added', 'revoked', 'unchanged'], true)
                    || ! is_int($receipt['expires_at'] ?? null) || $receipt['expires_at'] <= now()->timestamp) {
                    return null;
                }
                if (! $cache->forget($key) || $receipt['expires_at'] <= now()->timestamp) {
                    return null;
                }

                return ['receipt_id' => $reference, 'status' => $receipt['status']];
            } finally {
                $lock->release();
            }
        } catch (Throwable) {
            Log::warning('Receipt izin peran tidak dapat dikonsumsi; hasil delivery tidak tersedia.');

            return null;
        }
    }

    private function key(string $actorId, string $sessionId, string $reference): string
    {
        return 'role-permission:receipt:'.hash('sha256', $sessionId).':'.$actorId.':'.$reference;
    }
}
