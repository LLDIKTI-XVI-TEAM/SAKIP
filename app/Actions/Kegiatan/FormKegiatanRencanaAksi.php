<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Props halaman ubah kegiatan dalam konteks Rencana Aksi pengklaimnya
 * (Q74 §74.1). `FormKegiatan` dievaluasi lebih dulu agar otorisasi per unit dan
 * audit penolakan tetap menjadi miliknya; baru setelah itu izin baca Rencana
 * Aksi ditegakkan Policy yang sama dengan halaman RA, sehingga identitas,
 * klaim, dan berkas header tidak terkirim kepada aktor yang tidak berhak
 * membacanya. Kegiatan juga dipastikan benar-benar diklaim RA tersebut,
 * sehingga props kegiatan tidak dapat dibuka lewat UUID Rencana Aksi lain.
 */
class FormKegiatanRencanaAksi
{
    public function __construct(
        private readonly FormKegiatan $form,
        private readonly DaftarKlaimKegiatan $klaim,
        private readonly DaftarBerkasPerencanaanKegiatan $berkas,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, RencanaAksi $rencanaAksi, Kegiatan $kegiatan): array
    {
        $props = $this->form->handle($actor, $kegiatan);

        // Penolakan tidak memuat identitas RA: gate berjalan sebelum relasi, klaim, dan berkas dimuat.
        Gate::forUser($actor)->authorize('view', $rencanaAksi);

        abort_unless(
            KlaimKegiatan::where('kegiatan_id', $kegiatan->id)->where('rencana_aksi_id', $rencanaAksi->id)->exists(),
            404,
        );

        // Satu header dimuat bersama indikatornya; tidak ada query per baris.
        $rencanaAksi->loadMissing('indikator:id,kode');

        return $props + [
            // Identitas RA untuk tautan kembali dan breadcrumb kode indikator.
            'rencanaAksi' => [
                'id' => (string) $rencanaAksi->id,
                'tahun' => $rencanaAksi->tahun,
                'kode' => $rencanaAksi->indikator?->kode,
            ],
            'klaim' => $this->klaim->handle($actor, $kegiatan),
            'berkasPerencanaan' => $this->berkas->handle($actor, $kegiatan),
        ];
    }
}
