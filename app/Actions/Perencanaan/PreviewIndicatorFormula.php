<?php

namespace App\Actions\Perencanaan;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kinerja\IndikatorPerhitunganService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PreviewIndicatorFormula
{
    public function __construct(private PermissionResolver $resolver, private IndikatorPerhitunganService $formula, private CalculatePengukuran $calculator) {}

    /** Pratinjau master tersimpan; tidak membuat snapshot atau menulis hasil pengukuran. */
    public function handle(User $actor, string $id, array $data): array
    {
        abort_unless($this->resolver->resolve($actor, 'komponen:read')->allowed, 403);

        return DB::transaction(function () use ($id, $data) {
            $indikator = IndikatorKinerja::whereKey($id)->sharedLock()->firstOrFail();
            $revision = $indikator->updated_at?->toISOString();
            if (Carbon::parse($data['expected_updated_at'])->toISOString() !== $revision) {
                throw ValidationException::withMessages(['konflik' => 'Konfigurasi telah berubah. Muat ulang konfigurasi sebelum simulasi.'])->status(409);
            }
            $indikator->setRelation('komponen', $indikator->komponen()->get());
            $validation = $this->formula->validateDefinisiKomponen($indikator);
            if (! $validation['is_valid']) {
                throw ValidationException::withMessages(['komponen' => $validation['messages']]);
            }
            $definitions = $indikator->komponen->where('aktif', true)->map(fn ($row) => ['komponen_id' => $row->id, 'peran' => $row->peran, 'bobot' => (string) $row->bobot])->values()->all();
            if (array_diff(array_keys($data['values']), array_column($definitions, 'komponen_id'))) {
                throw ValidationException::withMessages(['values' => 'Nilai memuat komponen yang bukan anggota aktif konfigurasi ini.']);
            }
            try {
                $result = $this->calculator->handle($indikator->tipe_perhitungan, $indikator->presisi, $definitions, $data['values'], $data['nilai_manual'] ?? null);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['values' => str_replace('snapshot', 'konfigurasi', $exception->getMessage())]);
            }

            return ['indikator_id' => $id, 'revision' => $revision, ...$result];
        });
    }
}
