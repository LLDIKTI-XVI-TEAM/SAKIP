<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Validation\ValidationException;

/**
 * Props panel klaim pada halaman Kegiatan: klaim tahap RA milik kegiatan dan
 * pilihan RA yang dapat diklaim. Pilihan dan hak hapus memakai gerbang yang
 * sama dengan writer agar UI tidak menawarkan aksi yang pasti ditolak; writer
 * tetap memeriksa ulang di bawah lock.
 */
class DaftarKlaimKegiatan
{
    public function __construct(
        private readonly GerbangKlaimKegiatan $gerbang,
        private readonly TambahKlaimKegiatan $cocok,
        private readonly KonteksBekuRencanaAksi $konteks,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly PermissionResolver $resolver,
    ) {}

    /**
     * Null bila aktor tidak boleh membaca RA unit kegiatan (deny unit menang);
     * klaim selalu satu unit dengan RA-nya sehingga satu pemeriksaan cukup.
     *
     * @return array<string, mixed>|null
     */
    public function handle(User $actor, Kegiatan $kegiatan): ?array
    {
        $unitId = (string) $kegiatan->unit_id;
        if (! $this->resolver->allows($actor, PermissionCodes::RENCANA_AKSI_READ, $unitId)) {
            return null;
        }
        $periode = [(string) $kegiatan->periode_id];
        $perencanaan = $this->jendela->jalurPerencanaan($this->resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_UPDATE, $unitId));
        $boleh = [];
        $bolehUntuk = function (RencanaAksi $header) use (&$boleh, $actor, $unitId, $periode): bool {
            return $boleh[$header->id] ??= $this->gerbang->boleh($actor, $header, $unitId, PermissionCodes::KEGIATAN_UPDATE, $periode);
        };

        $klaim = KlaimKegiatan::where('kegiatan_id', $kegiatan->id)->where('sumber_klaim', 'rencana_aksi')->orderBy('id')->get();
        $headers = RencanaAksi::with('indikator:id,kode,nama')
            ->whereIn('id', $klaim->pluck('rencana_aksi_id'))
            ->orWhere(fn ($query) => $query->where('unit_id', $unitId)->where('tahun', $kegiatan->tahun)->whereIn('status_alur', RencanaAksi::STATUS_DAPAT_DISUNTING))
            ->orderBy('id')->get()->keyBy('id');

        // Label komponen dari snapshot beku RA yang sama dengan validasi dan pengajuan, bukan master berjalan.
        $komponenRa = [];
        $komponenUntuk = function (RencanaAksi $header) use (&$komponenRa): array {
            if (! array_key_exists($header->id, $komponenRa)) {
                $snapshot = $this->konteks->snapshotTerbaru((string) $header->jadwal_tahunan_id, (string) $header->indikator_id);
                $komponenRa[$header->id] = $snapshot === null ? [] : $this->konteks->komponen($snapshot)
                    ->mapWithKeys(fn (array $row): array => [$row['komponen_id'] => "{$row['kode']} — {$row['label']}"])->all();
            }

            return $komponenRa[$header->id];
        };
        // Kombinasi RA/komponen yang sudah diklaim tidak ditawarkan lagi; '' mewakili klaim tanpa komponen.
        $terpakai = $klaim->groupBy('rencana_aksi_id')->map(fn ($grup) => $grup->map(fn (KlaimKegiatan $k): string => (string) $k->komponen_id)->all());
        $opsi = [];
        foreach ($headers as $header) {
            if ($header->unit_id !== $kegiatan->unit_id || ! $bolehUntuk($header)) {
                continue;
            }
            try {
                $this->cocok->pastikanCocok($header, $kegiatan, null);
            } catch (ValidationException) {
                continue;
            }
            $sudah = $terpakai->get($header->id, []);
            $komponen = collect($komponenUntuk($header))->reject(fn (string $nama, string $id): bool => in_array($id, $sudah, true))
                ->map(fn (string $nama, string $id): array => ['id' => $id, 'nama' => $nama])->values()->all();
            $tanpaKomponen = ! in_array('', $sudah, true);
            if ($komponen === [] && ! $tanpaKomponen) {
                continue;
            }
            $opsi[] = ['id' => (string) $header->id, 'label' => $this->label($header), 'komponen' => $komponen, 'tanpa_komponen' => $tanpaKomponen];
        }

        return [
            'daftar' => $klaim->map(function (KlaimKegiatan $item) use ($headers, $komponenUntuk, $actor, $perencanaan, $bolehUntuk): array {
                $header = $headers->get($item->rencana_aksi_id);

                return [
                    'id' => (string) $item->id,
                    'rencana_aksi_id' => (string) $item->rencana_aksi_id,
                    'rencana_aksi' => $header === null ? null : $this->label($header),
                    'status_alur' => $header?->status_alur,
                    'komponen_id' => $item->komponen_id,
                    'komponen' => $item->komponen_id === null || $header === null ? null : ($komponenUntuk($header)[(string) $item->komponen_id] ?? null),
                    'arah_dampak' => $item->arah_dampak,
                    'catatan' => $item->catatan,
                    'can' => ['hapus' => $header !== null && ((string) $item->created_by === (string) $actor->id || $perencanaan) && $bolehUntuk($header)],
                ];
            })->values()->all(),
            'opsiRencanaAksi' => $opsi,
            'can' => ['tambah' => $opsi !== []],
        ];
    }

    private function label(RencanaAksi $header): string
    {
        return trim(($header->indikator?->kode ?? '').' — '.($header->indikator?->nama ?? ''), ' —');
    }
}
