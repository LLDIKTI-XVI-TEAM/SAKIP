<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PreviewPengukuran
{
    public function __construct(private CalculatePengukuran $calculator) {}

    /** Pratinjau memakai snapshot dan izin editor, tanpa menulis draf atau versi pengajuan. */
    public function handle(User $actor, string $id, array $data): array
    {
        $p = PengukuranKinerja::with('jadwalSnapshot.komponen')->findOrFail($id);
        Gate::forUser($actor)->authorize('view', $p);
        Gate::forUser($actor)->authorize('update', $p);
        $snapshot = $p->jadwalSnapshot;
        if ($snapshot->tipe_perhitungan === 'manual') {
            throw ValidationException::withMessages(['komponen' => 'Pratinjau komponen hanya tersedia untuk indikator nonmanual.']);
        }
        $definitions = $snapshot->komponen->map(fn ($c) => $c->only(['komponen_id', 'peran', 'bobot']))->all();
        $values = array_column($data['komponen'] ?? [], 'nilai', 'komponen_id');
        try {
            return $this->calculator->handle($snapshot->tipe_perhitungan, $snapshot->presisi, $definitions, $values, null);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['komponen' => $exception->getMessage()]);
        }
    }
}
