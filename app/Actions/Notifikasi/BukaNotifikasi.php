<?php

namespace App\Actions\Notifikasi;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;

class BukaNotifikasi
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Tandai item milik aktor sebagai dibaca lalu kembalikan URL tujuan yang tersimpan.
     * Item pengguna lain atau id asing = 404 (dicari lewat relasi milik aktor). Update bersyarat `read_at IS NULL`
     * menjaga waktu baca pertama saat dua POST serentak. Tidak ada bypass izin: route tujuan memeriksanya sendiri.
     */
    public function handle(User $user, string $id): string
    {
        if ($this->resolver->penggunaBerperanAktif([$user->id]) === []) {
            throw new AuthorizationException('Akun belum memiliki peran aktif.');
        }
        $item = $user->notifications()->whereKey(strtolower($id))->firstOrFail();
        // Tujuan dihitung lebih dulu agar item tidak tertandai dibaca bila route-nya ternyata tidak dapat dibentuk.
        $tujuan = route($item->data['route_name'], $item->data['route_params']);
        $user->notifications()->whereKey($item->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $tujuan;
    }
}
