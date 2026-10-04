<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kinerja\IndikatorPerhitunganService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadIndicatorEditor
{
    public function __construct(private PermissionResolver $resolver, private IndikatorPerhitunganService $formula) {}

    /**
     * Membaca baseline editor koheren. Semua writer definisi mengunci induk eksklusif;
     * kunci bersama di sini menjaga data dan revisi pada satu keadaan commit.
     * Kelanjutan dibatasi 50 row dan wajib memakai revisi halaman pertama.
     */
    public function handle(User $actor, string $id, bool $parentSurface, int $page = 1, ?string $expected = null): array
    {
        abort_unless($this->resolver->resolve($actor, $parentSurface ? 'indikator:read' : 'komponen:read')->allowed, 403);

        return DB::transaction(function () use ($actor, $id, $parentSurface, $page, $expected) {
            $indikator = IndikatorKinerja::whereKey($id)->sharedLock()->firstOrFail();
            $revision = $indikator->updated_at?->toISOString() ?? $indikator->created_at?->toISOString();
            if ($page > 1 && ($expected === null || Carbon::parse($expected)->toISOString() !== $revision)) {
                throw ValidationException::withMessages(['konflik' => 'Konfigurasi telah berubah. Muat ulang seluruh konfigurasi.'])->status(409);
            }
            $canRead = $this->resolver->resolve($actor, 'komponen:read')->allowed;
            $metadata = $indikator->only(['id', 'kode', 'nama', 'satuan', 'tipe_perhitungan', 'presisi']);
            $metadata['unit_nama'] = $indikator->unit()->value('nama');
            if ($parentSurface) {
                $metadata = array_merge($metadata, $indikator->only(['sasaran_strategis_id', 'unit_id', 'definisi_operasional', 'arah', 'desimal_tampilan', 'wajib_catatan', 'status', 'created_by_role', 'jenis_agregasi']));
                $metadata['regulasi_id'] = $this->resolver->resolve($actor, 'regulasi:read')->allowed ? $indikator->regulasi_id : null;
            }
            $total = $canRead ? $indikator->komponen()->count() : 0;
            $rows = $canRead ? $indikator->komponen()->reorder()->orderBy('urutan')->orderBy('id')->offset(($page - 1) * 50)->limit(50)->get() : null;
            $contract = $validation = null;
            if ($canRead && $page === 1) {
                $indikator->setRelation('komponen', $indikator->komponen()->reorder()->orderBy('urutan')->orderBy('id')->get());
                $contract = $this->formula->getFormulaContract($indikator);
                $contract['komponen_list'] = $rows->where('aktif', true)->map(fn ($row) => $row->only(['id', 'kode', 'label', 'satuan', 'peran', 'bobot', 'urutan', 'aktif']))->values()->all();
                $validation = ['is_valid' => $contract['is_valid'], 'messages' => $contract['messages']];
            }

            return [
                'indikator' => $metadata, 'revision' => $revision,
                'komponen' => $rows?->map(fn ($row) => $row->only(['id', 'kode', 'label', 'satuan', 'peran', 'bobot', 'urutan', 'aktif']))->values()->all(),
                'formulaContract' => $contract, 'validation' => $validation,
                'pagination' => ['page' => $page, 'per_page' => 50, 'total' => $total, 'next_page' => $page * 50 < $total ? $page + 1 : null, 'complete' => $page * 50 >= $total],
                'can' => ['create' => $this->resolver->resolve($actor, 'komponen:create')->allowed,
                    'update' => $this->resolver->resolve($actor, 'komponen:update')->allowed,
                    'delete' => $this->resolver->resolve($actor, 'komponen:delete')->allowed,
                    'update_indikator' => $this->resolver->resolve($actor, 'indikator:update')->allowed],
            ];
        });
    }
}
