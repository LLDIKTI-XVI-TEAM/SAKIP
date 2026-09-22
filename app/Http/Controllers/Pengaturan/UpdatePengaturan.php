<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdatePengaturanRequest;
use App\Models\User;
use App\Services\PengaturanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class UpdatePengaturan extends Controller
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
    ) {}

    public function __invoke(UpdatePengaturanRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $alasan = $validated['alasan'] ?? null;
        $values = Arr::except(Arr::dot($validated), ['alasan']);

        $this->pengaturanService->update($actor, $values, $alasan);

        return redirect()
            ->route('pengaturan.index')
            ->with('success', 'Pengaturan sistem berhasil diperbarui dan dicatat dalam audit trail.');
    }
}
