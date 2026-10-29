<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use App\Support\PermissionDecision;

class FormKegiatan
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly AuditLogger $audit,
        private readonly PersyaratanPerencanaanKegiatan $persyaratan) {}

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
        $periodeAktif = Periode::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['id', 'nama', 'urutan', ...Periode::TANGGAL]);
        // Aturan sama dengan halaman kegiatan Rencana Aksi: aksi status tidak ditawarkan untuk tahun yang pasti ditolak.
        $tahunDitutup = GerbangBerkasKegiatan::tahunDitutup((int) $kegiatan->tahun);
        $pilihan = $kegiatan->loadMissing('periode')->pilihanStatus($periodeAktif);
        $ubahStatus = $tahunDitutup ? [] : $pilihan['status'];
        // Persyaratan pelaksanaan dinilai ulang dari klaim saat ini: klaim baru setelah terlaksana dapat menambah bukti wajib (Q22).
        $kurang = PersyaratanPerencanaanKegiatan::kurang($this->persyaratan->evaluasi($kegiatan,
            PersyaratanPerencanaanKegiatan::indikatorDiklaim((string) $kegiatan->id), null, PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN));

        return [
            'kegiatan' => $kegiatan->rencanaAttributes() + [
                ...$kegiatan->only(Kegiatan::REALISASI_FIELDS),
                'tanggal_realisasi' => $kegiatan->tanggal_realisasi?->format('Y-m-d'),
                'version_token' => $kegiatan->versionToken(),
                'justifikasi' => $kegiatan->justifikasi,
                'periode_nama' => $kegiatan->periode->nama,
                // Nama periode kegiatan lanjutan, bila kegiatan ini sudah ditunda.
                'periode_tujuan' => Kegiatan::where('kegiatan_asal_id', $kegiatan->id)->with('periode:id,nama')->first()?->periode?->nama,
            ],
            'unitOptions' => $unitOptions->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => $periodeAktif->map(fn (Periode $periode) => [...$periode->only(['id', 'nama']), 'tanggal' => $periode->only(Periode::TANGGAL)])->all(),
            'pelaksanaan' => ['persyaratan_kurang' => $kurang, 'perlu_koreksi' => $kegiatan->status === 'terlaksana' && $kurang !== [],
                'rentang' => $kegiatan->periode->rentangPadaTahun((int) $kegiatan->tahun)],
            'opsiPeriodeTujuan' => in_array('ditunda', $ubahStatus, true) ? $pilihan['periode_tujuan'] : [],
            // Data rencana baca-saja setelah status berubah (Q69 §69.3).
            'can' => ['ubah_rencana' => $kegiatan->status === 'rencana', 'ubah_status' => $ubahStatus,
                'catat_realisasi' => ! $tahunDitutup && in_array($kegiatan->status, ['rencana', 'tidak_terlaksana', 'terlaksana'], true),
                'tandai_terlaksana' => ! $tahunDitutup && in_array($kegiatan->status, ['rencana', 'tidak_terlaksana'], true)],
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
