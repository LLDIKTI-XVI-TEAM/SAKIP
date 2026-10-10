<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\Gate;

/**
 * Props panel bukti pada halaman rencana aksi: persyaratan tahap
 * `rencana_aksi` beserta pemenuhannya, daftar bukti berlaku, dan capability
 * mutasi. Null bila aktor tidak berhak melihat bukti induk ini.
 */
class IndexBuktiRencanaAksi
{
    public function __construct(
        private readonly EvaluateEvidence $evaluator,
        private readonly GerbangBuktiRencanaAksi $gerbang,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(User $actor, string $id): ?array
    {
        $header = RencanaAksi::findOrFail($id);
        if (! Gate::forUser($actor)->allows('viewEvidence', $header)) {
            return null;
        }

        // Nama persyaratan dibaca dari relasi, bukan daftar persyaratan aktif, agar bukti untuk
        // persyaratan yang kemudian dinonaktifkan atau dipindah indikator tidak tampil sebagai lampiran bebas.
        $bukti = $header->buktiDukungs()->current()->with(['pengunggah:id,nama', 'jenisBerkas:id,nama'])->orderByDesc('created_at')->orderBy('id')->get();
        $persyaratan = $this->evaluator->untuk('rencana_aksi', (string) $header->indikator_id, $bukti);
        $settings = $this->evaluator->settings();

        return [
            'persyaratan' => $persyaratan,
            'ringkasan' => $this->evaluator->ringkasan($persyaratan),
            // `path` tidak pernah dikirim; unduhan lewat route berizin.
            'daftar' => $bukti->map(fn (BuktiDukung $b): array => [
                'id' => $b->id,
                'jenis_berkas_id' => $b->jenis_berkas_id,
                'nama_persyaratan' => $b->jenisBerkas?->nama,
                'mode' => $b->mode,
                'nama_asli' => $b->nama_asli,
                'mime' => $b->mime,
                'ukuran_bytes' => $b->ukuran_bytes,
                'tautan' => $b->tautan,
                'isi_teks' => $b->isi_teks,
                'download_url' => $b->mode === 'file' ? route('rencana-aksi.bukti.download', ['rencanaAksi' => $header->id, 'bukti' => $b->id]) : null,
                'pengunggah' => $b->pengunggah?->nama,
                'created_at' => $b->created_at?->toISOString(),
            ])->values()->all(),
            'unggahan' => $settings,
            // Izin + validasi bisnis (status, unit, arsip, jendela PIC), sama dengan gerbang tulis.
            'can' => [
                'upload' => $this->gerbang->bolehMutasi($actor, $header, PermissionCodes::BERKAS_UPLOAD),
                'delete' => $this->gerbang->bolehMutasi($actor, $header, PermissionCodes::BERKAS_DELETE),
            ],
        ];
    }
}
