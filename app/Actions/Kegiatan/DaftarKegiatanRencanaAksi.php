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
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Props daftar kegiatan per periode untuk panel Rencana Aksi dan halaman
 * kegiatan Rencana Aksi (Q42 §42.1): kegiatan yang diklaim RA (tahap RA dan
 * pengukuran) beserta anggaran, penanda kegiatan bersama, total (Q42 §42.2),
 * kandidat kegiatan unit+tahun yang belum diklaim dan bukan kegiatan lanjutan
 * (jalur penautan kegiatan lama), capability tambah, capability kelola, dan
 * capability tautkan. Akses halaman sudah diputuskan pemanggil; periode,
 * komponen, dan periode terkunci bagi aktor memakai props halaman Rencana Aksi
 * yang sama. Capability tambah dinilai terhadap periode yang dapat ditulis
 * aktor sehingga klaim baru tidak ditawarkan saat semua periode terkunci.
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
     * ditulis, sehingga capability tambah dan tautkan fail-closed.
     *
     * `can.tautkan` memakai gerbang bisnis klaim, bukan resolver permission
     * saja: gerbang yang sama dengan penambahan klaim tahap RA menuntut izin
     * ubah RA + izin ubah kegiatan pada unitnya, status RA yang dapat disunting,
     * serta jendela tulis periode. Capability dinilai sekali per halaman, bukan
     * per baris daftar.
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
            // Pengelolaan klaim dan berkas per kegiatan berada di halaman kegiatan Rencana Aksi.
            'kelola' => $this->resolver->allows($actor, PermissionCodes::KEGIATAN_UPDATE, $unitId),
            // Penautan memakai endpoint klaim tahap RA, sehingga tunduk pada gerbang bisnis yang sama:
            // izin ubah RA tujuan + izin ubah kegiatan, status RA, dan jendela tulis periode.
            'tautkan' => $periodeDapatDitulis !== []
                && $this->gerbang->boleh($actor, $header, $unitId, PermissionCodes::KEGIATAN_UPDATE, $periodeDapatDitulis),
        ];

        return DB::transaction(function () use ($header, $can): array {
            // Daftar, penanda, dan total dibaca dari satu snapshot; bila dipanggil di dalam transaksi lain, isolasi milik caller.
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }

            return [...$this->baca($header), 'can' => $can];
        });
    }

    /**
     * Daftar, total, dan kandidat penautan dihitung sekali per halaman di dalam
     * snapshot yang sama, bukan per baris daftar.
     *
     * @return array{daftar: list<array<string, mixed>>, total: array<string, mixed>, kegiatan_tanpa_klaim: list<array<string, mixed>>}
     */
    private function baca(RencanaAksi $header): array
    {
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
            // Jalur penautan kegiatan existing yang belum punya klaim apa pun.
            'kegiatan_tanpa_klaim' => $this->kegiatanTanpaKlaim($header),
        ];
    }

    /**
     * Kegiatan unit dan tahun header yang belum diklaim RA mana pun dan bukan
     * kegiatan lanjutan (`kegiatan_asal_id` null). Kegiatan lanjutan diklaim
     * pada tahap pengukuran, bukan sebagai klaim tahap RA (Q41 §41.4 butir 2),
     * sehingga tidak boleh ditawarkan sebagai kandidat penautan.
     *
     * Satu query dengan join periode untuk urutan dan nama periode; nama
     * periode ikut diproyeksikan agar tidak ada query per baris. Dibatasi 50
     * baris agar payload halaman tetap wajar.
     *
     * @return list<array{id: string, nama: string, periode_id: string, status: string, kegiatan_asal_id: string|null, periode_nama: string}>
     */
    private function kegiatanTanpaKlaim(RencanaAksi $header): array
    {
        return Kegiatan::query()
            ->join('periode', 'periode.id', '=', 'kegiatan.periode_id')
            ->where('kegiatan.unit_id', (string) $header->unit_id)
            ->where('kegiatan.tahun', (int) $header->tahun)
            ->whereNull('kegiatan.kegiatan_asal_id')
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('klaim_kegiatan')
                ->whereColumn('klaim_kegiatan.kegiatan_id', 'kegiatan.id'))
            ->orderBy('periode.urutan')
            ->orderBy('periode.id')
            ->orderBy('kegiatan.nama')
            ->orderBy('kegiatan.id')
            ->limit(50)
            ->get(['kegiatan.id', 'kegiatan.nama', 'kegiatan.periode_id', 'kegiatan.status', 'kegiatan.kegiatan_asal_id', 'periode.nama as periode_nama'])
            ->map(fn (Kegiatan $kegiatan): array => [
                'id' => (string) $kegiatan->id,
                'nama' => (string) $kegiatan->nama,
                'periode_id' => (string) $kegiatan->periode_id,
                'status' => (string) $kegiatan->status,
                'kegiatan_asal_id' => $kegiatan->kegiatan_asal_id === null ? null : (string) $kegiatan->kegiatan_asal_id,
                'periode_nama' => (string) $kegiatan->getAttribute('periode_nama'),
            ])->values()->all();
    }
}
