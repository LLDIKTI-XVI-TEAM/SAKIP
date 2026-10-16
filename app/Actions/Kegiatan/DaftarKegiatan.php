<?php

namespace App\Actions\Kegiatan;

use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Policies\KegiatanPolicy;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;

class DaftarKegiatan
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly KegiatanPolicy $policy, private readonly AuditLogger $audit) {}

    /** Penolakan halaman mempertahankan keputusan server, tanpa mempercayai filter unit klien. */
    public function catatPenolakan(User $actor, PermissionDecision $decision): void
    {
        $this->audit->catat(
            actor: $actor, tindakan: 'kegiatan.baca_ditolak', objekTipe: 'pengguna', objekId: (string) $actor->id,
            alasan: 'Izin membaca daftar kegiatan tidak tersedia atau terkena deny.', dasarIzin: $decision->toAuditBasis(),
        );
    }

    /** Gerbang baca global (Q32), baris hanya unit berizin dikurangi deny unit di SQL (Q54), dan capability ubah per objek. @param array<string,mixed> $filters @return array<string,mixed> */
    public function handle(User $actor, array $filters): array
    {
        abort_unless($this->policy->viewAny($actor), 403);
        $tahunDefault = today(config('app.business_timezone'))->year;
        $effectiveFilters = $filters;
        if (empty($effectiveFilters['tahun'])) {
            $effectiveFilters['tahun'] = (string) $tahunDefault;
        }

        $query = Kegiatan::with(['unit:id,nama', 'periode:id,nama'])->whereNotIn('unit_id', $this->resolver->unitDitolak($actor, 'kegiatan:read'))
            ->when($this->resolver->unitTerbaca($actor, 'kegiatan:read'), fn ($query, $terbaca) => $query->whereIn('unit_id', $terbaca));
        foreach (['tahun', 'unit_id', 'periode_id', 'status'] as $field) {
            if (isset($effectiveFilters[$field]) && $effectiveFilters[$field] !== '') {
                $query->where($field, $effectiveFilters[$field]);
            }
        }
        $page = $query->orderByDesc('tahun')->orderByDesc('created_at')->orderBy('id')->paginate(20)->withQueryString();
        $units = Unit::whereNotIn('id', $this->resolver->unitDitolak($actor, 'kegiatan:read'))->orderBy('nama')->get(['id', 'nama']);
        $activeUnitIds = Unit::where('status', 'aktif')->pluck('id');
        $scopes = $activeUnitIds->merge($page->getCollection()->pluck('unit_id'))->unique()->values()->all();
        $decisions = $this->resolver->decideManyForUnits($actor, ['kegiatan:read', 'kegiatan:create', 'kegiatan:update'], $scopes);
        // Q54 §54.3: unit tulis harus juga terbaca agar hasil simpan tetap muncul di daftar ini.
        $boleh = fn (string $unitId, string $code): bool => ($decisions[$unitId][$code]['allowed'] ?? false) && ($decisions[$unitId]['kegiatan:read']['allowed'] ?? false);
        $activeUnits = Unit::where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama']);
        $creatableUnits = $activeUnits
            ->filter(fn (Unit $unit) => $boleh($unit->id, 'kegiatan:create'))
            ->values();
        $editableUnits = $activeUnits
            ->filter(fn (Unit $unit) => $boleh($unit->id, 'kegiatan:update'))
            ->values();
        // Aturan sama dengan SimpanKegiatan agar tombol ubah tidak ditawarkan untuk tahun yang pasti ditolak.
        $tahunDitutup = JadwalTahunan::whereIn('tahun', $page->getCollection()->pluck('tahun')->unique()->values())->where('status', '!=', 'draft')->get()
            ->filter(fn (JadwalTahunan $jadwal): bool => $jadwal->tahunDitutup())->pluck('tahun')->unique()->all();

        $page->through(fn (Kegiatan $kegiatan) => [
            'id' => $kegiatan->id,
            'unit_id' => $kegiatan->unit_id,
            'tahun' => $kegiatan->tahun,
            'periode_id' => $kegiatan->periode_id,
            'nama' => $kegiatan->nama,
            'tujuan' => $kegiatan->tujuan,
            'sasaran_peserta' => $kegiatan->sasaran_peserta,
            'keterangan_peserta' => $kegiatan->keterangan_peserta,
            'lokasi' => $kegiatan->lokasi,
            'status' => $kegiatan->status,
            'tanggal_rencana' => $kegiatan->tanggal_rencana?->format('Y-m-d'),
            'version_token' => $kegiatan->versionToken(),
            'unit' => $kegiatan->unit->only(['id', 'nama']),
            'periode' => $kegiatan->periode->only(['id', 'nama']),
            // Allow dari role tidak bergantung status unit; writer tetap mensyaratkan unit aktif.
            'can' => ['update' => $boleh($kegiatan->unit_id, 'kegiatan:update') && $activeUnitIds->contains($kegiatan->unit_id)
                && ! in_array($kegiatan->tahun, $tahunDitutup, true)],
        ]);

        return [
            'kegiatan' => $page,
            'filters' => array_replace(['tahun' => (string) $tahunDefault, 'unit_id' => '', 'periode_id' => '', 'status' => ''], array_intersect_key($effectiveFilters, array_flip(['tahun', 'unit_id', 'periode_id', 'status']))),
            'unitOptions' => $units->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => Periode::orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
            'creatableUnitOptions' => $creatableUnits->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'editableUnitOptions' => $editableUnits->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'activePeriodeOptions' => Periode::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
            'tahunDefault' => $tahunDefault,
            'can' => ['create' => $activeUnitIds->contains(fn (string $unitId) => $boleh($unitId, 'kegiatan:create'))],
        ];
    }
}
