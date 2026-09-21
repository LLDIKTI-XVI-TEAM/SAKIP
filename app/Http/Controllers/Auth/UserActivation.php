<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ActivateUser;
use App\Http\Requests\Auth\ActivateUserRequest;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserActivation
{
    public function index(Request $request, PermissionResolver $permissions): Response
    {
        abort_unless($permissions->allows($request->user(), 'pengguna:read'), 403);

        return Inertia::render('Auth/ActivationIndex', [
            'users' => User::where('is_active', false)->select(['id', 'nama', 'email', 'created_at'])->orderBy('created_at')->orderBy('id')->paginate(20),
            'canActivate' => $permissions->allows($request->user(), 'akses:update'),
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
