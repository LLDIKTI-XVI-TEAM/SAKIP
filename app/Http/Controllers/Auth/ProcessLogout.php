<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EndLocalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcessLogout
{
    public function __invoke(Request $request, EndLocalSession $endSession): RedirectResponse
    {
        $endSession->handle($request);

        return redirect()->route('auth.logged-out');
    }
}
