<?php

namespace App\Actions\PerjanjianKinerja;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use App\Models\RenstraPkVersiTarget;
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
     * hanya dikirim bila aktor boleh mengunduh berkas. Hapus lampiran dikunci saat jadwal terkunci. Linimasa versi PK
     * terlihat oleh semua pembaca; data form revisi hanya dikirim bila aktor boleh merevisi.
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
        $alasanWajib = $perjanjianKinerja->dipakaiJadwalAktif();
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
            'berkas' => $perjanjianKinerja->berkas->map(fn (Berkas $b): array => $this->berkas($b, $canReadBerkas))->values()->all(),
        ];
        $canRevisi = $user->can('revisi', $perjanjianKinerja);

        return [
            'pk' => $pk,
            'versi' => $this->versi($perjanjianKinerja, $canReadBerkas),
            'revisi' => $canRevisi && $jadwal ? $this->revisi($perjanjianKinerja, $jadwal) : null,
            'target_pk' => $this->targetPk($perjanjianKinerja),
            'jadwal_status' => $jadwal?->status,
            'is_jadwal_aktif' => $jadwal?->status === 'aktif',
            'is_jadwal_terkunci' => $isJadwalTerkunci,
            'alasan_wajib' => $alasanWajib,
            'snapshot_url' => $jadwal && $alasanWajib && $user->can('viewAny', $jadwal)
                ? '/jadwal/'.$jadwal->id.'/snapshot' : null,
            'storageSettings' => $this->evidence->settings(),
            'can' => [
                'update' => $user->can('update', $perjanjianKinerja),
                'delete_berkas' => ! $isJadwalTerkunci && $user->can('deleteBerkas', $perjanjianKinerja),
                'read_berkas' => $canReadBerkas,
                'upload_berkas' => $user->can('uploadBerkas', $perjanjianKinerja),
                'update_target' => $this->permissions->allows($user, PermissionCodes::INDIKATOR_READ)
                    && $this->permissions->allows($user, PermissionCodes::TARGET_UPDATE),
                'revisi' => $canRevisi,
            ],
        ];
    }

    /**
     * Linimasa versi PK. Versi 1 memakai lampiran induk yang id-nya dibekukan saat revisi pertama; perubahan target
     * dihitung dari target tersimpan versi N terhadap N-1, sehingga koreksi snapshot yang diserap versi baru ikut tampil.
     *
     * @return list<array<string, mixed>>
     */
    private function versi(RenstraPk $pk, bool $canReadBerkas): array
    {
        $pk->load(['versi.berkas.pengunggah:id,nama', 'versi.periodeMulai:id,nama', 'versi.penyetuju:id,nama', 'versi.target.indikator:id,kode,desimal_tampilan']);
        $sebelumnya = null;

        return $pk->versi->map(function (RenstraPkVersi $v) use ($pk, $canReadBerkas, &$sebelumnya): array {
            $berkas = $v->nomor_versi === 1 ? $pk->berkas->whereIn('id', $v->berkas_awal_ids ?? []) : $v->berkas;
            $perubahan = $sebelumnya === null ? [] : $this->perubahanTarget($sebelumnya, $v);
            $sebelumnya = $v;

            return [
                'id' => $v->id,
                'nomor_versi' => $v->nomor_versi,
                'nomor_surat' => $v->nomor_surat,
                'tanggal_surat' => $v->tanggal_surat?->toDateString(),
                'periode_mulai' => $v->periodeMulai?->only(['id', 'nama']),
                'alasan' => $v->alasan,
                'disetujui_oleh' => $v->penyetuju?->nama,
                'disetujui_pada' => $v->disetujui_pada?->toISOString(),
                'berkas' => $berkas->map(fn (Berkas $b): array => $this->berkas($b, $canReadBerkas))->values()->all(),
                'perubahan_target' => $perubahan,
            ];
        })->values()->all();
    }

    /**
     * Indikator yang nilainya berbeda antara dua versi berurutan, termasuk yang hanya ada di salah satunya.
     *
     * @return list<array{indikator_id: string, kode: string, lama: string|null, baru: string|null, desimal_tampilan: int}>
     */
    private function perubahanTarget(RenstraPkVersi $lama, RenstraPkVersi $baru): array
    {
        $peta = fn (RenstraPkVersi $versi) => $versi->target->mapWithKeys(fn (RenstraPkVersiTarget $t): array => [$t->indikator_id => TargetTahunanDecimal::normalize($t->getRawOriginal('nilai'))]);
        $nilaiLama = $peta($lama);
        $nilaiBaru = $peta($baru);
        $indikator = $lama->target->merge($baru->target)->mapWithKeys(fn (RenstraPkVersiTarget $t): array => [$t->indikator_id => $t->indikator]);

        return $indikator->keys()->filter(fn (string $id): bool => $nilaiLama->get($id) !== $nilaiBaru->get($id))
            ->map(fn (string $id): array => ['indikator_id' => $id, 'kode' => $indikator[$id]->kode, 'lama' => $nilaiLama->get($id),
                'baru' => $nilaiBaru->get($id), 'desimal_tampilan' => (int) $indikator[$id]->desimal_tampilan])
            ->sortBy('kode')->values()->all();
    }

    /**
     * Data form revisi. Nilai dasar, token, gerbang target, dan periode mulai memakai aturan yang sama dengan Action
     * revisi; periode mulai hanya pratinjau karena nilai final ditetapkan saat simpan.
     *
     * @return array<string, mixed>
     */
    private function revisi(RenstraPk $pk, JadwalTahunan $jadwal): array
    {
        $dasar = $jadwal->snapshotDasar()->load('periodeMulai');
        $kode = IndikatorKinerja::whereIn('id', $dasar->keys())->pluck('kode', 'id');
        $periode = $jadwal->periodePertamaBelumDilaporkan();
        $pengukuran = [];
        foreach ($periode ? $dasar : [] as $id => $snapshot) {
            $mulai = SetujuiRevisiPerjanjianKinerja::periodeMulaiIndikator($periode->periode, $snapshot);
            $daftar = $jadwal->pengukuranBelumDisahkanSebelum($id, $mulai)->with('periode:id,nama')->get();
            if ($daftar->isNotEmpty()) {
                $pengukuran[$id] = $daftar->map(fn (PengukuranKinerja $row): array => ['periode_id' => $row->periode_id, 'periode_nama' => $row->periode->nama,
                    ...$row->only(['status_alur', 'nomor_versi'])])->all();
            }
        }
        $penghalang = SetujuiRevisiPerjanjianKinerja::penghalangTarget($jadwal, $pk->renstra)
            ?? ($periode === null ? SetujuiRevisiPerjanjianKinerja::PESAN_TERLAPOR : null);

        return [
            'expected_nomor_versi' => (int) $pk->versi->max('nomor_versi'),
            'expected_dasar' => JadwalTahunan::tokenDasar($dasar),
            'periode_mulai' => $periode ? ['id' => $periode->periode_id, 'nama' => $periode->periode->nama] : null,
            'dapat_ubah_target' => $penghalang === null,
            'alasan_tidak_dapat_ubah_target' => $penghalang,
            'target' => $dasar->map(fn (JadwalSnapshot $s, string $id): array => ['indikator_id' => $id, 'kode' => $kode[$id], 'nama' => $s->nama,
                'satuan' => $s->satuan, 'nilai' => TargetTahunanDecimal::normalize($s->getRawOriginal('target')), 'desimal_tampilan' => (int) $s->desimal_tampilan])
                ->sortBy('kode')->values()->all(),
            'pengukuran_belum_disahkan' => $pengukuran,
        ];
    }

    /**
     * Lampiran PK atau versi PK; tautan dan isi teks hanya dikirim bila aktor boleh mengunduh berkas.
     *
     * @return array<string, mixed>
     */
    private function berkas(Berkas $b, bool $canReadBerkas): array
    {
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
