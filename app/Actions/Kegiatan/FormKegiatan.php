<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Policies\KegiatanPolicy;
use App\Services\Authorization\PermissionResolver;

class FormKegiatan
{
    public function __construct(private readonly KegiatanPolicy $policy, private readonly PermissionResolver $resolver) {}

    /** Pilihan unit diturunkan dari izin kegiatan; tidak mensyaratkan hak pengelolaan master unit. @return array<string,mixed> */
    public function handle(User $actor, ?Kegiatan $kegiatan = null): array
    {
        if ($kegiatan) {
            abort_unless($this->policy->view($actor, $kegiatan) && $this->policy->update($actor, $kegiatan), 403);
        }
        $units = Unit::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']);
        $code = $kegiatan ? 'kegiatan:update' : 'kegiatan:create';
        $decisions = $this->resolver->decideManyForUnits($actor, [$code], $units->pluck('id')->all());
        $unitOptions = $units->filter(fn (Unit $unit) => $decisions[$unit->id][$code]['allowed'])->values();
        abort_unless($unitOptions->isNotEmpty(), 403);

        return [
            'kegiatan' => $kegiatan ? $kegiatan->rencanaAttributes() + ['version_token' => $kegiatan->versionToken()] : null,
            'tahunDefault' => today(config('app.business_timezone'))->year,
            'unitOptions' => $unitOptions->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => Periode::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
        ];
    }
}
