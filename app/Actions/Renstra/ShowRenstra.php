<?php

namespace App\Actions\Renstra;

use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;

class ShowRenstra
{
    /**
     * Detail Renstra beserta regulasi dan lampiran yang boleh dibaca aktor, token state untuk aksi lifecycle,
     * peringatan kelengkapan sasaran/indikator, dan capability transisi status sesuai status saat ini.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, Renstra $renstra): array
    {
        $canViewAttachments = $user->can('viewAttachment', $renstra);
        $dapatBacaRegulasi = $user->can('viewAny', Regulasi::class);

        $relations = [
            'pembuat:id,nama',
        ];

        if ($dapatBacaRegulasi) {
            $relations[] = 'regulasi';
        }

        if ($canViewAttachments) {
            $relations['berkas'] = fn ($query) => $query->with('pengunggah:id,nama')->orderByDesc('created_at');
        }

        $renstra->load($relations);

        $canDeleteAttachment = $user->can('deleteAttachment', $renstra);
        $hasBerkas = $canViewAttachments && ! $canDeleteAttachment && $renstra->berkas()->exists();

        $detail = [
            'id' => $renstra->id,
            'kode' => $renstra->kode,
            'nama' => $renstra->nama,
            'tahun_mulai' => $renstra->tahun_mulai,
            'tahun_selesai' => $renstra->tahun_selesai,
            'status' => $renstra->status,
            'is_aktif' => $renstra->is_aktif,
            'deskripsi' => $renstra->deskripsi,
            'dasar_hukum' => $renstra->dasar_hukum,
            'regulasi_id' => $dapatBacaRegulasi ? $renstra->regulasi_id : null,
            'pembuat' => $renstra->pembuat ? [
                'id' => $renstra->pembuat->id,
                'nama' => $renstra->pembuat->nama,
            ] : null,
            'created_at' => $renstra->created_at?->toISOString(),
            'updated_at' => $renstra->updated_at?->toISOString(),
            'berkas' => $canViewAttachments
                ? $renstra->berkas->map(fn (Berkas $berkas): array => [
                    'id' => $berkas->id,
                    'mode' => $berkas->mode,
                    'nama_asli' => $berkas->nama_asli,
                    'mime' => $berkas->mime,
                    'ukuran_bytes' => $berkas->ukuran_bytes,
                    'tautan' => $berkas->tautan,
                    'isi_teks' => $berkas->isi_teks,
                    'download_url' => $berkas->mode === 'file'
                        ? route('renstra.berkas.download', [$renstra, $berkas])
                        : null,
                    'pengunggah' => $berkas->pengunggah ? [
                        'id' => $berkas->pengunggah->id,
                        'nama' => $berkas->pengunggah->nama,
                    ] : null,
                ])->values()->all()
                : [],
        ];

        if ($dapatBacaRegulasi) {
            $detail['regulasi'] = $renstra->regulasi ? [
                'id' => $renstra->regulasi->id,
                'jenis' => $renstra->regulasi->jenis,
                'nomor' => $renstra->regulasi->nomor,
                'tahun' => $renstra->regulasi->tahun,
                'tentang' => $renstra->regulasi->tentang,
            ] : null;
        }

        $canChangeStatus = $user->can('update', $renstra);
        $warnings = [];
        if (! $renstra->sasaranStrategis()->exists()) {
            $warnings[] = 'Renstra belum memiliki Sasaran Strategis. Aktivasi tetap dapat dilanjutkan.';
        } elseif ($renstra->sasaranStrategis()->whereDoesntHave('indikatorKinerjas')->exists()) {
            $warnings[] = 'Ada Sasaran Strategis yang belum memiliki Indikator. Aktivasi tetap dapat dilanjutkan.';
        }

        return [
            'renstra' => $detail,
            'expected_state' => $renstra->stateToken(),
            'lifecycle' => [
                'warnings' => $warnings,
                'nonactivation_blocked' => $canChangeStatus && $renstra->status === Renstra::STATUS_AKTIF
                    && $renstra->jadwalTahunan()->where('status', 'aktif')->exists(),
            ],
            'can' => [
                'activate' => $canChangeStatus && $renstra->status === Renstra::STATUS_DRAFT,
                'deactivate' => $canChangeStatus && $renstra->status === Renstra::STATUS_AKTIF,
                'archive' => $canChangeStatus && $renstra->status === Renstra::STATUS_NONAKTIF,
                'update' => $canChangeStatus && in_array($renstra->status, [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF], true),
                'delete' => $user->can('delete', $renstra)
                    && ($canDeleteAttachment || ($canViewAttachments && ! $hasBerkas)),
                'deleteAttachment' => $canDeleteAttachment,
            ],
        ];
    }
}
