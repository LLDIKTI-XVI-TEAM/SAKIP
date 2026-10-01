<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\UpdateSasaran as UpdateSasaranAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\UpdateSasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateSasaran extends Controller
{
    public function __invoke(UpdateSasaranRequest $request, SasaranStrategis $sasaran, UpdateSasaranAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $action->handle($actor, $sasaran, [...$request->validated(), ...$request->only('expected_updated_at')]);

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $sasaran->renstra_id])
            ->with('success', "Sasaran strategis '{$sasaran->kode}' berhasil diperbarui.");
    }
}
