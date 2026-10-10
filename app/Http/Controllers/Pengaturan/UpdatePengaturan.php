<?php

namespace App\Http\Controllers\Pengaturan;

use App\Actions\Pengaturan\UpdatePengaturan as Update;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdatePengaturanRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class UpdatePengaturan extends Controller
{
    public function __invoke(UpdatePengaturanRequest $request, Update $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $alasan = (string) $validated['alasan'];
        $rawExpected = $validated['expected_updated_at'] ?? [];
        /** @var array<string, string|null> $expectedUpdatedAt */
        $expectedUpdatedAt = is_array($rawExpected) ? Arr::dot($rawExpected) : [];

        $dotData = Arr::dot($validated);
        $values = [];
        foreach ($dotData as $key => $val) {
            if ($key === 'alasan' || str_starts_with($key, 'expected_updated_at')) {
                continue;
            }
            $values[$key] = $val;
        }

        $changed = $action->handle($actor, $values, $alasan, $expectedUpdatedAt);

        $message = $changed > 0
            ? 'Pengaturan sistem berhasil diperbarui dan dicatat dalam audit trail.'
            : 'Tidak ada perubahan pengaturan yang perlu disimpan.';

        return redirect()
            ->route('pengaturan.index')
            ->with('success', $message);
    }
}
