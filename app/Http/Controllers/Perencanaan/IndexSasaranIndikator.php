<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Models\IndikatorKinerja;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IndexSasaranIndikator extends Controller
{
    public function __invoke(Request $request, PermissionResolver $resolver): Response
    {
        Gate::authorize('viewAny', IndikatorKinerja::class);

        /** @var User $user */
        $user = $request->user();

        $canReadRegulasi = $resolver->allows($user, PermissionCodes::REGULASI_READ);

        $renstras = Renstra::orderByDesc('is_aktif')
            ->orderByDesc('tahun_mulai')
            ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif']);

        $requestedRenstraId = $request->query('renstra_id');
        $selectedRenstra = null;

        if ($request->has('renstra_id') && $requestedRenstraId !== null && $requestedRenstraId !== '') {
            if (! is_string($requestedRenstraId) || ! Str::isUuid($requestedRenstraId)) {
                abort(404, 'Renstra tidak ditemukan.');
            }
            $selectedRenstra = $renstras->firstWhere('id', $requestedRenstraId);
            if (! $selectedRenstra) {
                abort(404, 'Renstra tidak ditemukan.');
            }
        } elseif (! $request->has('renstra_id') || $requestedRenstraId === null || $requestedRenstraId === '') {
            if ($renstras->isNotEmpty()) {
                $selectedRenstra = $renstras->firstWhere('is_aktif', true) ?? $renstras->first();
            }
        }

        $selectedRenstraId = $selectedRenstra?->id;

        $sasarans = [];
        if ($selectedRenstraId) {
            $sasarans = SasaranStrategis::where('renstra_id', $selectedRenstraId)
                ->orderBy('urutan')
                ->orderBy('kode')
                ->with([
                    'indikatorKinerjas' => function ($query) use ($canReadRegulasi) {
                        $relations = ['unit:id,nama'];
                        if ($canReadRegulasi) {
                            $relations[] = 'regulasi:id,jenis,nomor,tahun,tentang';
                        }
                        $query->orderBy('kode')->with($relations);
                    },
                ])
                ->get()
                ->map(function (SasaranStrategis $sasaran) use ($canReadRegulasi) {
                    return [
                        'id' => $sasaran->id,
                        'renstra_id' => $sasaran->renstra_id,
                        'kode' => $sasaran->kode,
                        'deskripsi' => $sasaran->deskripsi,
                        'urutan' => $sasaran->urutan,
                        'indikator_kinerjas' => $sasaran->indikatorKinerjas->map(function (IndikatorKinerja $indikator) use ($canReadRegulasi) {
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
                                'is_aktif' => $indikator->is_aktif,
                                'created_by_role' => $indikator->created_by_role,
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

        return Inertia::render('Perencanaan/SasaranIndikator/Index', [
            'renstras' => $renstras,
            'selectedRenstraId' => $selectedRenstraId,
            'sasarans' => $sasarans,
            'units' => $units,
            'regulasis' => $regulasis,
            'can' => [
                'sasaran_create' => $resolver->allows($user, PermissionCodes::SASARAN_CREATE),
                'sasaran_update' => $resolver->allows($user, PermissionCodes::SASARAN_UPDATE),
                'sasaran_delete' => $resolver->allows($user, PermissionCodes::SASARAN_DELETE),
                'indikator_create' => $resolver->allows($user, PermissionCodes::INDIKATOR_CREATE),
                'indikator_read' => $resolver->allows($user, PermissionCodes::INDIKATOR_READ),
                'indikator_update' => $resolver->allows($user, PermissionCodes::INDIKATOR_UPDATE),
                'indikator_delete' => $resolver->allows($user, PermissionCodes::INDIKATOR_DELETE),
                'regulasi_read' => $canReadRegulasi,
                'komponen_read' => $resolver->allows($user, PermissionCodes::KOMPONEN_READ),
            ],
        ]);
    }
}
