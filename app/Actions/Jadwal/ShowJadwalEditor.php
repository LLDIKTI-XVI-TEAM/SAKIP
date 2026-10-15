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
        $canActivate = $this->policy->activate($actor)->allowed();
        $canClose = $this->policy->close($actor)->allowed();
        if ($jadwal === null) {
            return ['jadwal' => null, 'can' => ['create' => $canCreate, 'update' => false, 'activate' => false, 'close' => false], 'read_only_reason' => null,
                'renstra_options' => $this->renstraOptions(), 'periode_options' => $this->periodeOptions()];
        }

        return DB::transaction(function () use ($actor, $jadwal, $canCreate, $canActivate, $canClose): array {
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
                // can.activate hanya izin; kalender read-only tetap dapat diaktifkan bila readiness server lolos.
                // can.close memuat status agar tombol tidak ditawarkan untuk jadwal yang pasti ditolak.
                'can' => ['create' => $canCreate, 'update' => $reason === null, 'activate' => $canActivate, 'close' => $canClose && $current->status === 'aktif'],
                'read_only_reason' => $reason,
                'renstra_options' => $reason === null ? $this->renstraOptions() : [],
                'periode_options' => $reason === null ? $this->periodeOptions() : []];
        });
    }

    /**
     * Pilihan dropdown tambah periode: hanya periode aktif, urut sesuai master.
     *
     * @return list<array<string, mixed>>
     */
    private function periodeOptions(): array
    {
        $fields = ['id', 'nama', 'urutan', 'aktif', 'is_nilai_akhir', 'revisi'];

        // shortcut: tanpa paginasi karena master periode global dan tidak bertambah per tahun (Data Model §2.14;
        // seed Triwulan I–IV), hanya yang aktif dikirim; ganti ke pencarian bila periode aktif melebihi ±50.
        return Periode::query()->select($fields)->where('aktif', true)->orderBy('urutan')->orderBy('id')->get()
            ->map(fn (Periode $periode): array => $periode->only($fields))->values()->all();
    }

    /**
     * Pilihan dropdown Renstra: hanya draft/aktif yang boleh menerima jadwal, dikirim saat form dapat diubah.
     *
     * @return list<array<string, mixed>>
     */
    private function renstraOptions(): array
    {
        $fields = ['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai'];

        // shortcut: tanpa paginasi karena Renstra berjangka lima tahun, hanya draft/aktif dikirim, dan rentang aktif
        // tidak boleh tumpang tindih; ganti ke pencarian bila pilihan melebihi ±50.
        return Renstra::query()->select($fields)->whereIn('status', ['draft', 'aktif'])
            ->orderByDesc('tahun_mulai')->orderBy('id')->get()
            ->map(fn (Renstra $renstra): array => $renstra->only($fields))->values()->all();
    }
}
