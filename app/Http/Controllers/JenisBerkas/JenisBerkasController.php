<?php

namespace App\Http\Controllers\JenisBerkas;

use App\Actions\JenisBerkas\CreateJenisBerkasAction;
use App\Actions\JenisBerkas\DeleteJenisBerkasAction;
use App\Actions\JenisBerkas\GetJenisBerkasPage;
use App\Actions\JenisBerkas\UpdateBatasTeknisJenisBerkasAction;
use App\Actions\JenisBerkas\UpdateJenisBerkasAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteJenisBerkasRequest;
use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateBatasTeknisJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JenisBerkasController extends Controller
{
    public function index(Request $request, GetJenisBerkasPage $action): Response
    {
        return Inertia::render('JenisBerkas/Index', $action->handle($request->user()));
    }

    public function store(StoreJenisBerkasRequest $request, CreateJenisBerkasAction $action): RedirectResponse
    {
        $warning = $action->handle($request->user(), $request->validated());
        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil ditambahkan.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function update(UpdateJenisBerkasRequest $request, string $id, UpdateJenisBerkasAction $action): RedirectResponse
    {
        $warning = $action->handle($request->user(), $id, $request->validated());
        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil diperbarui.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function destroy(DeleteJenisBerkasRequest $request, string $id, DeleteJenisBerkasAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());
        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil dihapus.');

        return $redirect;
    }

    public function updateBatasTeknis(UpdateBatasTeknisJenisBerkasRequest $request, string $id, UpdateBatasTeknisJenisBerkasAction $action): RedirectResponse
    {
        $warning = $action->handle($request->user(), $id, $request->validated());
        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Batas teknis persyaratan jenis berkas berhasil diperbarui.');
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }
}
