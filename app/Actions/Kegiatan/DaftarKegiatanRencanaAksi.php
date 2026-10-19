<?php

namespace App\Actions\Kegiatan;

use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Services\Kegiatan\TotalAnggaranKegiatan;
use App\Support\PermissionCodes;

/**
 * Props panel kegiatan per periode pada halaman Rencana Aksi (Q42 §42.1):
 * daftar kegiatan yang diklaim RA (tahap RA dan pengukuran) beserta anggaran,
 * penanda kegiatan bersama, total (Q42 §42.2), dan capability tambah. Akses
 * halaman sudah diputuskan `IndexRencanaAksi`; periode, komponen, dan
 * periode terkunci bagi aktor memakai props halaman yang sama.
 */
class DaftarKegiatanRencanaAksi
{
    public function __construct(
        private readonly GerbangKlaimKegiatan $gerbang,
        private readonly PermissionResolver $resolver,
        private readonly TotalAnggaranKegiatan $anggaran,
    ) {}

    /**
     * Null bila aktor tidak boleh membaca kegiatan unit RA (deny unit menang),
     * agar detail kegiatan, termasuk anggaran dan total, tidak terbaca lewat
     * halaman RA (Q42 §42.5).
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
            ->orderBy('kegiatan.nama')
            ->orderBy('klaim_kegiatan.id')
            ->get(['klaim_kegiatan.id', 'klaim_kegiatan.kegiatan_id', 'klaim_kegiatan.komponen_id', 'klaim_kegiatan.arah_dampak',
                'klaim_kegiatan.catatan', 'klaim_kegiatan.sumber_klaim', 'kegiatan.periode_id', 'kegiatan.nama', 'kegiatan.status', 'kegiatan.anggaran']);
        $bersama = $this->anggaran->kegiatanBersama($daftar->pluck('kegiatan_id')->map(fn ($id): string => (string) $id)->unique()->values()->all());

        return [
            'daftar' => $daftar->map(fn (KlaimKegiatan $klaim): array => [
                'id' => (string) $klaim->id,
                'kegiatan_id' => (string) $klaim->kegiatan_id,
                'periode_id' => (string) $klaim->getAttribute('periode_id'),
                'nama' => (string) $klaim->getAttribute('nama'),
                'status' => (string) $klaim->getAttribute('status'),
                // Kolom hasil join tidak melewati cast `decimal:0` Kegiatan; rupiah bulat, null berarti belum diisi.
                'anggaran' => $klaim->getAttribute('anggaran') === null ? null : bcadd((string) $klaim->getAttribute('anggaran'), '0', 0),
                'sumber_klaim' => $klaim->sumber_klaim,
                'kegiatan_bersama' => in_array((string) $klaim->kegiatan_id, $bersama, true),
                'komponen_id' => $klaim->komponen_id,
                'arah_dampak' => $klaim->arah_dampak,
                'catatan' => $klaim->catatan,
            ])->values()->all(),
            'total' => $this->anggaran->perRencanaAksi($header),
            'can' => [
                'tambah' => $this->gerbang->boleh($actor, $header, $unitId, PermissionCodes::KEGIATAN_CREATE),
                // Pengelolaan klaim per kegiatan berada di halaman Kegiatan; tautan hanya bila izin ubah kegiatan ada.
                'kelola' => $this->resolver->allows($actor, PermissionCodes::KEGIATAN_UPDATE, $unitId),
            ],
        ];
    }
}
