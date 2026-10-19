<?php

namespace App\Actions\Kegiatan;

use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Policies\KegiatanPolicy;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Kegiatan\AnggaranKegiatan;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimpanKegiatan
{
    public function __construct(
        private readonly ResolveLockedActor $pengunci,
        private readonly KegiatanPolicy $policy,
        private readonly AuditLogger $audit,
        private readonly AnggaranKegiatan $anggaran,
        private readonly PermissionResolver $resolver,
        private readonly JendelaTulisRencanaAksi $jendela,
    ) {}

    /** Audit penolakan awal memakai keputusan asli; target update dari binding, target create diverifikasi di DB. */
    public function catatPenolakan(User $actor, ?Kegiatan $kegiatan, mixed $unitId, string $alasan, ?PermissionDecision $decision = null): void
    {
        $unit = ! $kegiatan && is_string($unitId) && Str::isUuid($unitId) ? Unit::find($unitId) : null;
        $decision ??= $this->policy->keputusanSimpan($actor, $kegiatan, (string) ($kegiatan?->unit_id ?? $unit?->id ?? ''));
        $this->audit->catat(
            actor: $actor, tindakan: $kegiatan ? 'kegiatan.ubah_ditolak' : 'kegiatan.buat_ditolak',
            objekTipe: $kegiatan ? 'kegiatan' : ($unit ? 'unit' : 'pengguna'),
            objekId: (string) ($kegiatan?->id ?? $unit?->id ?? $actor->id),
            alasan: $alasan, dasarIzin: $decision->toAuditBasis(),
        );
    }

    /**
     * Satu use case simpan rencana; aktor/ACL, objek dan master diperiksa ulang di bawah lock.
     * Metadata rencana termasuk anggaran (Q42); tidak mengubah status, narasi pelaksanaan atau snapshot pengukuran.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?string $id = null): Kegiatan
    {
        // UUID PostgreSQL tidak peka huruf besar; perbandingan konteks harus konsisten.
        $data['unit_id'] = strtolower((string) $data['unit_id']);
        $data['periode_id'] = strtolower((string) $data['periode_id']);
        // Anggaran tidak dikirim berarti dikosongkan, agar gerbang wajib anggaran tidak dapat dilewati.
        $data['anggaran'] ??= null;
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $data, $id, &$decision): Kegiatan {
                $locked = $this->pengunci->handle($actor, $id ? 'kegiatan:update' : 'kegiatan:create');
                $currentActor = $locked['aktor'];
                $decision = $locked['keputusan'];
                if (! $currentActor instanceof User || $currentActor->status !== 'aktif') {
                    throw new AuthorizationException('Akun tidak aktif.');
                }
                // Selaras writer periode: aktor/ACL sebelum konfigurasi, lalu objek domain.
                Periode::lockConfiguration();
                // Nama/tujuan/anggaran ikut snapshot klaim RA; header dikunci sebelum jadwal dan kegiatan, searah gerbang klaim dan pengajuan RA.
                $headerKlaim = fn (): array => $id ? KlaimKegiatan::where('kegiatan_id', $id)->where('sumber_klaim', 'rencana_aksi')
                    ->distinct()->orderBy('rencana_aksi_id')->pluck('rencana_aksi_id')->map(fn ($v): string => (string) $v)->all() : [];
                $headerIds = $headerKlaim();
                RencanaAksi::whereIn('id', $headerIds)->orderBy('id')->lockForUpdate()->get();
                // Jadwal dikunci sebelum kegiatan, searah pengajuan pengukuran; tahun lama hanya kandidat dan dicek ulang setelah kegiatan terkunci.
                $tahunLama = $id ? Kegiatan::whereKey($id)->value('tahun') : null;
                $tahun = array_values(array_unique([(int) $data['tahun'], (int) ($tahunLama ?? $data['tahun'])]));
                // Kegiatan tidak terikat Renstra: tahun terkunci bila jadwal tahun itu mana pun sudah ditutup; shared lock menyerialkan dengan penutupan.
                $ditutup = JadwalTahunan::whereIn('tahun', $tahun)->where('status', '!=', 'draft')->orderBy('id')->sharedLock()->get()
                    ->contains(fn (JadwalTahunan $jadwal): bool => $jadwal->tahunDitutup());
                $kegiatan = $id ? Kegiatan::whereKey($id)->lockForUpdate()->firstOrFail() : null;
                if ($kegiatan && (int) $kegiatan->tahun !== (int) $tahunLama) {
                    throw ValidationException::withMessages(['expected_state' => 'Kegiatan telah berubah. Muat ulang sebelum menyimpan.']);
                }
                $unitId = (string) $data['unit_id'];
                $decision = $this->policy->keputusanSimpan($currentActor, $kegiatan, $unitId);
                if (($decision->basis['alasan'] ?? $decision->basis['tujuan']['alasan'] ?? null) === 'inactive_unit') {
                    throw ValidationException::withMessages(['unit_id' => 'Unit kegiatan harus tetap aktif.']);
                }
                if (! $decision->allowed) {
                    throw new AuthorizationException('Izin kegiatan tidak tersedia atau telah dicabut.');
                }
                $unitIds = array_unique([$unitId, $kegiatan?->unit_id ?? $unitId]);
                $units = Unit::whereIn('id', $unitIds)->orderBy('id')->sharedLock()->get()->keyBy('id');
                if ($units->count() !== count($unitIds) || $units->contains(fn (Unit $unit) => $unit->status !== 'aktif')) {
                    throw ValidationException::withMessages(['unit_id' => 'Unit kegiatan harus tetap aktif.']);
                }
                $periode = Periode::whereKey($data['periode_id'])->sharedLock()->first();
                if (! $periode || ! $periode->aktif) {
                    throw ValidationException::withMessages(['periode_id' => 'Master periode tidak tersedia atau sudah nonaktif.']);
                }
                if ($ditutup) {
                    throw ValidationException::withMessages(['tahun' => 'Tahun kegiatan sudah ditutup; perubahan hanya melalui pembukaan kembali jadwal.']);
                }
                if ($kegiatan && ! hash_equals($kegiatan->versionToken(), (string) ($data['expected_state'] ?? ''))) {
                    throw ValidationException::withMessages(['expected_state' => 'Kegiatan telah berubah. Muat ulang sebelum menyimpan.']);
                }
                if ($kegiatan) {
                    $changedContext = array_filter(['unit_id', 'tahun', 'periode_id'], fn ($field) => (string) $data[$field] !== (string) $kegiatan->{$field});
                    // Jaga integritas klaim yang sudah ada. Jangan lock child: submit mengunci klaim sebelum kegiatan.
                    if ($changedContext !== [] && KlaimKegiatan::where('kegiatan_id', $kegiatan->id)->exists()) {
                        throw ValidationException::withMessages(array_fill_keys($changedContext, 'Konteks kegiatan yang sudah diklaim tidak dapat dipindahkan.'));
                    }
                }
                $jalurPerencanaan = $this->jendela->jalurPerencanaan($this->resolver->resolve($currentActor, $id ? 'kegiatan:update' : 'kegiatan:create', $unitId));
                if ($data['anggaran'] === null && ! $this->anggaran->bolehKosong((int) $data['tahun'], $data['periode_id'], $jalurPerencanaan)) {
                    throw ValidationException::withMessages(['anggaran' => AnggaranKegiatan::PESAN_WAJIB]);
                }
                $before = $kegiatan?->rencanaAttributes();
                $values = Arr::only($data, Kegiatan::RENCANA_FIELDS);
                if ($kegiatan) {
                    // Klaim baru wajib mengunci kegiatan; selisih hanya mungkin sebelum lock di atas.
                    if ($headerKlaim() !== $headerIds) {
                        throw ValidationException::withMessages(['expected_state' => 'Kegiatan telah berubah. Muat ulang sebelum menyimpan.']);
                    }
                    $kegiatan->fill($values);
                    if ($kegiatan->isDirty(['nama', 'tujuan', 'anggaran'])) {
                        // Token expected_versi RA terbuka harus basi agar pengajuan tidak membekukan atribut yang belum dilihat pengaju.
                        RencanaAksi::whereIn('id', $headerIds)->increment('versi');
                    }
                    $kegiatan->save();
                } else {
                    $kegiatan = Kegiatan::create($values + ['status' => 'rencana', 'created_by' => $currentActor->id]);
                }
                $this->audit->catat(
                    actor: $currentActor, tindakan: $id ? 'kegiatan.ubah' : 'kegiatan.buat', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
                    nilaiLama: $before, nilaiBaru: $kegiatan->rencanaAttributes(), alasan: $id ? 'Pembaruan data rencana kegiatan unit.' : 'Pencatatan rencana kegiatan unit.', dasarIzin: $decision->toAuditBasis(),
                );

                return $kegiatan;
            });
        } catch (AuthorizationException $exception) {
            // Penolakan dari boundary terkunci dicatat sesudah rollback, bukan oleh Policy atau payload klien.
            $this->audit->catat(actor: $actor, tindakan: $id ? 'kegiatan.ubah_ditolak' : 'kegiatan.buat_ditolak', objekTipe: $id ? 'kegiatan' : 'unit', objekId: $id ?? (string) $data['unit_id'], alasan: $exception->getMessage(), dasarIzin: $decision instanceof PermissionDecision ? $decision->toAuditBasis() : null);
            throw $exception;
        }
    }
}
