<?php

namespace App\Actions\Kegiatan;

use App\Models\IndikatorKomponen;
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

    /** @return array<string, mixed> */
    public function handle(User $actor, Kegiatan $kegiatan): array
    {
        $unitId = (string) $kegiatan->unit_id;
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

        $komponen = IndikatorKomponen::whereIn('id', $klaim->pluck('komponen_id')->filter())->get(['id', 'kode', 'label'])
            ->mapWithKeys(fn (IndikatorKomponen $row): array => [(string) $row->id => "{$row->kode} — {$row->label}"]);
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
            $snapshot = $this->konteks->snapshotTerbaru((string) $header->jadwal_tahunan_id, (string) $header->indikator_id);
            $opsi[] = [
                'id' => (string) $header->id,
                'label' => $this->label($header),
                'komponen' => $snapshot === null ? [] : $this->konteks->komponen($snapshot)
                    ->map(fn (array $row): array => ['id' => $row['komponen_id'], 'nama' => "{$row['kode']} — {$row['label']}"])->values()->all(),
            ];
        }

        return [
            'daftar' => $klaim->map(function (KlaimKegiatan $item) use ($headers, $komponen, $actor, $perencanaan, $bolehUntuk): array {
                $header = $headers->get($item->rencana_aksi_id);

                return [
                    'id' => (string) $item->id,
                    'rencana_aksi_id' => (string) $item->rencana_aksi_id,
                    'rencana_aksi' => $header === null ? null : $this->label($header),
                    'status_alur' => $header?->status_alur,
                    'komponen_id' => $item->komponen_id,
                    'komponen' => $item->komponen_id === null ? null : $komponen->get((string) $item->komponen_id),
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
