<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Support\AuditReason;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WriteAuditLog
{
    /**
     * Pemanggil server menyusun snapshot terbatas dan menulis dalam transaksi mutasinya.
     * Audit penolakan ditulis di luar transaksi yang akan dibatalkan.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): AuditLog
    {
        $type = $attributes['actor_type'] ?? null;
        $source = $attributes['sumber'] ?? null;
        $event = $attributes['tindakan'] ?? null;
        $actor = $attributes['actor_id'] ?? null;
        $valid = match ($type) {
            'user' => $source === 'manual' && is_string($actor) && $actor !== '',
            'system' => $actor === null && (
                ($source === 'sso_onboarding' && $event === 'pengguna.terdaftar'
                    && ($attributes['objek_tipe'] ?? null) === 'users'
                    && ($attributes['operator_reference'] ?? null) === null
                    && ($attributes['runtime_identity'] ?? null) === null)
                || ($source === 'preset_release' && ($attributes['operator_reference'] ?? null) === null
                    && is_string($attributes['runtime_identity'] ?? null) && trim($attributes['runtime_identity']) !== ''
                    && match ($event) {
                        'role_permissions.ubah', 'roles.tambah', 'roles.hapus' => ($attributes['objek_tipe'] ?? null) === 'roles',
                        'permissions.ubah' => ($attributes['objek_tipe'] ?? null) === 'permissions',
                        default => false,
                    })
            ),
            'operator' => $source === 'bootstrap' && $actor === null
                && is_string($attributes['operator_reference'] ?? null) && trim($attributes['operator_reference']) !== ''
                && is_string($attributes['runtime_identity'] ?? null) && trim($attributes['runtime_identity']) !== ''
                && in_array($event, ['user_roles.tambah', 'user_roles.ubah', 'pengguna.aktivasi', 'role_permissions.ubah', 'auth.bootstrap'], true),
            default => false,
        };
        if (! $valid || ! is_string($attributes['alasan'] ?? null) || trim($attributes['alasan']) === '') {
            throw new InvalidArgumentException('Audit memerlukan provenance aktor dan alasan yang sah.');
        }

        $reason = AuditReason::sanitize($attributes['alasan']);
        if (trim($reason) === '') {
            // Input yang habis setelah sanitasi adalah error field; provenance tidak sah tetap error pemanggil.
            throw ValidationException::withMessages(['alasan' => 'Alasan wajib memuat teks yang dapat dibaca.']);
        }

        return AuditLog::create([
            'actor_id' => $actor,
            'actor_type' => $type,
            'sumber' => $source,
            'operator_reference' => $attributes['operator_reference'] ?? null,
            'runtime_identity' => $attributes['runtime_identity'] ?? null,
            'waktu' => now(),
            'tindakan' => $event,
            'objek_tipe' => $attributes['objek_tipe'],
            'objek_id' => $attributes['objek_id'],
            'nilai_lama' => $attributes['nilai_lama'] ?? null,
            'nilai_baru' => $attributes['nilai_baru'] ?? null,
            'alasan' => $reason,
            'dasar_izin' => $attributes['dasar_izin'] ?? null,
        ]);
    }
}
