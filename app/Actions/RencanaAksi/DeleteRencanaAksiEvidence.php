<?php

namespace App\Actions\RencanaAksi;

use App\Models\Berkas;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRencanaAksiEvidence
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Menghapus (soft delete) bukti dukung Rencana Aksi dengan guard imutabilitas dan audit log.
     *
     * @throws ValidationException
     */
    public function handle(RencanaAksi $rencanaAksi, Berkas $berkas, string $alasan, User $actor): void
    {
        if ($rencanaAksi->isDisahkan()) {
            throw ValidationException::withMessages([
                'rencana_aksi' => 'Bukti dukung tidak dapat dihapus karena Rencana Aksi telah disahkan.',
            ]);
        }

        if ($berkas->berkasable_id !== $rencanaAksi->id || ! in_array($berkas->berkasable_type, ['rencana_aksi', RencanaAksi::class], true)) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas bukan merupakan lampiran bukti dukung dari Rencana Aksi ini.',
            ]);
        }

        $sanitizedAlasan = trim($alasan);
        if ($sanitizedAlasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penghapusan bukti dukung wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($rencanaAksi, $berkas, $sanitizedAlasan, $actor): void {
            /** @var RencanaAksi $lockedRa */
            $lockedRa = RencanaAksi::where('id', $rencanaAksi->id)->lockForUpdate()->firstOrFail();

            if ($lockedRa->isDisahkan()) {
                throw ValidationException::withMessages([
                    'rencana_aksi' => 'Bukti dukung tidak dapat dihapus karena Rencana Aksi telah disahkan.',
                ]);
            }

            /** @var Berkas|null $lockedBerkas */
            $lockedBerkas = Berkas::where('id', $berkas->id)
                ->where('berkasable_id', $lockedRa->id)
                ->whereIn('berkasable_type', ['rencana_aksi', RencanaAksi::class])
                ->whereNull('dihapus_pada')
                ->lockForUpdate()
                ->first();

            if (! $lockedBerkas) {
                throw ValidationException::withMessages([
                    'berkas' => 'Bukti dukung sudah dihapus atau tidak ditemukan.',
                ]);
            }

            $nilaiLama = [
                'mode' => $lockedBerkas->mode,
                'jenis_berkas_id' => $lockedBerkas->jenis_berkas_id,
                'rencana_aksi_id' => $lockedRa->id,
            ];

            if ($lockedBerkas->mode === 'file') {
                $nilaiLama['nama_asli'] = $lockedBerkas->nama_asli;
                $nilaiLama['mime'] = $lockedBerkas->mime;
                $nilaiLama['ukuran_bytes'] = $lockedBerkas->ukuran_bytes;
            } elseif ($lockedBerkas->mode === 'tautan') {
                $nilaiLama['tautan'] = $lockedBerkas->tautan;
            } else {
                $nilaiLama['panjang_karakter'] = mb_strlen((string) $lockedBerkas->isi_teks);
            }

            $lockedBerkas->dihapus_oleh = $actor->id;
            $lockedBerkas->dihapus_pada = now();
            $lockedBerkas->save();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus',
                objekTipe: 'berkas',
                objekId: $lockedBerkas->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: ['dihapus_pada' => $lockedBerkas->dihapus_pada->toIso8601String()],
                alasan: $sanitizedAlasan,
            );
        });
    }
}
