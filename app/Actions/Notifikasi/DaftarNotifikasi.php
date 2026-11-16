<?php

namespace App\Actions\Notifikasi;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;

class DaftarNotifikasi
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Daftar item milik aktor, terbaru lebih dulu (`id` sebagai pemutus seri waktu sama), 20 per halaman.
     * Akun tanpa peran aktif ditolak: tanpa peran tidak ada izin apa pun, termasuk membaca item lamanya.
     * Route tujuan tidak dikirim ke klien; membuka item selalu lewat POST agar prefetch tidak menandai dibaca.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        if ($this->resolver->penggunaBerperanAktif([$user->id]) === []) {
            throw new AuthorizationException('Akun belum memiliki peran aktif.');
        }
        $notifikasi = $user->notifications()->orderByDesc('id')->paginate(20)
            ->through(fn (DatabaseNotification $item): array => ['id' => $item->id, 'jenis' => $item->type, 'judul' => $item->data['judul'],
                'ringkasan' => $item->data['ringkasan'], 'dibaca' => $item->read_at !== null, 'dibuat_pada' => $item->created_at?->toIso8601String()]);

        return ['notifikasi' => $notifikasi];
    }
}
