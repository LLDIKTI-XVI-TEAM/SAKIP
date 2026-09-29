<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\DestroySasaran as DestroySasaranAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\DestroySasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DestroySasaran extends Controller
{
    public function __invoke(DestroySasaranRequest $request, SasaranStrategis $sasaran, DestroySasaranAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $result = $action->handle($actor, $sasaran, (string) $request->validated('alasan'));

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $sasaran->renstra_id])
            ->with('success', "Sasaran strategis '{$result['kode']}' berhasil dihapus.");
    }
}
