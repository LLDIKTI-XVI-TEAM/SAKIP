<?php

namespace App\Actions\PerjanjianKinerja;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\RenstraPk;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\DeviasiTargetRenstra;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;

class ShowPerjanjianKinerja
{
    public function __construct(private EvaluateEvidence $evidence, private PermissionResolver $permissions) {}

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
                    'keterangan' => $b->keterangan,
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
            'target_pk' => $this->targetPk($perjanjianKinerja),
            'jadwal_status' => $jadwal?->status,
            'is_jadwal_aktif' => $jadwal?->status === 'aktif',
            'is_jadwal_terkunci' => $isJadwalTerkunci,
            'alasan_wajib' => $perjanjianKinerja->dipakaiJadwalAktif(),
            'storageSettings' => $this->evidence->settings(),
            'can' => [
                'update' => $user->can('update', $perjanjianKinerja),
                'delete_berkas' => ! $isJadwalTerkunci && $user->can('deleteBerkas', $perjanjianKinerja),
                'read_berkas' => $canReadBerkas,
                'upload_berkas' => $user->can('uploadBerkas', $perjanjianKinerja),
                'update_target' => $this->permissions->allows($user, PermissionCodes::INDIKATOR_READ)
                    && $this->permissions->allows($user, PermissionCodes::TARGET_UPDATE),
            ],
        ];
    }

    /**
     * Baseline (data pendukung) dan target PK per sasaran untuk indikator yang ikut gerbang target aktivasi jadwal.
     *
     * @return list<array<string, mixed>>
     */
    private function targetPk(RenstraPk $pk): array
    {
        $sasaran = SasaranStrategis::where('renstra_id', $pk->renstra_id)->orderBy('urutan')->orderBy('kode')
            ->with(['indikatorKinerjas' => fn ($query) => $query->where('status', IndikatorKinerja::STATUS_AKTIF)
                ->where('tahun_mulai_berlaku', '<=', $pk->tahun)->orderBy('kode')])
            ->get()->filter(fn (SasaranStrategis $s): bool => $s->indikatorKinerjas->isNotEmpty());
        $indikatorIds = $sasaran->flatMap(fn (SasaranStrategis $s) => $s->indikatorKinerjas->modelKeys());
        $targets = TargetKinerja::whereIn('indikator_kinerja_id', $indikatorIds)->where('tahun', $pk->tahun)->get()->keyBy('indikator_kinerja_id');
        $targetRenstra = TargetRenstra::whereIn('indikator_id', $indikatorIds)->where('tahun', $pk->tahun)->get()->keyBy('indikator_id');

        return $sasaran->map(fn (SasaranStrategis $s): array => [
            'id' => $s->id,
            'kode' => $s->kode,
            'deskripsi' => $s->deskripsi,
            'indikator' => $s->indikatorKinerjas->map(function (IndikatorKinerja $i) use ($targets, $targetRenstra, $pk): array {
                $nilai = TargetTahunanState::values($targets->get($i->id));
                $renstra = TargetTahunanDecimal::normalize($targetRenstra->get($i->id)?->nilai);

                return [
                    'id' => $i->id,
                    'kode' => $i->kode,
                    'nama' => $i->nama,
                    'satuan' => $i->satuan,
                    'desimal_tampilan' => $i->desimal_tampilan,
                    'arah' => $i->arah,
                    ...$nilai,
                    'target_renstra' => $renstra,
                    'status_deviasi' => DeviasiTargetRenstra::status($i->arah, $nilai['target_tahunan'], $renstra),
                    'read_only_reason' => TargetTahunanState::readOnlyReason(['indikator' => $i, 'renstra' => $pk->renstra]),
                ];
            })->values()->all(),
        ])->values()->all();
    }
}
