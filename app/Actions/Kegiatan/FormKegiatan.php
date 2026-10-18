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
    public function handle(User $actor, Kegiatan $kegiatan): array
    {
        foreach (['kegiatan:read', 'kegiatan:update'] as $permission) {
            $decision = $this->resolver->resolve($actor, $permission, (string) $kegiatan->unit_id);
            if (! $decision->allowed) {
                $this->reject($actor, $decision, $kegiatan);
            }
        }
        // Allow dari role tidak bergantung status unit; writer selalu menolak unit sumber nonaktif.
        if (! Unit::whereKey($kegiatan->unit_id)->where('status', 'aktif')->exists()) {
            $this->reject($actor, new PermissionDecision(false, 'kegiatan:update', ['alasan' => 'inactive_unit']), $kegiatan);
        }
        $units = Unit::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']);
        $decisions = $this->resolver->decideManyForUnits($actor, ['kegiatan:update', 'kegiatan:read'], $units->pluck('id')->all());
        // Q54 §54.3: unit tujuan pemindahan juga harus terbaca agar hasil simpan tetap terlihat.
        $unitOptions = $units->filter(fn (Unit $unit) => $decisions[$unit->id]['kegiatan:update']['allowed'] && $decisions[$unit->id]['kegiatan:read']['allowed'])->values();

        return [
            'kegiatan' => $kegiatan->rencanaAttributes() + ['version_token' => $kegiatan->versionToken()],
            'unitOptions' => $unitOptions->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => Periode::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
        ];
    }

    /** Audit GET forbidden di boundary use case, agar Policy/resolver tetap tanpa efek samping. */
    private function reject(User $actor, PermissionDecision $decision, Kegiatan $kegiatan): never
    {
        $this->audit->catat(
            actor: $actor, tindakan: 'kegiatan.form_ditolak', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
            alasan: 'Izin membuka form rencana kegiatan tidak tersedia atau terkena deny.', dasarIzin: $decision->toAuditBasis(),
        );
        abort(403);
    }
}
