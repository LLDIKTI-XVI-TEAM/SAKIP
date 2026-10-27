<?php

namespace App\Policies;

use App\Models\JadwalTahunan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\Response;

class JadwalTahunanPolicy
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /** Baca daftar/detail untuk pengelola, pengaktif, atau penutup; izin baca tidak membuka mutasi selain intent yang diberikan. */
    public function viewAny(User $user): Response
    {
        return $this->manage($user)->allowed() || $this->activate($user)->allowed() || $this->close($user)->allowed() ? Response::allow() : Response::deny();
    }

    /** Editor kalender dan opsi pemilihnya; izin aktivasi saja tidak termasuk. */
    public function manage(User $user): Response
    {
        return $this->create($user)->allowed() || $this->update($user)->allowed() ? Response::allow() : Response::deny();
    }

    /** Izin efektif saja; status jadwal dan empat gerbang dinilai server pada readiness/aktivasi. */
    public function activate(User $user): Response
    {
        return $this->resolver->allows($user, PermissionCodes::JADWAL_AKTIVASI) ? Response::allow() : Response::deny();
    }

    /** Izin efektif saja; status jadwal dinilai ulang server di dalam transaksi penutupan. */
    public function close(User $user): Response
    {
        return $this->resolver->allows($user, PermissionCodes::JADWAL_TUTUP) ? Response::allow() : Response::deny();
    }

    /** Izin efektif saja; status jadwal dan kelayakan indikator dinilai ulang server di dalam transaksi. */
    public function tambahIndikator(User $user): Response
    {
        return $this->resolver->allows($user, PermissionCodes::JADWAL_BUKA_KEMBALI) && $this->resolver->allows($user, PermissionCodes::INDIKATOR_READ)
            ? Response::allow() : Response::deny();
    }

    public function create(User $user): Response
    {
        return $this->resolver->allows($user, PermissionCodes::JADWAL_CREATE) ? Response::allow() : Response::deny();
    }

    public function update(User $user, ?JadwalTahunan $jadwal = null): Response
    {
        return $this->resolver->allows($user, PermissionCodes::JADWAL_UPDATE) ? Response::allow() : Response::deny();
    }
}
