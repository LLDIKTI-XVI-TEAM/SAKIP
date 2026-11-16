<?php

namespace App\Actions\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EndLocalSession
{
    public function handle(Request $request): void
    {
        $actorId = $request->user()?->id;
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();
        Log::info('Session SAKIP diakhiri.', ['category' => 'logout_local', 'actor_id' => $actorId, 'correlation_id' => (string) Str::uuid()]);
    }
}
