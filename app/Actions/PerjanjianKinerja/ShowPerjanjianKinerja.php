<?php

namespace App\Actions\PerjanjianKinerja;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\Berkas;
use App\Models\RenstraPk;
use App\Models\User;

class ShowPerjanjianKinerja
{
    public function __construct(private EvaluateEvidence $evidence) {}

    /**
     * Detail PK beserta status jadwal tahunan (langsung atau fallback legacy) dan lampiran legal; tautan/isi teks lampiran
     * hanya dikirim bila aktor boleh mengunduh berkas. Hapus lampiran dikunci saat jadwal terkunci.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, RenstraPk $perjanjianKinerja): array
    {
        $perjanjianKinerja->load([
            'renstra',
            'creator:id,nama',
            'berkas.pengunggah:id,nama',
            'jadwalTahunan',
        ]);

        $jadwal = $perjanjianKinerja->resolveJadwalTahunan();
        $isJadwalTerkunci = $jadwal?->is_terkunci ?? false;
        $canReadBerkas = $user->can('downloadBerkas', $perjanjianKinerja);

        $pk = [
            'id' => $perjanjianKinerja->id,
            'renstra_id' => $perjanjianKinerja->renstra_id,
            'tahun' => $perjanjianKinerja->tahun,
            'nomor_pk' => $perjanjianKinerja->nomor_pk,
            'tanggal_pk' => $perjanjianKinerja->tanggal_pk?->format('Y-m-d'),
            'created_at' => $perjanjianKinerja->created_at?->toISOString(),
            'updated_at' => $perjanjianKinerja->updated_at?->toISOString(),
            'renstra' => $perjanjianKinerja->renstra ? [
                'id' => $perjanjianKinerja->renstra->id,
                'kode' => $perjanjianKinerja->renstra->kode,
                'nama' => $perjanjianKinerja->renstra->nama,
                'tahun_mulai' => $perjanjianKinerja->renstra->tahun_mulai,
                'tahun_selesai' => $perjanjianKinerja->renstra->tahun_selesai,
                'is_aktif' => (bool) $perjanjianKinerja->renstra->is_aktif,
            ] : null,
            'creator' => $perjanjianKinerja->creator ? [
                'id' => $perjanjianKinerja->creator->id,
                'nama' => $perjanjianKinerja->creator->nama,
            ] : null,
            'jadwal_tahunan' => $jadwal ? [
                'id' => $jadwal->id,
                'renstra_id' => $jadwal->renstra_id,
                'renstra_pk_id' => $jadwal->renstra_pk_id,
                'tahun' => $jadwal->tahun,
                'status' => $jadwal->status,
                'activated_at' => $jadwal->activated_at,
                'is_terkunci' => $jadwal->is_terkunci,
            ] : null,
            'berkas' => $perjanjianKinerja->berkas->map(function (Berkas $b) use ($canReadBerkas) {
                $item = [
                    'id' => $b->id,
                    'mode' => $b->mode,
                    'nama_asli' => $b->nama_asli,
                    'mime' => $b->mime,
                    'ukuran_bytes' => $b->ukuran_bytes,
                    'created_at' => $b->dibuat_pada?->toISOString() ?? $b->created_at?->toISOString(),
                    'pengunggah' => $b->pengunggah ? [
                        'id' => $b->pengunggah->id,
                        'nama' => $b->pengunggah->nama,
                    ] : null,
                ];

                if ($canReadBerkas) {
                    $item['tautan'] = $b->tautan;
                    $item['isi_teks'] = $b->isi_teks;
                }

                return $item;
            })->values()->all(),
        ];

        return [
            'pk' => $pk,
            'jadwal_status' => $jadwal?->status,
            'is_jadwal_aktif' => $jadwal?->status === 'aktif',
            'is_jadwal_terkunci' => $isJadwalTerkunci,
            'storageSettings' => $this->evidence->settings(),
            'can' => [
                'update' => $user->can('update', $perjanjianKinerja),
                'delete_berkas' => ! $isJadwalTerkunci && $user->can('deleteBerkas', $perjanjianKinerja),
                'read_berkas' => $canReadBerkas,
                'upload_berkas' => $user->can('uploadBerkas', $perjanjianKinerja),
            ],
        ];
    }
}
