<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Services\Kegiatan\TotalAnggaranKegiatan;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

/**
 * Props daftar kegiatan per periode untuk panel Rencana Aksi dan halaman
 * kegiatan Rencana Aksi (Q42 §42.1): kegiatan yang diklaim RA (tahap RA dan
 * pengukuran) beserta anggaran, penanda kegiatan bersama, total (Q42 §42.2),
 * capability tambah, dan capability kelola. Akses halaman sudah diputuskan
 * pemanggil; periode, komponen, dan periode terkunci bagi aktor memakai props
 * halaman Rencana Aksi yang sama. Capability tambah dinilai terhadap periode
 * yang dapat ditulis aktor sehingga klaim baru tidak ditawarkan saat semua
 * periode terkunci.
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
     * `$periodeDapatDitulis` berasal dari `IndexRencanaAksi` (efektif ∩ lingkup
     * koreksi − terkunci). Daftar kosong berarti tidak ada periode yang dapat
     * ditulis, sehingga capability tambah fail-closed.
     *
     * @param  list<string>  $periodeDapatDitulis
     * @return array<string, mixed>|null
     */
    public function handle(User $actor, string $id, array $periodeDapatDitulis): ?array
    {
        $header = RencanaAksi::findOrFail($id);
        $unitId = (string) $header->unit_id;
        if (! $this->resolver->allows($actor, PermissionCodes::KEGIATAN_READ, $unitId)) {
            return null;
        }

        // Capability di luar snapshot READ ONLY: gerbang klaim mengambil row lock (`FOR SHARE`) yang ditolak transaksi read-only.
        $can = [
            'tambah' => $periodeDapatDitulis !== []
                && $this->gerbang->boleh($actor, $header, $unitId, PermissionCodes::KEGIATAN_CREATE, $periodeDapatDitulis),
            // Pengelolaan klaim dan berkas per kegiatan berada di halaman kegiatan Rencana Aksi; tautan hanya bila izin ubah kegiatan ada.
            'kelola' => $this->resolver->allows($actor, PermissionCodes::KEGIATAN_UPDATE, $unitId),
        ];

        return DB::transaction(function () use ($header, $can): array {
            // Daftar, penanda, dan total dibaca dari satu snapshot; bila dipanggil di dalam transaksi lain, isolasi milik caller.
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }

            return [...$this->baca($header), 'can' => $can];
        });
    }

    /** @return array{daftar: list<array<string, mixed>>, total: array<string, mixed>} */
    private function baca(RencanaAksi $header): array
    {
        $daftar = KlaimKegiatan::query()
            ->join('kegiatan', 'kegiatan.id', '=', 'klaim_kegiatan.kegiatan_id')
            ->where('klaim_kegiatan.rencana_aksi_id', $header->id)
            ->orderBy('kegiatan.nama')
            ->orderBy('klaim_kegiatan.id')
            ->get(['klaim_kegiatan.id', 'klaim_kegiatan.kegiatan_id', 'klaim_kegiatan.komponen_id', 'klaim_kegiatan.arah_dampak',
                'klaim_kegiatan.catatan', 'klaim_kegiatan.sumber_klaim', 'kegiatan.periode_id', 'kegiatan.nama', 'kegiatan.status', 'kegiatan.anggaran', 'kegiatan.kegiatan_asal_id']);
        $ids = $daftar->pluck('kegiatan_id')->unique()->values()->all();
        // Jejak roll-over dua arah (Q41 §41.4 butir 2): asal dari lanjutan dan lanjutan pertama dari kegiatan yang ditunda.
        $asal = Kegiatan::whereIn('id', $daftar->pluck('kegiatan_asal_id')->filter()->unique()->values()->all())->get(['id', 'periode_id'])->keyBy('id');
        $lanjutan = Kegiatan::whereIn('kegiatan_asal_id', $ids)->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'kegiatan_asal_id', 'periode_id'])->keyBy('kegiatan_asal_id');
        $tautan = fn (?Kegiatan $kegiatan): ?array => $kegiatan ? ['id' => (string) $kegiatan->id, 'periode_id' => (string) $kegiatan->periode_id] : null;
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
                'asal' => $tautan($asal->get((string) $klaim->getAttribute('kegiatan_asal_id'))),
                'lanjutan' => $tautan($lanjutan->get((string) $klaim->kegiatan_id)),
            ])->values()->all(),
            'total' => $this->anggaran->perRencanaAksi($header),
        ];
    }
}
