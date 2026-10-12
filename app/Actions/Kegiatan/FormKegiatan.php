<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;

class FormKegiatan
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly AuditLogger $audit) {}

    /** Pilihan unit diturunkan dari izin kegiatan; tidak mensyaratkan hak pengelolaan master unit. @return array<string,mixed> */
    public function handle(User $actor, ?Kegiatan $kegiatan = null): array
    {
        if ($kegiatan) {
            foreach (['kegiatan:read', 'kegiatan:update'] as $permission) {
                $decision = $this->resolver->resolve($actor, $permission, (string) $kegiatan->unit_id);
                if (! $decision->allowed) {
                    $this->reject($actor, $decision, $kegiatan);
                }
            }
        }
        $units = Unit::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']);
        $code = $kegiatan ? 'kegiatan:update' : 'kegiatan:create';
        $decisions = $this->resolver->decideManyForUnits($actor, [$code], $units->pluck('id')->all());
        $unitOptions = $units->filter(fn (Unit $unit) => $decisions[$unit->id][$code]['allowed'])->values();
        if ($unitOptions->isEmpty()) {
            $decision = $this->resolver->resolve($actor, $code);
            if ($decision->allowed) {
                $decision = new PermissionDecision(false, $code, ['alasan' => 'no_active_writable_unit']);
            }
            $this->reject($actor, $decision, $kegiatan);
        }

        return [
            'kegiatan' => $kegiatan ? $kegiatan->rencanaAttributes() + ['version_token' => $kegiatan->versionToken()] : null,
            'tahunDefault' => today(config('app.business_timezone'))->year,
            'unitOptions' => $unitOptions->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => Periode::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
        ];
    }

    /** Audit GET forbidden di boundary use case, agar Policy/resolver tetap tanpa efek samping. */
    private function reject(User $actor, PermissionDecision $decision, ?Kegiatan $kegiatan): never
    {
        $this->audit->catat(
            actor: $actor, tindakan: 'kegiatan.form_ditolak', objekTipe: $kegiatan ? 'kegiatan' : 'pengguna', objekId: (string) ($kegiatan?->id ?? $actor->id),
            alasan: 'Izin membuka form rencana kegiatan tidak tersedia atau terkena deny.', dasarIzin: $decision->toAuditBasis(),
        );
        abort(403);
    }
}
