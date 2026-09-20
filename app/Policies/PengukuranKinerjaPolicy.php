<?php

namespace App\Policies;

use App\Models\PengukuranKinerja;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PengukuranKinerjaPolicy
{
    /**
     * Admin sistem secara tegas dilarang memanipulasi data substansi kinerja SAKIP
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') && in_array($ability, ['update', 'submit', 'verify', 'ratify', 'delete'])) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(['perencanaan', 'pimpinan', 'superadmin'])) {
            return true;
        }

        return $user->penugasanIndikators()
            ->where('is_active', true)
            ->exists();
    }

    public function view(User $user, PengukuranKinerja $pengukuran): bool
    {
        if ($user->hasRole('perencanaan') || $user->hasRole('pimpinan') || $user->hasRole('superadmin')) {
            return true;
        }

        return $this->isPenanggungJawabAktif($user, $pengukuran);
    }

    public function update(User $user, PengukuranKinerja $pengukuran): Response
    {
        if ($user->hasRole('perencanaan')) {
            return Response::allow();
        }

        if ($user->hasRole('pimpinan')) {
            return Response::deny('Pimpinan hanya memiliki akses baca terhadap pengukuran kinerja.');
        }

        // Cek status alur
        if (! in_array($pengukuran->status, ['draft', 'dikembalikan'])) {
            return Response::deny('Kinerja yang sudah diajukan atau disahkan tidak dapat diedit tanpa persetujuan buka kembali.');
        }

        // Role PIC tidak cukup untuk memberi akses: penanggung jawab harus ditetapkan eksplisit.
        if (! $this->isPenanggungJawabAktif($user, $pengukuran)) {
            return Response::deny('Anda bukan penanggung jawab aktif untuk indikator ini.');
        }

        // Cek deadline jadwal periode
        if (! $pengukuran->periodeJadwal->isAktifBuka()) {
            return Response::deny('Batas waktu pengisian untuk periode ini telah ditutup.');
        }

        return Response::allow();
    }

    public function submit(User $user, PengukuranKinerja $pengukuran): Response
    {
        return $this->update($user, $pengukuran);
    }

    public function verify(User $user, PengukuranKinerja $pengukuran): Response
    {
        if (! $user->hasRole('perencanaan') && ! $user->hasRole('superadmin')) {
            return Response::deny('Hanya Tim Perencanaan yang berwenang memverifikasi capaian kinerja.');
        }

        if (! in_array($pengukuran->status, ['diajukan', 'diverifikasi'])) {
            return Response::deny('Hanya pengukuran dengan status Diajukan yang dapat diverifikasi.');
        }

        return Response::allow();
    }

    public function ratify(User $user, PengukuranKinerja $pengukuran): Response
    {
        if (! $user->hasRole('perencanaan') && ! $user->hasRole('superadmin')) {
            return Response::deny('Hanya Tim Perencanaan yang berwenang mengesahkan capaian kinerja.');
        }

        if (! in_array($pengukuran->status, ['diajukan', 'diverifikasi'])) {
            return Response::deny('Hanya pengukuran yang telah diajukan/diverifikasi yang dapat disahkan.');
        }

        return Response::allow();
    }

    private function isPenanggungJawabAktif(User $user, PengukuranKinerja $pengukuran): bool
    {
        $penugasan = $pengukuran->penugasanIndikator;

        return $penugasan->is_active
            && $penugasan->user_id === $user->id;
    }
}
