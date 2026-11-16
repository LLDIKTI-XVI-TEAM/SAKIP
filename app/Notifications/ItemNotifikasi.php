<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Satu-satunya bentuk item notifikasi in-app. Judul dan ringkasan disimpan sebagai teks jadi
 * agar daftar tidak memuat relasi dan tetap terbaca walau objeknya berubah; tujuan disimpan
 * sebagai nama route + parameter dan diotorisasi ulang oleh route tujuan saat dibuka.
 */
class ItemNotifikasi extends Notification
{
    /** @param array<string, string> $routeParams */
    public function __construct(
        public readonly string $jenis,
        public readonly string $judul,
        public readonly ?string $ringkasan,
        public readonly string $routeName,
        public readonly array $routeParams = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Kolom `type` diisi jenis kejadian, bukan nama kelas, agar dapat difilter per kejadian. */
    public function databaseType(object $notifiable): string
    {
        return $this->jenis;
    }

    /**
     * `route_params` selalu tersimpan sebagai objek JSON (`{}` bila kosong), bukan `[]`.
     *
     * @return array{judul: string, ringkasan: string|null, route_name: string, route_params: object}
     */
    public function toDatabase(object $notifiable): array
    {
        return ['judul' => $this->judul, 'ringkasan' => $this->ringkasan, 'route_name' => $this->routeName, 'route_params' => (object) $this->routeParams];
    }
}
