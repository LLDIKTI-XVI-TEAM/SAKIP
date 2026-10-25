<?php

namespace App\Actions\Jadwal;

use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Jadwal\JadwalDraftRules;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevisiJendelaPic
{
    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit, private readonly JadwalDraftRules $rules) {}

    /**
     * Revisi resmi jendela pengisian periode anggota jadwal aktif (Q18; jendela reviu dihapus Q70).
     * Hanya tanggal jendela dan revisi yang berubah; guard PIC membaca nilai baru
     * langsung dari `jadwal_periode`.
     * Urutan kunci jadwal → periode jadwal sama dengan writer pengukuran.
     *
     * @param  array{expected_revisi: int|string, alasan: string, periode: list<array{periode_id: string, pengisian_mulai: string, pengisian_selesai: string}>}  $data
     */
    public function handle(User $actor, string $jadwalId, array $data): JadwalTahunan
    {
        /** @var PermissionDecision|null $decision */
        $decision = null;
        $jadwalId = strtolower($jadwalId);
        try {
            return DB::transaction(function () use ($actor, $jadwalId, $data, &$decision): JadwalTahunan {
                $decision = $this->lockedActor->handle($actor, PermissionCodes::JADWAL_UPDATE)['keputusan'];
                if (! $decision->allowed) {
                    throw new AuthorizationException('Anda tidak memiliki izin merevisi jendela jadwal.');
                }
                Periode::lockConfiguration();
                $jadwal = JadwalTahunan::whereKey($jadwalId)->lockForUpdate()->firstOrFail();
                if ($jadwal->status !== 'aktif' || $jadwal->tahunDitutup()) {
                    throw ValidationException::withMessages(['jadwal' => 'Revisi jendela PIC hanya untuk jadwal aktif yang belum melewati penutupan.']);
                }
                if ($jadwal->revisi !== (int) $data['expected_revisi']) {
                    throw ValidationException::withMessages(['jadwal' => 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum merevisi jendela.']);
                }
                $windows = PeriodeJadwal::where('jadwal_id', $jadwal->id)->orderBy('id')->lockForUpdate()->get()->keyBy('periode_id');
                $requested = array_map(fn (array $window): array => [...$window, 'periode_id' => strtolower($window['periode_id'])], $data['periode']);
                $ids = array_column($requested, 'periode_id');
                sort($ids);
                if ($ids !== $windows->keys()->sort()->values()->all()) {
                    throw ValidationException::withMessages(['periode' => 'Revisi hanya mengubah tanggal periode anggota jadwal; periode tidak dapat ditambah atau dihapus.']);
                }
                $jadwal->setRelation('periode', $windows->values());
                $before = ['revisi' => $jadwal->revisi, 'periode' => $jadwal->draftAttributes()['periode']];
                $lama = collect($before['periode'])->keyBy('periode_id');
                $diubah = array_column(array_filter($requested, fn (array $window): bool => Arr::only($window, JadwalDraftRules::WINDOW_FIELDS)
                    != Arr::only($lama[$window['periode_id']], JadwalDraftRules::WINDOW_FIELDS)), 'periode_id');
                // Batas awal hanya untuk jendela yang tanggalnya diubah; jendela lain pada jadwal aktif tetap berlaku apa adanya.
                $metadata = Periode::whereIn('id', $ids)->get()->mapWithKeys(fn (Periode $periode): array => [$periode->id => [
                    'urutan' => $periode->urutan,
                    ...(in_array($periode->id, $diubah, true) ? ['batas_awal_pengisian' => $periode->batasAwalPengisian($jadwal->tahun)] : []),
                ]])->all();
                $this->rules->validate(['tahun' => $jadwal->tahun, 'penutupan' => $jadwal->penutupan->toDateString(), 'periode' => $requested], $metadata);
                $changed = false;
                foreach ($requested as $window) {
                    $row = $windows->get($window['periode_id']);
                    $row->fill(Arr::only($window, JadwalDraftRules::WINDOW_FIELDS));
                    if ($row->isDirty()) {
                        $row->save();
                        $changed = true;
                    }
                }
                if (! $changed) {
                    return $jadwal;
                }
                $jadwal->forceFill(['revisi' => $jadwal->revisi + 1])->save();
                $this->audit->catat(actor: $actor, tindakan: 'jadwal.revisi_jendela', objekTipe: 'jadwal', objekId: $jadwal->id, nilaiLama: $before,
                    nilaiBaru: ['revisi' => $jadwal->revisi, 'periode' => $jadwal->draftAttributes()['periode']],
                    alasan: AuditReason::sanitize($data['alasan']), dasarIzin: $decision->toAuditBasis());

                return $jadwal;
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Di luar rollback agar penolakan tetap tercatat dengan keputusan izin yang benar-benar dipakai.
            $this->audit->catat(actor: $actor, tindakan: 'jadwal.revisi_jendela_ditolak', objekTipe: 'jadwal', objekId: $jadwalId,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_jadwal'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }
}
