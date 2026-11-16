<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\ChangeRenstraStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\ChangeRenstraStatusRequest;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ChangeRenstraStatusController extends Controller
{
    public function activate(ChangeRenstraStatusRequest $request, Renstra $renstra, ChangeRenstraStatus $action): RedirectResponse
    {
        return $this->change($request, $renstra, $action, 'activate', 'Renstra berhasil diaktifkan.');
    }

    public function deactivate(ChangeRenstraStatusRequest $request, Renstra $renstra, ChangeRenstraStatus $action): RedirectResponse
    {
        return $this->change($request, $renstra, $action, 'deactivate', 'Renstra berhasil dinonaktifkan.');
    }

    public function archive(ChangeRenstraStatusRequest $request, Renstra $renstra, ChangeRenstraStatus $action): RedirectResponse
    {
        return $this->change($request, $renstra, $action, 'archive', 'Renstra berhasil diarsipkan.');
    }

    private function change(ChangeRenstraStatusRequest $request, Renstra $renstra, ChangeRenstraStatus $action, string $intent, string $message): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->execute($renstra, $actor, $intent, $request->validated('expected_state'));

        Inertia::flash('success', $message);

        return redirect()->route('renstra.show', $renstra);
    }
}
