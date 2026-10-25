<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\RevisiTargetRenstra;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\Perencanaan\IndikatorPresenter;
use App\Support\TargetRenstraState;
use App\Support\TargetTahunanDecimal;
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
            ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif', 'status']);

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
                    'indikatorKinerjas' => fn ($query) => $query->orderBy('kode')->with([...$this->presenter->indikatorRelations($can['regulasi_read']), 'targetRenstra:id,indikator_id,tahun,nilai']),
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
                    fn (IndikatorKinerja $indikator) => [
                        ...$this->presenter->presentIndikator($indikator, $can['regulasi_read'], $pjEfektif->get($indikator->id)?->pic),
                        ...$this->targetRenstra($indikator, $selectedRenstra),
                    ],
                ),
            ]);
        }

        return [
            'renstras' => $renstras,
            'selectedRenstraId' => $selectedRenstraId,
            'sasarans' => $sasarans,
            ...$this->presenter->formOptions($can['regulasi_read']),
            'can' => $can,
            'revisi_target' => $this->revisiTarget($can, $selectedRenstra),
        ];
    }

    /**
     * Draf revisi hanya untuk pemegang izin revisi; pembaca lain tetap melihat nilai berlaku saja. Sel draf dikunci per
     * indikator lalu tahun, sama dengan bentuk payload autosave.
     *
     * @param  array<string, bool>  $can
     * @return array{id: string, versi: int, justifikasi: ?string, dibuat_oleh: ?string, diubah_pada: ?string, nilai: array<string, array<int, ?string>>}|null
     */
    private function revisiTarget(array $can, ?Renstra $renstra): ?array
    {
        if ($renstra?->status !== Renstra::STATUS_AKTIF || ! $can['indikator_read'] || ! $can['target_update']) {
            return null;
        }
        $draf = RevisiTargetRenstra::with(['nilai', 'pembuat:id,nama'])->where('renstra_id', $renstra->id)->where('status', RevisiTargetRenstra::STATUS_DRAF)->first();
        if ($draf === null) {
            return null;
        }
        $nilai = [];
        foreach ($draf->nilai as $sel) {
            $nilai[$sel->indikator_id][$sel->tahun] = TargetTahunanDecimal::normalize($sel->nilai_baru);
        }

        return ['id' => $draf->id, 'versi' => $draf->versi, 'justifikasi' => $draf->justifikasi, 'dibuat_oleh' => $draf->pembuat?->nama,
            'diubah_pada' => $draf->updated_at?->toISOString(), 'nilai' => $nilai];
    }

    /**
     * Hanya halaman daftar yang mengirim target Renstra, agar payload detail indikator tidak berubah. Token dihitung dengan
     * values/token yang sama dengan Action simpan sehingga cocok selama state tidak berubah.
     *
     * @return array{target_renstra: list<array{tahun: int, nilai: ?string}>, target_renstra_state: string}
     */
    private function targetRenstra(IndikatorKinerja $indikator, Renstra $renstra): array
    {
        $values = TargetRenstraState::values($indikator->targetRenstra);
        $rows = [];
        foreach ($values as $tahun => $nilai) {
            $rows[] = ['tahun' => $tahun, 'nilai' => $nilai];
        }

        return ['target_renstra' => $rows, 'target_renstra_state' => TargetRenstraState::token($indikator, $renstra, $values)];
    }
}
