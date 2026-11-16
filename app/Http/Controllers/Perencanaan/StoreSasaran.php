<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\StoreSasaran as StoreSasaranAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\StoreSasaranRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreSasaran extends Controller
{
    public function __invoke(StoreSasaranRequest $request, StoreSasaranAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $sasaran = $action->handle($actor, $request->validated());

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $sasaran->renstra_id])
            ->with('success', "Sasaran strategis '{$sasaran->kode}' berhasil ditambahkan.");
    }
}
