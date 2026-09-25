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
use Inertia\Inertia;
use Inertia\Response;

class IndexSasaranIndikator extends Controller
{
    public function __invoke(Request $request, PermissionResolver $resolver): Response
    {
        Gate::authorize('viewAny', IndikatorKinerja::class);

        /** @var User $user */
        $user = $request->user();

        $renstras = Renstra::orderByDesc('is_aktif')
            ->orderByDesc('tahun_mulai')
            ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif']);

        $selectedRenstraId = $request->query('renstra_id');
        if (! $selectedRenstraId && $renstras->isNotEmpty()) {
            $selectedRenstraId = $renstras->firstWhere('is_aktif', true)?->id ?? $renstras->first()->id;
        }

        $sasarans = [];
        if ($selectedRenstraId) {
            $sasarans = SasaranStrategis::where('renstra_id', $selectedRenstraId)
                ->orderBy('urutan')
                ->orderBy('kode')
                ->with([
                    'indikatorKinerjas' => function ($query) {
                        $query->orderBy('kode')
                            ->with([
                                'unit:id,nama',
                                'regulasi:id,jenis,nomor,tahun,tentang',
                            ]);
                    },
                ])
                ->get()
                ->map(function (SasaranStrategis $sasaran) {
                    return [
                        'id' => $sasaran->id,
                        'renstra_id' => $sasaran->renstra_id,
                        'kode' => $sasaran->kode,
                        'deskripsi' => $sasaran->deskripsi,
                        'urutan' => $sasaran->urutan,
                        'indikator_kinerjas' => $sasaran->indikatorKinerjas->map(function (IndikatorKinerja $indikator) {
                            return [
                                'id' => $indikator->id,
                                'sasaran_strategis_id' => $indikator->sasaran_strategis_id,
                                'regulasi_id' => $indikator->regulasi_id,
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
                                'regulasi' => $indikator->regulasi ? [
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

        $regulasis = Regulasi::where('aktif', true)
            ->orderByDesc('tahun')
            ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang']);

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
            ],
        ]);
    }
}
