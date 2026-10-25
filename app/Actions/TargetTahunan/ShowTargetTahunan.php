<?php

namespace App\Actions\TargetTahunan;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Policies\TargetKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\DeviasiTargetRenstra;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShowTargetTahunan
{
    public function __construct(private readonly TargetKinerjaPolicy $policy, private readonly ResolveLockedActor $lockedActor, private readonly PermissionResolver $resolver) {}

    /** Satu pasangan, metadata/capability dan token berasal dari state terkunci yang sama. @return array<string, mixed> */
    public function handle(User $actor, string $indikatorId, int $tahun, ?string $renstraId = null): array
    {
        $this->policy->view($actor)->authorize();

        return DB::transaction(function () use ($actor, $indikatorId, $tahun, $renstraId): array {
            $read = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_READ);
            if (! $read['keputusan']->allowed) {
                throw new AuthorizationException('Izin membaca indikator tidak lagi berlaku.');
            }
            $write = $this->lockedActor->handle($actor, PermissionCodes::TARGET_UPDATE);
            $state = TargetTahunanState::lock($indikatorId, $tahun, renstraId: $renstraId);
            $reason = TargetTahunanState::readOnlyReason($state) ?? (! $write['keputusan']->allowed ? 'Anda tidak memiliki izin mengubah target.' : null);
            $values = TargetTahunanState::values($state['target']);
            $targetRenstra = TargetTahunanDecimal::normalize(TargetRenstra::where('indikator_id', $state['indikator']->id)->where('tahun', $tahun)->value('nilai'));

            return ['indikator_id' => $state['indikator']->id, 'tahun' => $tahun, 'baseline_year' => $tahun - 1,
                'indikator' => $state['indikator']->only(['id', 'kode', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku', 'arah']),
                'renstra' => $state['renstra']->only(['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai']),
                ...$values, 'has_snapshot' => $state['has_snapshot'], 'expected_state' => TargetTahunanState::token($state),
                'target_renstra' => $targetRenstra, 'alasan_deviasi_renstra' => $state['target']?->alasan_deviasi_renstra,
                'status_deviasi' => DeviasiTargetRenstra::status($state['indikator']->arah, $values['target_tahunan'], $targetRenstra),
                'can' => ['update' => $reason === null], 'read_only_reason' => $reason,
                'saran_baseline' => $values['baseline'] === null && $reason === null ? $this->saranBaseline($actor, $state['indikator'], $tahun) : null];
        });
    }

    /**
     * Saran baseline dari realisasi periode nilai akhir tahun sebelumnya, khusus indikator yang sama.
     *
     * Angka hanya diambil dari versi disahkan selama header masih berstatus disahkan, sehingga pengukuran yang dibuka
     * kembali dianggap belum disahkan. Status: `disahkan` (nilai dan nama periode beku), `belum_disahkan` (ikut jadwal
     * T−1, tetapi pengukuran nilai akhir belum ada atau belum disahkan), `tidak_dapat_dihitung` (versi disahkan tanpa
     * angka), `negatif` (baseline wajib nonnegatif), dan `belum_ada` (indikator tidak diukur jadwal T−1 aktif/ditutup).
     *
     * Hak baca pengukuran memakai Gate `view` bila baris ada, dan unit versi snapshot efektif pada periode nilai akhir
     * bila belum. Unit master indikator hanya dipakai untuk `belum_ada`, karena unit master dapat dipindah setelah
     * snapshot dibuat. Tanpa hak baca, atau bila jadwal T−1 tidak punya periode nilai akhir atau versi efektif,
     * hasilnya null: editor tampil tanpa saran dan keterangan. Saran hanya nilai awal form; tidak ditulis, tidak
     * diaudit, dan tidak masuk token `expected_state`, karena Simpan memvalidasi ulang baseline.
     *
     * @return array{status: string, nilai: ?string, periode: ?string}|null
     */
    private function saranBaseline(User $actor, IndikatorKinerja $indikator, int $tahun): ?array
    {
        $tanpaAngka = fn (string $status): array => ['status' => $status, 'nilai' => null, 'periode' => null];
        // Predikat "ikut jadwal T−1" sama dengan RekomendasiBerlaku::periodeSebelum; ubah keduanya bersama.
        $jadwalId = DB::table('jadwal_tahunan as j')->where('j.tahun', $tahun - 1)->whereIn('j.status', ['aktif', 'ditutup'])
            ->whereExists(fn (Builder $s) => $s->from('jadwal_snapshot as s')->whereColumn('s.jadwal_id', 'j.id')->where('s.indikator_id', $indikator->id))
            ->value('j.id');
        if ($jadwalId === null) {
            return $this->resolver->allows($actor, 'pengukuran:read', $indikator->unit_id) ? $tanpaAngka('belum_ada') : null;
        }
        // Periode dibaca dari jadwal T−1, bukan master aktif: periode lama yang diganti tetap bertanda nilai akhir.
        $periode = DB::table('jadwal_periode as jp')->join('periode as p', 'p.id', '=', 'jp.periode_id')
            ->where('jp.jadwal_id', $jadwalId)->where('p.is_nilai_akhir', true)->orderByDesc('p.urutan')->first(['p.id', 'p.nama', 'p.urutan']);
        if ($periode === null) {
            return null;
        }
        $pengukuran = PengukuranKinerja::with(['jadwalSnapshot', 'ratifiedVersion'])
            ->where('indikator_id', $indikator->id)->where('tahun', $tahun - 1)->where('periode_id', $periode->id)
            ->whereHas('jadwalSnapshot', fn ($query) => $query->where('jadwal_id', $jadwalId))->first();
        if ($pengukuran === null) {
            // Versi efektif dipilih seperti saat pengukuran dibuat: nomor_versi terbesar yang sudah berlaku pada periode itu.
            // Query sama dengan EnsureDraftPengukuran; ubah keduanya bersama.
            $unitId = JadwalSnapshot::join('periode', 'periode.id', '=', 'jadwal_snapshot.periode_mulai_id')
                ->where('jadwal_snapshot.jadwal_id', $jadwalId)->where('jadwal_snapshot.indikator_id', $indikator->id)
                ->where('periode.urutan', '<=', $periode->urutan)->orderByDesc('jadwal_snapshot.nomor_versi')->value('jadwal_snapshot.unit_id');

            return $unitId !== null && $this->resolver->allows($actor, 'pengukuran:read', $unitId) ? $tanpaAngka('belum_disahkan') : null;
        }
        if (! Gate::forUser($actor)->allows('view', $pengukuran)) {
            return null;
        }
        $versi = $pengukuran->ratifiedVersion?->snapshot;
        if ($pengukuran->status_alur !== 'disahkan' || $versi === null) {
            return $tanpaAngka('belum_disahkan');
        }
        $nilai = $versi['nilai'] ?? null;
        if (($versi['status_perhitungan'] ?? null) !== 'terhitung' || $nilai === null) {
            return $tanpaAngka('tidak_dapat_dihitung');
        }
        // Realisasi boleh negatif, sedangkan baseline ditolak saat Simpan bila negatif.
        if (str_starts_with($nilai, '-')) {
            return $tanpaAngka('negatif');
        }

        return ['status' => 'disahkan', 'nilai' => TargetTahunanDecimal::normalize($nilai, 'baseline'), 'periode' => $versi['periode']['nama'] ?? $periode->nama];
    }
}
