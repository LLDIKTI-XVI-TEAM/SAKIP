<?php

namespace App\Actions\Jadwal;

use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\User;
use App\Policies\JadwalTahunanPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShowJadwalEditor
{
    public function __construct(private readonly JadwalTahunanPolicy $policy) {}

    /** Parent, seluruh pilihan, metadata, dan token dibaca bersama; tidak menjadi partial/lazy props. @return array<string, mixed> */
    public function handle(User $actor, ?JadwalTahunan $jadwal = null): array
    {
        ($jadwal ? $this->policy->viewAny($actor) : $this->policy->create($actor))->authorize();
        $canCreate = $this->policy->create($actor)->allowed();
        if ($jadwal === null) {
            return ['jadwal' => null, 'can' => ['create' => $canCreate, 'update' => false], 'read_only_reason' => null];
        }

        return DB::transaction(function () use ($actor, $jadwal, $canCreate): array {
            Periode::lockConfiguration();
            $original = JadwalTahunan::findOrFail($jadwal->id);
            $renstra = Renstra::whereKey($original->renstra_id)->sharedLock()->firstOrFail();
            $current = JadwalTahunan::whereKey($jadwal->id)->sharedLock()->firstOrFail();
            if ($current->renstra_id !== $renstra->id) {
                throw ValidationException::withMessages(['jadwal' => 'Jadwal sudah berubah. Muat kembali data terbaru.']);
            }
            $current->load('periode.periode');
            $reason = match (true) {
                ! in_array($renstra->status, ['draft', 'aktif'], true) => 'Renstra nonaktif atau diarsipkan. Kalender hanya dapat dibaca.',
                $current->status !== 'draft' || $current->is_terkunci => 'Jadwal aktif, ditutup, atau pernah diaktifkan hanya dapat dibaca.',
                ! $this->policy->update($actor)->allowed() => 'Anda tidak memiliki izin mengubah jadwal.',
                default => null,
            };
            $attributes = $current->draftAttributes();
            $metadata = $current->periode->keyBy('periode_id');
            $windows = collect($attributes['periode'])->map(function (array $window) use ($metadata): array {
                /** @var PeriodeJadwal $child */
                $child = $metadata->get($window['periode_id']);

                return [...$window, ...$child->periode->only(['nama', 'urutan', 'aktif', 'is_nilai_akhir']), 'periode_revisi' => $child->periode->revisi];
            })->sortBy('urutan')->values()->all();

            return ['jadwal' => [...$attributes, 'renstra' => $renstra->only(['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai']), 'periode' => $windows],
                'can' => ['create' => $canCreate, 'update' => $reason === null], 'read_only_reason' => $reason];
        });
    }
}
