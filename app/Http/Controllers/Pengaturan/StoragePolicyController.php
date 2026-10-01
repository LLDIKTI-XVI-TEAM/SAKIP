<?php

namespace App\Http\Controllers\Pengaturan;

use App\Actions\Pengaturan\GetStoragePolicyPage;
use App\Actions\Pengaturan\UpdateStoragePolicyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdateStoragePolicyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoragePolicyController extends Controller
{
    public function index(Request $request, GetStoragePolicyPage $action): Response
    {
        return Inertia::render('Pengaturan/StorageIndex', $action->handle($request->user()));
    }

    public function update(UpdateStoragePolicyRequest $request, UpdateStoragePolicyAction $action): RedirectResponse
    {
        $result = $action->handle($request->user(), $request->mutationData());

        if (! $result['changed']) {
            return redirect()
                ->route('pengaturan.storage.index')
                ->with('message', 'Tidak ada perubahan pada nilai kebijakan storage.');
        }

        return redirect()
            ->route('pengaturan.storage.index')
            ->with('success', "Kebijakan storage berhasil diperbarui ({$result['count']} kunci diubah).");
    }
}
