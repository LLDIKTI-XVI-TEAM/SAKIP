<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tambah kegiatan dari halaman Rencana Aksi per periode (Q42 §42.1 butir 2):
 * satu aksi membuat kegiatan (unit × periode RA) sekaligus klaimnya dengan
 * `sumber_klaim = rencana_aksi`. Salinan ke periode lain pada RA yang sama
 * (Q49 §49.2) menjadi pasangan kegiatan + klaim tersendiri; berkas tidak
 * disalin. Seluruh pasangan dibuat atau dibatalkan bersama.
 */
class TambahKegiatanRencanaAksi
{
    /** Atribut rencana yang ikut disalin; tanggal rencana melekat pada periode asal sehingga tidak disalin. */
    private const FIELD_SALIN = ['nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi'];

    public function __construct(
        private readonly GerbangKlaimKegiatan $gerbang,
        private readonly TambahKlaimKegiatan $klaim,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Hasil validasi `TambahKegiatanRencanaAksiRequest`.
     * @return list<Kegiatan>
     */
    public function handle(User $actor, string $headerId, array $data): array
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;
        $periodeUtama = strtolower((string) $data['periode_id']);
        $periodeIds = array_values(array_unique([$periodeUtama, ...array_map(fn ($id): string => strtolower((string) $id), $data['salin_ke_periode_ids'] ?? [])]));

        try {
            return DB::transaction(function () use ($actor, $headerId, $data, $periodeUtama, $periodeIds, &$dasarIzin): array {
                $konteks = $this->gerbang->kunci($actor, $headerId, null, PermissionCodes::KEGIATAN_CREATE, $periodeIds, $dasarIzin);
                $header = $konteks['header'];
                $pengunci = $konteks['pengunci'];
                $aktif = Periode::whereIn('id', $periodeIds)->where('aktif', true)->sharedLock()->pluck('id')->map(fn ($id): string => (string) $id);
                if ($aktif->count() !== count($periodeIds)) {
                    throw ValidationException::withMessages(['periode_id' => 'Master periode tidak tersedia atau sudah nonaktif.']);
                }

                $komponenId = isset($data['komponen_id']) ? strtolower((string) $data['komponen_id']) : null;
                $dibuat = [];
                foreach ($periodeIds as $periodeId) {
                    $rencana = $periodeId === $periodeUtama ? Arr::only($data, [...self::FIELD_SALIN, 'tanggal_rencana']) : Arr::only($data, self::FIELD_SALIN);
                    $kegiatan = Kegiatan::create($rencana + ['unit_id' => $header->unit_id, 'tahun' => $header->tahun, 'periode_id' => $periodeId,
                        'status' => 'rencana', 'created_by' => $pengunci->id]);
                    $this->klaim->pastikanCocok($header, $kegiatan, $komponenId, 'periode_id');
                    $klaim = KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'komponen_id' => $komponenId,
                        'arah_dampak' => (string) $data['arah_dampak'], 'catatan' => $data['catatan'] ?? null, 'sumber_klaim' => 'rencana_aksi',
                        'created_by' => $pengunci->id, 'created_at' => now()]);

                    $this->audit->catat(actor: $pengunci, tindakan: 'kegiatan.buat', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
                        nilaiBaru: $kegiatan->rencanaAttributes(), alasan: 'Pencatatan kegiatan dari halaman rencana aksi.', dasarIzin: $dasarIzin);
                    $this->audit->catat(actor: $pengunci, tindakan: 'klaim_kegiatan.tambah', objekTipe: 'klaim_kegiatan', objekId: (string) $klaim->id,
                        nilaiBaru: TambahKlaimKegiatan::atributAudit($klaim), alasan: 'Klaim kegiatan terhadap rencana aksi.', dasarIzin: $dasarIzin);
                    $dibuat[] = $kegiatan;
                }

                return $dibuat;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && RencanaAksi::whereKey($headerId)->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'kegiatan.buat_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: $headerId,
                    nilaiBaru: ['periode_ids' => $periodeIds],
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Penambahan kegiatan dari rencana aksi ditolak.'),
                    dasarIzin: $dasarIzin,
                );
            }

            throw $exception;
        }
    }
}
