<?php

namespace App\Actions\Kegiatan;

use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Support\PermissionCodes;

/**
 * Props panel kegiatan per periode pada halaman Rencana Aksi (Q42 §42.1):
 * daftar kegiatan yang diklaim tahap RA beserta capability tambah. Akses
 * halaman sudah diputuskan `IndexRencanaAksi`; periode, komponen, dan
 * periode terkunci bagi aktor memakai props halaman yang sama.
 */
class DaftarKegiatanRencanaAksi
{
    public function __construct(
        private readonly GerbangKlaimKegiatan $gerbang,
        private readonly PermissionResolver $resolver,
    ) {}

    /**
     * Null bila aktor tidak boleh membaca kegiatan unit RA (deny unit menang),
     * agar detail kegiatan tidak terbaca lewat halaman RA.
     *
     * @return array<string, mixed>|null
     */
    public function handle(User $actor, string $id): ?array
    {
        $header = RencanaAksi::findOrFail($id);
        $unitId = (string) $header->unit_id;
        if (! $this->resolver->allows($actor, PermissionCodes::KEGIATAN_READ, $unitId)) {
            return null;
        }
        $daftar = KlaimKegiatan::query()
            ->join('kegiatan', 'kegiatan.id', '=', 'klaim_kegiatan.kegiatan_id')
            ->where('klaim_kegiatan.rencana_aksi_id', $header->id)
            ->where('klaim_kegiatan.sumber_klaim', 'rencana_aksi')
            ->orderBy('kegiatan.nama')
            ->orderBy('klaim_kegiatan.id')
            ->get(['klaim_kegiatan.id', 'klaim_kegiatan.kegiatan_id', 'klaim_kegiatan.komponen_id', 'klaim_kegiatan.arah_dampak',
                'klaim_kegiatan.catatan', 'kegiatan.periode_id', 'kegiatan.nama', 'kegiatan.status']);

        return [
            'daftar' => $daftar->map(fn (KlaimKegiatan $klaim): array => [
                'id' => (string) $klaim->id,
                'kegiatan_id' => (string) $klaim->kegiatan_id,
                'periode_id' => (string) $klaim->getAttribute('periode_id'),
                'nama' => (string) $klaim->getAttribute('nama'),
                'status' => (string) $klaim->getAttribute('status'),
                'komponen_id' => $klaim->komponen_id,
                'arah_dampak' => $klaim->arah_dampak,
                'catatan' => $klaim->catatan,
            ])->values()->all(),
            'can' => [
                'tambah' => $this->gerbang->boleh($actor, $header, $unitId, PermissionCodes::KEGIATAN_CREATE),
                // Pengelolaan klaim per kegiatan berada di halaman Kegiatan; tautan hanya bila izin ubah kegiatan ada.
                'kelola' => $this->resolver->allows($actor, PermissionCodes::KEGIATAN_UPDATE, $unitId),
            ],
        ];
    }
}
