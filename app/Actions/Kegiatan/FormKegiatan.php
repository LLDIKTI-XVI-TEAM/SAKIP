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
            // Allow dari role tidak bergantung status unit; writer selalu menolak unit sumber nonaktif.
            if (! Unit::whereKey($kegiatan->unit_id)->where('status', 'aktif')->exists()) {
                $this->reject($actor, new PermissionDecision(false, 'kegiatan:update', ['alasan' => 'inactive_unit']), $kegiatan);
            }
        } else {
            // Q54 §54.3: tanpa izin baca, hasil simpan tidak dapat dibuka kembali dari daftar.
            $baca = $this->resolver->resolve($actor, 'kegiatan:read');
            if (! $baca->allowed) {
                $this->reject($actor, $baca, null);
            }
        }
        $units = Unit::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']);
        $code = $kegiatan ? 'kegiatan:update' : 'kegiatan:create';
        $decisions = $this->resolver->decideManyForUnits($actor, [$code, 'kegiatan:read'], $units->pluck('id')->all());
        // Alasan tulis didahulukan; keputusan baca hanya dilaporkan bila tulis sudah diizinkan.
        $perUnit = array_map(fn (array $unit) => $unit[$code]['allowed'] ? $unit['kegiatan:read'] : $unit[$code], $decisions);
        $unitOptions = $units->filter(fn (Unit $unit) => $perUnit[$unit->id]['allowed'])->values();
        if ($unitOptions->isEmpty()) {
            $this->reject($actor, $this->keputusanTanpaUnit($code, array_values($perUnit)), $kegiatan);
        }

        return [
            'kegiatan' => $kegiatan ? $kegiatan->rencanaAttributes() + ['version_token' => $kegiatan->versionToken()] : null,
            'tahunDefault' => today(config('app.business_timezone'))->year,
            'unitOptions' => $unitOptions->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => Periode::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
        ];
    }

    /**
     * Permission ini unit-scoped; keputusan tanpa unit selalu `invalid_scope`. Gabungkan keputusan
     * batch per unit agar audit tetap memuat alasan, sumber allow, dan ID deny yang sebenarnya.
     *
     * @param  list<array{reason: string, roles: list<string>, grants: list<string>, denies: list<string>}>  $perUnit
     */
    private function keputusanTanpaUnit(string $code, array $perUnit): PermissionDecision
    {
        $alasan = array_values(array_unique(array_column($perUnit, 'reason')));
        $gabung = fn (string $key) => array_values(array_unique(array_merge([], ...array_column($perUnit, $key))));

        return new PermissionDecision(false, $code, [
            'alasan' => count($alasan) === 1 ? $alasan[0] : 'no_active_writable_unit',
            'alasan_unit' => $alasan,
            'sumber_allow' => ['roles' => $gabung('roles'), 'grants' => $gabung('grants')],
            'deny' => $gabung('denies'),
        ]);
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
