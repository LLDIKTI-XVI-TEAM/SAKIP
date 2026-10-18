<?php

namespace App\Actions\Kegiatan;

use App\Models\BuktiDukung;
use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use App\Support\PermissionCodes;

/**
 * Panel berkas tahap perencanaan kegiatan pada form ubah kegiatan: persyaratan
 * global ditambah khusus indikator RA pengklaim, pemenuhannya, berkas berlaku,
 * contoh berkas, dan capability yang memakai aturan gerbang tulis yang sama.
 * Pemanggil sudah memastikan akses detail kegiatan (FormKegiatan, Q54).
 */
class DaftarBerkasPerencanaanKegiatan
{
    public function __construct(
        private readonly PersyaratanPerencanaanKegiatan $persyaratan,
        private readonly GerbangBerkasKegiatan $gerbang,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, Kegiatan $kegiatan): array
    {
        $pengklaim = RencanaAksi::whereIn('id', GerbangBerkasKegiatan::headerPengklaim((string) $kegiatan->id))->get();
        $bukti = PersyaratanPerencanaanKegiatan::buktiBerlaku((string) $kegiatan->id)->orderBy('created_at')->get();
        $evaluasi = $this->persyaratan->evaluasi($kegiatan, $pengklaim->pluck('indikator_id')->map(fn ($id): string => (string) $id)->unique()->values()->all(), $bukti);
        $contoh = BuktiDukung::where('berkasable_type', 'jenis_berkas')->whereIn('berkasable_id', array_column($evaluasi, 'id'))->current()
            ->orderBy('created_at')->get()->groupBy('berkasable_id');
        $ditutup = GerbangBerkasKegiatan::tahunDitutup((int) $kegiatan->tahun);
        $bacaTertutup = $this->gerbang->bacaTertutup($actor, $kegiatan);
        $alasan = fn (string $izin, bool $hapus): ?string => $this->gerbang->periksaIzin($actor, $kegiatan, $izin)['tolak'] !== null
            ? 'Izin mengubah berkas kegiatan tidak tersedia.'
            : $this->gerbang->pelanggaranBisnis($kegiatan, $pengklaim, $hapus, $ditutup)[1] ?? null;

        return [
            'persyaratan' => array_map(fn (array $item): array => $item + [
                'berkas' => $bacaTertutup ? [] : $bukti->where('jenis_berkas_id', $item['id'])->map(fn (BuktiDukung $b): array => [
                    ...$b->only(['id', 'mode', 'nama_asli', 'ukuran_bytes', 'tautan', 'isi_teks', 'menggantikan_id']), 'created_at' => $b->created_at?->toIso8601String(),
                ])->values()->all(),
                'contoh' => ($contoh[$item['id']] ?? collect())->map(fn (BuktiDukung $b): array => $b->only(['id', 'nama_asli']))->values()->all(),
            ], $evaluasi),
            'alasan_tolak_unggah' => $alasan(PermissionCodes::BERKAS_UPLOAD, false),
            'alasan_tolak_hapus' => $alasan(PermissionCodes::BERKAS_DELETE, true),
        ];
    }
}
