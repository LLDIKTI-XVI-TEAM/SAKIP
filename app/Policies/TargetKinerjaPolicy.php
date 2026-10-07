<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\Response;

class TargetKinerjaPolicy
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /** Izin baca indikator membuka baseline/target, tanpa permission baru. */
    public function view(User $actor): Response
    {
        return $this->resolver->allows($actor, PermissionCodes::INDIKATOR_READ) ? Response::allow() : Response::deny('Anda tidak memiliki izin membaca indikator.');
    }

    /** Kedua izin wajib; resolver tetap memenangkan explicit deny. */
    public function update(User $actor): Response
    {
        return $this->view($actor)->allowed() && $this->resolver->allows($actor, PermissionCodes::TARGET_UPDATE)
            ? Response::allow() : Response::deny('Menyimpan target memerlukan izin baca indikator dan ubah target.');
    }
}
