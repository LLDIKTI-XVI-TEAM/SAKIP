<?php

namespace App\Http\Middleware;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Policies\IndikatorKinerjaPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePenanggungJawabActorIsActive extends EnsureUserIsActive
{
    public function __construct(private readonly IndikatorKinerjaPolicy $policy) {}

    /** Hanya mutasi PJ memakai hook ini; objek berasal dari model binding, bukan payload. */
    protected function rejectInactive(Request $request, ?User $actor): Response
    {
        $indicator = $request->route('indikator');
        if ($actor && $indicator instanceof IndikatorKinerja) {
            // Policy memakai resolver dan audit penolakan yang sama, tanpa menjalankan Action mutasi.
            $this->policy->assignPenanggungJawab($actor, $indicator);
        }

        return parent::rejectInactive($request, $actor);
    }
}
