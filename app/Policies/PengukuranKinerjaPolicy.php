<?php

namespace App\Policies;

use App\Actions\Pengukuran\SubmissionPrerequisites;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\Response;

class PengukuranKinerjaPolicy
{
    public function __construct(private PermissionResolver $resolver, private SubmissionPrerequisites $prerequisites) {}

    public function viewAny(User $user): bool
    {
        return $this->resolver->allows($user, 'pengukuran:read');
    }

    public function view(User $user, PengukuranKinerja $p): bool
    {
        return $this->resolver->allows($user, 'pengukuran:read', $p->targetUnitId());
    }

    public function viewEvidence(User $user, PengukuranKinerja $p): bool
    {
        if (! $this->view($user, $p)) {
            return false;
        }
        $decision = $this->resolver->decide($user, 'berkas:read', $p->targetUnitId());
        if (in_array($decision['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'invalid_scope'], true)) {
            return false;
        }

        return $decision['allowed'] || $this->resolver->allows($user, 'pengukuran:update', $p->targetUnitId()) || $this->resolver->allows($user, 'pengukuran:create', $p->targetUnitId());
    }

    public function usesPlanningPath(User $user, PengukuranKinerja $p): bool
    {
        $decision = $this->resolver->decide($user, 'pengukuran:update', $p->targetUnitId());

        return $decision['allowed'] && $user->roles()->whereIn('roles.id', $decision['roles'])->whereIn('kode', ['perencanaan', 'superadmin'])->exists();
    }

    public function update(User $user, PengukuranKinerja $p): Response
    {
        return $this->capability($user, $p, 'draft', 'pengukuran:update');
    }

    public function submit(User $user, PengukuranKinerja $p): Response
    {
        $ability = $this->capability($user, $p, 'ajukan', 'pengukuran:update');
        if ($ability->denied()) {
            return $ability;
        }
        $result = $this->prerequisites->handle($p);

        return $result['siap'] ? Response::allow() : Response::deny(implode(' ', $result['alasan']));
    }

    public function uploadEvidence(User $user, PengukuranKinerja $p): bool
    {
        return $this->update($user, $p)->allowed() && ! in_array($this->resolver->decide($user, 'berkas:upload', $p->targetUnitId())['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'invalid_scope'], true);
    }

    public function verify(User $user, PengukuranKinerja $p): Response
    {
        return $this->capability($user, $p, 'verifikasi', 'pengukuran:verifikasi');
    }

    public function ratify(User $user, PengukuranKinerja $p): Response
    {
        return $this->capability($user, $p, 'sahkan', 'pengukuran:sahkan');
    }

    public function returnMeasurement(User $user, PengukuranKinerja $p): Response
    {
        return $this->capability($user, $p, 'kembalikan', 'pengukuran:kembalikan');
    }

    private function capability(User $user, PengukuranKinerja $p, string $command, string $permission): Response
    {
        if (! $this->resolver->allows($user, $permission, $p->targetUnitId())) {
            return Response::deny('Izin tindakan tidak tersedia atau telah dicabut.');
        }
        $errors = $this->businessErrors($user, $p, $command);

        return $errors === [] ? Response::allow() : Response::deny(implode(' ', $errors));
    }

    /** Dipakai Action setelah resolver; kegagalan bisnis menghasilkan validasi, bukan keputusan deny palsu. */
    public function businessErrors(User $user, PengukuranKinerja $p, string $command): array
    {
        $snapshot = $p->jadwalSnapshot;
        $errors = [];
        if (! $snapshot || $snapshot->indikator_id !== $p->indikator_id || $snapshot->jadwal->tahun !== $p->tahun) {
            return ['Snapshot jadwal tidak cocok dengan pengukuran.'];
        }
        $schedule = $snapshot->jadwal;
        if ($schedule->renstra_id !== $p->indikator->sasaranStrategis->renstra_id) {
            $errors[] = 'Renstra jadwal tidak cocok dengan indikator.';
        }
        if (! PeriodeJadwal::where('jadwal_id', $schedule->id)->where('periode_id', $snapshot->periode_mulai_id)->exists()) {
            $errors[] = 'Periode efektif snapshot bukan anggota jadwal.';
        }
        if ($p->sumber_nilai === 'historis') {
            $errors[] = 'Koreksi nilai historis memerlukan alur backfill resmi.';
        }
        if ($schedule->status !== 'aktif') {
            $errors[] = 'Jadwal tahunan harus aktif.';
        }
        $period = PeriodeJadwal::where('jadwal_id', $snapshot->jadwal_id)->where('periode_id', $p->periode_id)->first();
        if (! $period || $p->periode->urutan < $snapshot->periodeMulai->urutan) {
            $errors[] = 'Periode belum termasuk kewajiban efektif indikator pada jadwal ini.';
        }
        $scope = $schedule->lingkup_koreksi ?? [];
        $correction = $schedule->koreksi_mulai && $schedule->koreksi_sampai && now()->betweenIncluded($schedule->koreksi_mulai, $schedule->koreksi_sampai)
            && in_array($p->indikator_id, $scope['indikator_ids'] ?? [], true) && in_array($p->periode_id, $scope['periode_ids'] ?? [], true)
            && in_array('pengukuran', $scope['jenis_objek'] ?? [], true);
        if (now()->gt($schedule->penutupan->endOfDay())) {
            $scope = $schedule->lingkup_koreksi ?? [];
            if (! $schedule->koreksi_mulai || ! $schedule->koreksi_sampai || ! now()->betweenIncluded($schedule->koreksi_mulai, $schedule->koreksi_sampai)
                || ! in_array($p->indikator_id, $scope['indikator_ids'] ?? [], true) || ! in_array($p->periode_id, $scope['periode_ids'] ?? [], true)
                || ! in_array('pengukuran', $scope['jenis_objek'] ?? [], true)) {
                $errors[] = 'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup pengukuran ini.';
            }
        }
        $statuses = match ($command) {
            'draft','ajukan' => ['draft', 'dikembalikan'], 'verifikasi' => ['diajukan'], 'sahkan' => ['diverifikasi'], 'kembalikan' => ['diajukan', 'diverifikasi']
        };
        if (! in_array($p->status_alur, $statuses, true)) {
            $errors[] = 'Status pengukuran tidak sesuai untuk tindakan ini.';
        }
        if (in_array($command, ['draft', 'ajukan'], true)) {
            if (! $this->usesPlanningPath($user, $p)) {
                if ($p->effectivePic()?->user_id !== $user->id) {
                    $errors[] = 'Tindakan ini memerlukan penugasan PIC yang efektif.';
                }
                if (! $period || ! now()->betweenIncluded($period->pengisian_mulai->startOfDay(), $period->pengisian_selesai->endOfDay())) {
                    $errors[] = 'Jendela pengisian PIC periode ini sudah ditutup.';
                }
            }
        } else {
            if ($period && now()->lt($period->reviu_mulai->startOfDay()) && ! $correction) {
                $errors[] = 'Jendela reviu periode ini belum dimulai.';
            }
            $version = $p->latestVersion;
            if (! $version || $version->jadwal_snapshot_id !== $p->jadwal_snapshot_id) {
                $errors[] = 'Versi pengajuan yang direviu belum tersedia atau tidak cocok.';
            } elseif (in_array($command, ['verifikasi', 'sahkan'], true) && $version->diajukan_by === $user->id && $version->jalur_pengajuan === 'pic') {
                $errors[] = 'Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.';
            }
        }

        return $errors;
    }
}
