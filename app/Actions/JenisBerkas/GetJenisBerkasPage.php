<?php

namespace App\Actions\JenisBerkas;

use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Eloquent\Collection;

class GetJenisBerkasPage
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * @return array{
     *     jenisBerkasList: Collection<int, JenisBerkas>,
     *     indikators: Collection<int, IndikatorKinerja>,
     *     unggahanAktif: bool,
     *     can: array{create: bool, update: bool, delete: bool, pengaturan_update: bool}
     * }
     */
    public function handle(User $actor): array
    {
        $actor = $actor->fresh();
        abort_unless($this->resolver->allows($actor, 'jenis_berkas:read'), 403);

        $jenisBerkasList = JenisBerkas::with('indikator')
            ->orderBy('tahap')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $referencedIndikatorIds = JenisBerkas::whereNotNull('indikator_id')->pluck('indikator_id')->all();

        $indikators = IndikatorKinerja::where('status', 'aktif')
            ->orWhereIn('id', $referencedIndikatorIds)
            ->select('id', 'kode', 'nama', 'status')
            ->orderBy('kode')
            ->get();

        $isUnggahanAktif = filter_var(
            Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        return [
            'jenisBerkasList' => $jenisBerkasList,
            'indikators' => $indikators,
            'unggahanAktif' => $isUnggahanAktif,
            'can' => [
                'create' => $this->resolver->allows($actor, 'jenis_berkas:create'),
                'update' => $this->resolver->allows($actor, 'jenis_berkas:update'),
                'delete' => $this->resolver->allows($actor, 'jenis_berkas:delete'),
                'pengaturan_update' => $this->resolver->allows($actor, 'pengaturan:update'),
            ],
        ];
    }
}
