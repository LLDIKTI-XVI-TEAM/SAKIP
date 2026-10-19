<?php

namespace App\Actions\JenisBerkas;

use App\Models\BuktiDukung;
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
        $contoh = BuktiDukung::where('berkasable_type', 'jenis_berkas')->current()->orderBy('created_at')
            ->get(['id', 'berkasable_id', 'nama_asli', 'ukuran_bytes', 'created_at'])->groupBy('berkasable_id');
        $jenisBerkasList->each(fn (JenisBerkas $jenis) => $jenis->setAttribute('contoh_berkas', ($contoh[$jenis->id] ?? collect())
            ->map(fn (BuktiDukung $b): array => ['id' => $b->id, 'nama_asli' => $b->nama_asli, 'ukuran_bytes' => $b->ukuran_bytes])->values()->all()));

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
