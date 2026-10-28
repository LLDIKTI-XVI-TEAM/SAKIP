<?php

namespace App\Policies;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\SesiKoreksiJadwal;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class PengukuranKinerjaPolicy
{
    public function __construct(private PermissionResolver $resolver) {}

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
        if (in_array($decision['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'], true)) {
            return false;
        }

        return $decision['allowed'] || $this->resolver->allows($user, 'pengukuran:update', $p->targetUnitId()) || $this->resolver->allows($user, 'pengukuran:create', $p->targetUnitId());
    }

    /**
     * Jalur Perencanaan (global) bila allow berasal dari peran; allow yang hanya dari grant unit
     * adalah jalur PIC yang tunduk pada PJ efektif dan jendela (Data Model §4.2; ADR-0002).
     */
    public function usesPlanningPath(User $user, PengukuranKinerja $p, string $permission = 'pengukuran:update'): bool
    {
        $decision = $this->resolver->decide($user, $permission, $p->targetUnitId());

        return $decision['allowed'] && $decision['roles'] !== [];
    }

    public function viewClaims(User $user, PengukuranKinerja $p): bool
    {
        // Submit memastikan seluruh kegiatan klaim cocok dengan unit konteks pengukuran.
        return $this->view($user, $p) && $this->resolver->allows($user, 'kegiatan:read', $p->targetUnitId());
    }

    public function viewClaimEvidence(User $user, PengukuranKinerja $p): bool
    {
        // Allow berkas mengikuti akses induk kegiatan; deny dan katalog nonaktif tetap menang.
        return $this->viewClaims($user, $p)
            && ! in_array($this->resolver->decide($user, 'berkas:read', $p->targetUnitId())['reason'],
                ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'], true);
    }

    public function update(User $user, PengukuranKinerja $p): Response
    {
        return $this->capability($user, $p, 'draft', 'pengukuran:update');
    }

    public function submit(User $user, PengukuranKinerja $p): Response
    {
        // Input terkini disimpan dan diperiksa atomik oleh Action; kesiapan draf bukan izin tombol.
        return $this->capability($user, $p, 'ajukan', 'pengukuran:update');
    }

    public function uploadEvidence(User $user, PengukuranKinerja $p): bool
    {
        return $this->update($user, $p)->allowed() && ! in_array($this->resolver->decide($user, 'berkas:upload', $p->targetUnitId())['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'], true);
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
        $date = today(config('app.business_timezone'))->toDateString();
        if ($schedule->renstra_id !== $p->indikator->sasaranStrategis->renstra_id) {
            $errors[] = 'Renstra jadwal tidak cocok dengan indikator.';
        }
        if (! PeriodeJadwal::where('jadwal_id', $schedule->id)->where('periode_id', $snapshot->periode_mulai_id)->exists()) {
            $errors[] = 'Periode efektif snapshot bukan anggota jadwal.';
        }
        if (! DB::table('unit')->where('id', $snapshot->unit_id)->where('status', 'aktif')->exists()) {
            $errors[] = 'Unit organisasi pengukuran berstatus nonaktif.';
        }
        if ($p->indikator && $p->indikator->isArsip()) {
            $errors[] = 'Indikator berstatus arsip; pengukuran tidak dapat diubah, diajukan, atau direviu.';
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
        $correction = $this->sesiKoreksiAktif($schedule, $p->indikator_id, $p->periode_id);
        // Jalur mengikuti izin tindakan; deny input tidak mencabut izin review yang berbeda.
        $planning = $this->usesPlanningPath($user, $p, match ($command) {
            'draft', 'ajukan' => 'pengukuran:update',
            'verifikasi' => 'pengukuran:verifikasi', 'sahkan' => 'pengukuran:sahkan', 'kembalikan' => 'pengukuran:kembalikan',
        });
        if ($schedule->tahunDitutup() && (! $correction || ! $planning)) {
            $errors[] = 'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup pengukuran ini.';
        }
        $statuses = match ($command) {
            'draft','ajukan' => ['draft', 'dikembalikan'], 'verifikasi' => ['diajukan'], 'sahkan' => ['diverifikasi'], 'kembalikan' => ['diajukan', 'diverifikasi']
        };
        if (! in_array($p->status_alur, $statuses, true)) {
            $errors[] = 'Status pengukuran tidak sesuai untuk tindakan ini.';
        }
        if (in_array($command, ['draft', 'ajukan'], true)) {
            if (! $planning) {
                if ($p->effectivePic()?->user_id !== $user->id) {
                    $errors[] = 'Tindakan ini memerlukan penugasan PIC yang efektif.';
                }
                if (($pesanJendela = $this->pesanJendelaPic($date, $period)) !== null) {
                    $errors[] = $pesanJendela;
                }
            }
        } else {
            $version = $p->latestVersion;
            if (! $version || $version->jadwal_snapshot_id !== $p->jadwal_snapshot_id) {
                $errors[] = 'Versi pengajuan yang direviu belum tersedia atau tidak cocok.';
            } elseif (in_array($command, ['verifikasi', 'sahkan'], true) && $version->diajukan_by === $user->id && $version->jalur_pengajuan === 'pic') {
                $errors[] = 'Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.';
            }
        }

        return $errors;
    }

    /**
     * Guard bisnis pembentukan header draf, tanpa query: snapshot membawa relasi jadwal dan unit yang sudah dimuat,
     * dan PJ efektif dipasok pemanggil (Action dari baris terkunci; daftar dari data pramuat). Keputusan resolver
     * pengukuran:create menentukan jalur: allow dari peran = Perencanaan tanpa syarat PJ/jendela; grant unit saja = PIC.
     */
    public function creationErrors(User $actor, JadwalSnapshot $snapshot, PeriodeJadwal $period, array $decision, ?string $picUserId): array
    {
        $schedule = $snapshot->jadwal;
        $date = today(config('app.business_timezone'))->toDateString();
        $errors = [];
        if ($snapshot->unit?->status !== 'aktif') {
            $errors[] = 'Unit organisasi pengukuran berstatus nonaktif.';
        }
        if ($schedule->status !== 'aktif') {
            $errors[] = 'Jadwal tahunan harus aktif.';
        }
        if ($schedule->tahunDitutup() && ($decision['roles'] === [] || ! $this->sesiKoreksiAktif($schedule, $snapshot->indikator_id, $period->periode_id))) {
            $errors[] = 'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup pengukuran ini.';
        }
        if ($decision['roles'] === []) {
            if ($picUserId !== $actor->id) {
                $errors[] = 'Tindakan ini memerlukan penugasan PIC yang efektif.';
            }
            if (($pesanJendela = $this->pesanJendelaPic($date, $period)) !== null) {
                $errors[] = $pesanJendela;
            }
        }

        return $errors;
    }

    /**
     * Pesan jendela pengisian PIC pada tanggal bisnis (WITA), atau null bila tanggal berada di dalam jendela
     * inklusif pengisian_mulai..pengisian_selesai. Periode yang bukan anggota jadwal tetap dijawab "sudah ditutup";
     * ketidakcocokan periodenya sudah dilaporkan pesan tersendiri.
     */
    private function pesanJendelaPic(string $date, ?PeriodeJadwal $period): ?string
    {
        if (! $period || $date > $period->pengisian_selesai->toDateString()) {
            return 'Jendela pengisian PIC periode ini sudah ditutup.';
        }

        return $date < $period->pengisian_mulai->toDateString() ? 'Jendela pengisian PIC periode ini belum dibuka.' : null;
    }

    /** Sesi koreksi resmi membuka tahun yang ditutup hanya untuk indikator × periode × jenis objek dalam lingkupnya. */
    private function sesiKoreksiAktif(JadwalTahunan $schedule, string $indicatorId, string $periodId): bool
    {
        return SesiKoreksiJadwal::berlaku($schedule, 'pengukuran', $indicatorId, [$periodId]);
    }
}
