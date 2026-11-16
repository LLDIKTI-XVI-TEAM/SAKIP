<?php

namespace App\Http\Controllers\Notifikasi;

use App\Actions\Notifikasi\BukaNotifikasi as BukaNotifikasiAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifikasi\BukaNotifikasiRequest;
use Illuminate\Http\RedirectResponse;

class BukaNotifikasi extends Controller
{
    public function __invoke(BukaNotifikasiRequest $request, string $id, BukaNotifikasiAction $action): RedirectResponse
    {
        return redirect()->to($action->handle($request->user(), $id));
    }
}
