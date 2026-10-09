<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IndexSasaranIndikator
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Menyusun payload halaman Sasaran & Indikator untuk satu Renstra.
     * Otorisasi halaman (`viewAny`) tetap di controller; di sini hanya
     * `regulasi_id`/`regulasi` disembunyikan (null) dan katalog
     * regulasi dikosongkan bila pembaca tidak berwenang `regulasi:read`,
     * serta `renstra_id` non-UUID ditolak 404 sebelum menyentuh query UUID.
     * Nama PJ efektif hanya dikirim kepada pemegang `penanggung_jawab:update`,
     * sama dengan gerbang halaman Penanggung Jawab; selain itu bernilai null.
     *
     * @return array{renstras: mixed, selectedRenstraId: ?string, sasarans: mixed, units: mixed, regulasis: mixed, can: array<string, bool>}
     */
    public function handle(User $user, mixed $requestedRenstraId, bool $renstraParamPresent): array
    {
        $can = $this->capabilities($user);

        $renstras = Renstra::orderByDesc('is_aktif')
            ->orderByDesc('tahun_mulai')
            ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif']);

        $selectedRenstra = null;

        if ($renstraParamPresent) {
            if (! is_string($requestedRenstraId) || trim($requestedRenstraId) === '' || ! Str::isUuid($requestedRenstraId)) {
                abort(404, 'Renstra tidak ditemukan.');
            }
            $selectedRenstra = $renstras->firstWhere('id', $requestedRenstraId);
            if (! $selectedRenstra) {
                abort(404, 'Renstra tidak ditemukan.');
            }
        } elseif ($renstras->isNotEmpty()) {
            // Fallback hanya ketika parameter renstra_id tidak dikirim sama sekali
            $selectedRenstra = $renstras->firstWhere('is_aktif', true) ?? $renstras->first();
        }

        $selectedRenstraId = $selectedRenstra?->id;

        $sasarans = [];
        if ($selectedRenstraId) {
            $sasarans = SasaranStrategis::where('renstra_id', $selectedRenstraId)
                ->orderBy('urutan')
                ->orderBy('kode')
                ->with([
                    'indikatorKinerjas' => fn ($query) => $query->orderBy('kode')->with($this->indikatorRelations($can['regulasi_read'])),
                ])
                ->get();
            $pjEfektif = $this->pjEfektif(
                $can['penanggung_jawab_update'],
                $sasarans->flatMap(fn (SasaranStrategis $sasaran) => $sasaran->indikatorKinerjas->pluck('id'))->all(),
            );
            $sasarans = $sasarans->map(fn (SasaranStrategis $sasaran) => [
                'id' => $sasaran->id,
                'renstra_id' => $sasaran->renstra_id,
                'kode' => $sasaran->kode,
                'deskripsi' => $sasaran->deskripsi,
                'urutan' => $sasaran->urutan,
                'updated_at' => $sasaran->updated_at?->toISOString(),
                'indikator_kinerjas' => $sasaran->indikatorKinerjas->map(
                    fn (IndikatorKinerja $indikator) => $this->presentIndikator($indikator, $can['regulasi_read'], $pjEfektif->get($indikator->id)?->pic),
                ),
            ]);
        }

        return [
            'renstras' => $renstras,
            'selectedRenstraId' => $selectedRenstraId,
            'sasarans' => $sasarans,
            ...$this->formOptions($can['regulasi_read']),
            'can' => $can,
        ];
    }

    /**
     * Capability halaman Sasaran & Indikator dan detail indikator; seluruhnya dari resolver kanonis.
     *
     * @return array<string, bool>
     */
    public function capabilities(User $user): array
    {
        return [
            'penanggung_jawab_update' => $this->resolver->resolve($user, 'penanggung_jawab:update')->allowed,
            'sasaran_create' => $this->resolver->resolve($user, PermissionCodes::SASARAN_CREATE)->allowed,
            'sasaran_update' => $this->resolver->resolve($user, PermissionCodes::SASARAN_UPDATE)->allowed,
            'sasaran_delete' => $this->resolver->resolve($user, PermissionCodes::SASARAN_DELETE)->allowed,
            'indikator_create' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_CREATE)->allowed,
            'indikator_read' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_READ)->allowed,
            'indikator_update' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_UPDATE)->allowed,
            'indikator_delete' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_DELETE)->allowed,
            'regulasi_read' => $this->resolver->resolve($user, PermissionCodes::REGULASI_READ)->allowed,
            'komponen_read' => $this->resolver->resolve($user, PermissionCodes::KOMPONEN_READ)->allowed,
            'komponen_create' => $this->resolver->resolve($user, 'komponen:create')->allowed,
            'komponen_update' => $this->resolver->resolve($user, 'komponen:update')->allowed,
            'komponen_delete' => $this->resolver->resolve($user, 'komponen:delete')->allowed,
        ];
    }

    /** @return list<string> */
    public function indikatorRelations(bool $canReadRegulasi): array
    {
        return $canReadRegulasi ? ['unit:id,nama', 'regulasi:id,jenis,nomor,tahun,tentang'] : ['unit:id,nama'];
    }

    /**
     * PJ efektif pada tanggal bisnis hari ini, satu query untuk seluruh indikator
     * (pemenang per indikator ditentukan scope temporal bersama). Kosong bila aktor
     * tidak berwenang mengelola PJ.
     *
     * @param  list<string>  $indikatorIds
     * @return Collection<string, PenugasanIndikator>
     */
    public function pjEfektif(bool $allowed, array $indikatorIds): Collection
    {
        if (! $allowed || $indikatorIds === []) {
            return collect();
        }

        return PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())
            ->whereIn('indikator_id', $indikatorIds)
            ->with('pic:id,nama,status')
            ->get()
            ->keyBy('indikator_id');
    }

    /**
     * Bentuk indikator yang sama untuk daftar dan detail; regulasi disembunyikan tanpa `regulasi:read`.
     *
     * @return array<string, mixed>
     */
    public function presentIndikator(IndikatorKinerja $indikator, bool $canReadRegulasi, ?User $pic): array
    {
        return [
            'id' => $indikator->id,
            'sasaran_strategis_id' => $indikator->sasaran_strategis_id,
            'regulasi_id' => $canReadRegulasi ? $indikator->regulasi_id : null,
            'kode' => $indikator->kode,
            'nama' => $indikator->nama,
            'definisi_operasional' => $indikator->definisi_operasional,
            'satuan' => $indikator->satuan,
            'unit_id' => $indikator->unit_id,
            'unit_nama' => $indikator->unit?->nama,
            'arah' => $indikator->arah,
            'tipe_perhitungan' => $indikator->tipe_perhitungan,
            'presisi' => $indikator->presisi,
            'desimal_tampilan' => $indikator->desimal_tampilan,
            'wajib_catatan' => $indikator->wajib_catatan,
            'jenis_agregasi' => $indikator->jenis_agregasi,
            'status' => $indikator->status,
            'tahun_mulai_berlaku' => $indikator->tahun_mulai_berlaku,
            'updated_at' => $indikator->updated_at?->toISOString(),
            'created_by_role' => $indikator->created_by_role,
            'penanggung_jawab' => $pic ? ['nama' => $pic->nama, 'status' => $pic->status] : null,
            'regulasi' => ($canReadRegulasi && $indikator->regulasi) ? [
                'id' => $indikator->regulasi->id,
                'jenis' => $indikator->regulasi->jenis,
                'nomor' => $indikator->regulasi->nomor,
                'tahun' => $indikator->regulasi->tahun,
                'tentang' => $indikator->regulasi->tentang,
            ] : null,
        ];
    }

    /**
     * Opsi form indikator: unit aktif dan regulasi aktif (kosong tanpa `regulasi:read`).
     *
     * @return array{units: mixed, regulasis: mixed}
     */
    public function formOptions(bool $canReadRegulasi): array
    {
        return [
            'units' => Unit::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']),
            'regulasis' => $canReadRegulasi
                ? Regulasi::where('aktif', true)->orderByDesc('tahun')->get(['id', 'jenis', 'nomor', 'tahun', 'tentang'])
                : [],
        ];
    }
}
