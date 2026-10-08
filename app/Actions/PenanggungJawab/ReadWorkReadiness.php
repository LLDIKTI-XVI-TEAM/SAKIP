<?php

namespace App\Actions\PenanggungJawab;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\PenanggungJawab\WorkReadiness;

class ReadWorkReadiness
{
    public function __construct(private readonly WorkReadiness $readiness) {}

    /** @return array<string,mixed> */
    public function handle(IndikatorKinerja $indicator, string $userId): array
    {
        $user = User::findOrFail($userId);

        return $this->readiness->forUser($user, $indicator->unit_id) + ['user_id' => $user->id, 'unit_id' => $indicator->unit_id];
    }
}
