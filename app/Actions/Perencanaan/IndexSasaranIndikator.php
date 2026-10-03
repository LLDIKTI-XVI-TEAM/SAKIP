<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
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
     *
     * @return array{renstras: mixed, selectedRenstraId: ?string, sasarans: mixed, units: mixed, regulasis: mixed, can: array<string, bool>}
     */
    public function handle(User $user, mixed $requestedRenstraId, bool $renstraParamPresent): array
    {
        $canReadRegulasi = $this->resolver->resolve($user, PermissionCodes::REGULASI_READ)->allowed;
        $canReadKomponen = $this->resolver->resolve($user, PermissionCodes::KOMPONEN_READ)->allowed;

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
                    'indikatorKinerjas' => function ($query) use ($canReadRegulasi, $canReadKomponen) {
                        $relations = ['unit:id,nama'];
                        if ($canReadKomponen) {
                            $relations['komponen'] = fn ($components) => $components->orderBy('urutan')->orderBy('id')
                                ->select(['id', 'indikator_id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'satuan', 'aktif']);
                        }
                        if ($canReadRegulasi) {
                            $relations[] = 'regulasi:id,jenis,nomor,tahun,tentang';
                        }
                        $query->orderBy('kode')->with($relations);
                    },
                ])
                ->get()
                ->map(function (SasaranStrategis $sasaran) use ($canReadRegulasi, $canReadKomponen) {
                    return [
                        'id' => $sasaran->id,
                        'renstra_id' => $sasaran->renstra_id,
                        'kode' => $sasaran->kode,
                        'deskripsi' => $sasaran->deskripsi,
                        'urutan' => $sasaran->urutan,
                        'updated_at' => $sasaran->updated_at?->toISOString(),
                        'indikator_kinerjas' => $sasaran->indikatorKinerjas->map(function (IndikatorKinerja $indikator) use ($canReadRegulasi, $canReadKomponen) {
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
                                'updated_at' => $indikator->updated_at?->toISOString(),
                                'created_by_role' => $indikator->created_by_role,
                                'komponen' => $canReadKomponen ? $indikator->komponen->map(fn ($item) => [
                                    'id' => $item->id, 'kode' => $item->kode, 'label' => $item->label,
                                    'peran' => $item->peran, 'bobot' => (string) $item->bobot,
                                    'satuan' => $item->satuan, 'urutan' => $item->urutan, 'aktif' => $item->aktif,
                                ])->values() : null,
                                'regulasi' => ($canReadRegulasi && $indikator->regulasi) ? [
                                    'id' => $indikator->regulasi->id,
                                    'jenis' => $indikator->regulasi->jenis,
                                    'nomor' => $indikator->regulasi->nomor,
                                    'tahun' => $indikator->regulasi->tahun,
                                    'tentang' => $indikator->regulasi->tentang,
                                ] : null,
                            ];
                        }),
                    ];
                });
        }

        $units = Unit::where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama']);

        $regulasis = $canReadRegulasi
            ? Regulasi::where('aktif', true)
                ->orderByDesc('tahun')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang'])
            : [];

        return [
            'renstras' => $renstras,
            'selectedRenstraId' => $selectedRenstraId,
            'sasarans' => $sasarans,
            'units' => $units,
            'regulasis' => $regulasis,
            'can' => [
                'sasaran_create' => $this->resolver->resolve($user, PermissionCodes::SASARAN_CREATE)->allowed,
                'sasaran_update' => $this->resolver->resolve($user, PermissionCodes::SASARAN_UPDATE)->allowed,
                'sasaran_delete' => $this->resolver->resolve($user, PermissionCodes::SASARAN_DELETE)->allowed,
                'indikator_create' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_CREATE)->allowed,
                'indikator_read' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_READ)->allowed,
                'indikator_update' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_UPDATE)->allowed,
                'indikator_delete' => $this->resolver->resolve($user, PermissionCodes::INDIKATOR_DELETE)->allowed,
                'regulasi_read' => $canReadRegulasi,
                'komponen_read' => $canReadKomponen,
                'komponen_create' => $this->resolver->resolve($user, 'komponen:create')->allowed,
            ],
        ];
    }
}
