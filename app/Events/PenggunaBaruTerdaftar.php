<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Akun baru dibentuk dari onboarding SSO tanpa peran; hanya pada cabang pembuatan, bukan login ulang. */
class PenggunaBaruTerdaftar implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $userId) {}
}
