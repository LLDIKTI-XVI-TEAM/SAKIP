<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\Perencanaan\IndikatorPresenter;
use Illuminate\Support\Str;

class IndexSasaranIndikator
{
    public function __construct(private readonly IndikatorPresenter $presenter) {}

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
        $can = $this->presenter->capabilities($user);

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
                    'indikatorKinerjas' => fn ($query) => $query->orderBy('kode')->with($this->presenter->indikatorRelations($can['regulasi_read'])),
                ])
                ->get();
            $pjEfektif = $this->presenter->pjEfektif(
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
                    fn (IndikatorKinerja $indikator) => $this->presenter->presentIndikator($indikator, $can['regulasi_read'], $pjEfektif->get($indikator->id)?->pic),
                ),
            ]);
        }

        return [
            'renstras' => $renstras,
            'selectedRenstraId' => $selectedRenstraId,
            'sasarans' => $sasarans,
            ...$this->presenter->formOptions($can['regulasi_read']),
            'can' => $can,
        ];
    }
}
