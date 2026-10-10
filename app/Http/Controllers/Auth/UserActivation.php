<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ActivateUser;
use App\Actions\Auth\GetActivationPage;
use App\Http\Requests\Auth\ActivateUserRequest;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserActivation
{
    public function index(Request $request, PermissionResolver $permissions, GetActivationPage $getPage): Response
    {
        abort_unless($permissions->allows($request->user(), 'pengguna:read'), 403);

        return Inertia::render('Auth/ActivationIndex', [
            ...$getPage->handle($request->user()),
            'activationResult' => $request->session()->get('activationResult'),
        ]);
    }

    public function store(ActivateUserRequest $request, string $user, ActivateUser $activate): RedirectResponse
    {
        $changed = $activate->handle($request->user(), $user, $request->validated('alasan'));

        return redirect()->route('activation.index')->with('activationResult', ['user_id' => $user, 'status' => $changed ? 'activated' : 'already_active'])
            ->with('success', $changed ? 'Akun berhasil diaktifkan.' : 'Akun sudah aktif.');
    }
}
