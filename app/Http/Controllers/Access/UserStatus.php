<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\ChangeUserStatus;
use App\Http\Requests\Access\ChangeUserStatusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserStatus
{
    public function __invoke(ChangeUserStatusRequest $request, string $user, ChangeUserStatus $change): RedirectResponse
    {
        $status = $request->validated('status');
        $result = $change->handle($request->user(), $user, $status, $request->validated('alasan'), $request->validated('expected_status'));
        Inertia::clearHistory();

        return back(303)->with('success', match (true) {
            $result['status'] === 'unchanged' => 'Status akun tidak berubah.',
            $status === 'nonaktif' => 'Akun berhasil dinonaktifkan.',
            default => 'Akun berhasil diaktifkan kembali.',
        });
    }
}
