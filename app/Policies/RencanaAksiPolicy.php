<?php

namespace App\Policies;

use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class RencanaAksiPolicy
{
    public function __construct(private PermissionResolver $resolver) {}

    public function viewAny(User $user): bool
    {
        return $this->resolver->allows($user, 'rencana_aksi:read');
    }

    public function view(User $user, RencanaAksi $ra): bool
    {
        return $this->resolver->allows($user, 'rencana_aksi:read', $ra->targetUnitId());
    }

    /**
     * Isi bukti (tautan/isi_teks) hanya dibuka bila baca ringkasan lolos dan
     * akses berkas tidak ditolak. Deny berkas:read menang atas fallback kelola.
     */
    public function viewEvidence(User $user, RencanaAksi $ra): bool
    {
        if (! $this->view($user, $ra)) {
            return false;
        }
        $decision = $this->resolver->decide($user, 'berkas:read', $ra->targetUnitId());
        if (in_array($decision['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'], true)) {
            return false;
        }

        return $decision['allowed'] || $this->resolver->allows($user, 'rencana_aksi:update', $ra->targetUnitId()) || $this->resolver->allows($user, 'rencana_aksi:ajukan', $ra->targetUnitId());
    }

    public function usesPlanningPath(User $user, RencanaAksi $ra): bool
    {
        $decision = $this->resolver->decide($user, 'rencana_aksi:update', $ra->targetUnitId());

        return $decision['allowed'] && $user->roles()->whereIn('roles.id', $decision['roles'])->whereIn('kode', ['perencanaan', 'superadmin'])->exists();
    }

    public function sahkan(User $user, RencanaAksi $ra): Response
    {
        return $this->capability($user, $ra, 'rencana_aksi:sahkan');
    }

    /**
     * Guard bukti pasca-sah: status disahkan membeku semua; setelah buka-kembali
     * (dikembalikan + versi tersahkan ada) hanya ID dalam snapshot resmi yang beku.
     * Bukti baru pasca-buka-kembali (ID tidak ada di snapshot resmi) tetap bisa dihapus.
     * FIX2: cek SEMUA versi dengan disahkan_at NOT NULL (satu query versions),
     * bukan hanya ratifiedVersion terbaru — ID yang muncul di salah satu snapshot
     * resmi tetap beku walau versi terbaru tidak merujuknya lagi.
     */
    public function deleteEvidence(User $user, RencanaAksi $ra, BuktiDukung $bukti): Response
    {
        if ($bukti->berkasable_type !== 'rencana_aksi' || $bukti->berkasable_id !== $ra->id) {
            return Response::deny('Bukti tidak terkait dengan rencana aksi ini.');
        }
        if ($ra->status_alur === 'disahkan') {
            return Response::deny('Bukti yang dirujuk versi resmi tidak boleh dihapus.');
        }
        $frozenIds = $ra->versions()->whereNotNull('disahkan_at')->get(['snapshot'])
            ->flatMap(fn ($version) => is_array($version->snapshot) ? array_values($version->snapshot['bukti_dukungs'] ?? []) : [])
            ->pluck('id')->filter()->unique()->values()->all();
        if (in_array($bukti->id, $frozenIds, true)) {
            return Response::deny('Bukti yang dirujuk versi resmi tidak boleh dihapus.');
        }
        if (! $this->resolver->allows($user, 'berkas:delete', $ra->targetUnitId())) {
            return Response::deny('Izin tindakan tidak tersedia atau telah dicabut.');
        }

        return Response::allow();
    }

    private function capability(User $user, RencanaAksi $ra, string $permission): Response
    {
        if (! $this->resolver->allows($user, $permission, $ra->targetUnitId())) {
            return Response::deny('Izin tindakan tidak tersedia atau telah dicabut.');
        }
        $errors = $this->businessErrors($user, $ra);

        return $errors === [] ? Response::allow() : Response::deny(implode(' ', $errors));
    }

    /** Dipakai Action setelah resolver; kegagalan bisnis menghasilkan validasi, bukan keputusan deny palsu. */
    public function businessErrors(User $user, RencanaAksi $ra): array
    {
        $snapshot = $ra->jadwalSnapshot;
        if (! $snapshot || $snapshot->indikator_id !== $ra->indikator_id || $snapshot->jadwal->tahun !== $ra->tahun) {
            return ['Snapshot jadwal tidak cocok dengan rencana aksi.'];
        }
        $jadwal = $snapshot->jadwal;
        $errors = [];
        if ($ra->unit_id !== $snapshot->unit_id) {
            $errors[] = 'Unit rencana aksi tidak cocok dengan snapshot jadwal.';
        }
        if ($jadwal->renstra_id !== $ra->indikator->sasaranStrategis->renstra_id) {
            $errors[] = 'Renstra jadwal tidak cocok dengan indikator.';
        }
        // FIX1: bila Action sudah mengunci baris unit (SahkanRencanaAksi), nilai
        // terkunci yang dipakai — bukan baca ulang tanpa lock (anti-TOCTOU).
        // Jalur Gate tanpa transaksi memakai cek DB seperti sebelumnya.
        $unitAktif = $this->lockedUnitStatus($ra)
            ?? DB::table('unit')->where('id', $snapshot->unit_id)->where('status', 'aktif')->exists();
        if (! $unitAktif) {
            $errors[] = 'Unit organisasi rencana aksi berstatus nonaktif.';
        }
        if ($jadwal->status !== 'aktif') {
            $errors[] = 'Jadwal tahunan harus aktif.';
        }
        $version = $ra->latestVersion;
        if (! $version || $version->jadwal_snapshot_id !== $ra->jadwal_snapshot_id) {
            return [...$errors, 'Versi pengajuan yang direviu belum tersedia atau tidak cocok.'];
        }
        // F1: pemisahan tugas keras jalur PIC memakai provenance beku versi, bukan created_by header.
        if ($version->diajukan_by === $user->id && $version->jalur_pengajuan === 'pic') {
            $errors[] = 'Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.';
        }
        if ($ra->status_alur !== 'diverifikasi') {
            $errors[] = 'Status rencana aksi tidak sesuai untuk tindakan ini.';
        }
        $date = today(config('app.business_timezone'))->toDateString();
        $scope = $jadwal->lingkup_koreksi ?? [];
        $correction = $jadwal->koreksi_mulai && $jadwal->koreksi_sampai && now()->betweenIncluded($jadwal->koreksi_mulai, $jadwal->koreksi_sampai)
            && in_array($ra->indikator_id, $scope['indikator_ids'] ?? [], true)
            && in_array('rencana_aksi', $scope['jenis_objek'] ?? [], true);
        if ($jadwal->penutupan && $date > $jadwal->penutupan->toDateString() && ! $correction) {
            $errors[] = 'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup rencana aksi ini.';
        }

        return $errors;
    }

    /**
     * Status unit dari relasi terkunci (SahkanRencanaAksi mengunci
     * jadwalSnapshot.unit via lockForUpdate). Null bila relasi tak dimuat —
     * pemanggil memakai cek DB biasa.
     */
    private function lockedUnitStatus(RencanaAksi $ra): ?bool
    {
        $snapshot = $ra->relationLoaded('jadwalSnapshot') ? $ra->jadwalSnapshot : null;
        $unit = $snapshot && $snapshot->relationLoaded('unit') ? $snapshot->unit : null;

        return $unit ? $unit->status === 'aktif' : null;
    }
}
