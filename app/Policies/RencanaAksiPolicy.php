<?php

namespace App\Policies;

use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Policy murni tanpa efek samping: penolakan dicatat pemanggil yang tahu
 * konteksnya (FormRequest simpan/buat, atau Action di dalam transaksi),
 * sehingga Gate yang sama dapat dipakai pratinjau tanpa tercatat sebagai
 * percobaan simpan.
 */
class RencanaAksiPolicy
{
    public function __construct(
        private readonly PermissionResolver $resolver,
    ) {}

    /**
     * Baca daftar memakai izin global; scope unit ditegakkan pada tiap baris via view().
     */
    public function viewAny(User $user): bool
    {
        return $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_READ);
    }

    /**
     * Baca satu header memakai izin baca global yang diuji terhadap unit header.
     *
     * Fail-closed saat unit snapshot versi pengajuan terbaru tidak selaras dengan
     * unit header (data beku tidak boleh dibaca memakai scope unit berbeda);
     * anomali dicatat. Deny unit-spesifik maupun global tetap menang via resolver;
     * tanpa audit baca agar penolakan awal tidak membanjiri jejak audit.
     */
    public function view(User $user, RencanaAksi $header): bool
    {
        $snapshotUnit = $this->snapshotUnit($header);
        if ($snapshotUnit !== null && $snapshotUnit !== (string) $header->unit_id) {
            Log::warning('rencana_aksi.unit_snapshot_tidak_konsisten', [
                'rencana_aksi_id' => $header->id, 'unit_id' => $header->unit_id, 'snapshot_unit_id' => $snapshotUnit,
            ]);

            return false;
        }

        return $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_READ, (string) $header->unit_id);
    }

    /**
     * Buat draf memakai unit indikator calon header (belum ada baris).
     *
     * PIC memakai grant unit + penugasan efektif (diperiksa Action/jendela);
     * Perencanaan lolos via peran global tanpa grant unit. Deny menang.
     */
    public function create(User $user, IndikatorKinerja $indikator): Response
    {
        $decision = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_CREATE, (string) $indikator->unit_id);

        return $this->response($decision, 'Izin pembuatan rencana aksi tidak tersedia atau telah dicabut.');
    }

    /**
     * Simpan target memakai unit snapshot header (bukan unit indikator berjalan).
     *
     * Hak tulis mengikuti unit header + PIC efektif saat transaksi (Action);
     * penanggung jawab historis header bukan dasar izin berjalan.
     */
    public function update(User $user, RencanaAksi $header): Response
    {
        $decision = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $header->unit_id);

        return $this->response($decision, 'Izin penyimpanan target rencana aksi tidak tersedia atau telah dicabut.');
    }

    public function sahkan(User $user, RencanaAksi $header): Response
    {
        return $this->capability($user, $header, PermissionCodes::RENCANA_AKSI_SAHKAN);
    }

    /**
     * Isi bukti (tautan/isi_teks) hanya dibuka bila baca ringkasan lolos dan
     * akses berkas tidak ditolak. Deny berkas:read menang atas fallback kelola.
     */
    public function viewEvidence(User $user, RencanaAksi $header): bool
    {
        if (! $this->view($user, $header)) {
            return false;
        }
        $decision = $this->resolver->decide($user, PermissionCodes::BERKAS_READ, (string) $header->unit_id);
        if (in_array($decision['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'], true)) {
            return false;
        }

        return $decision['allowed'] || $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $header->unit_id) || $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_AJUKAN, (string) $header->unit_id);
    }

    /**
     * Guard bukti pasca-sah: status disahkan membeku semua; setelah buka-kembali
     * (dikembalikan + versi tersahkan ada) hanya ID dalam snapshot resmi yang beku.
     * Bukti baru pasca-buka-kembali (ID tidak ada di snapshot resmi) tetap bisa dihapus.
     * FIX2: cek SEMUA versi dengan disahkan_at NOT NULL (satu query versions),
     * bukan hanya ratifiedVersion terbaru — ID yang muncul di salah satu snapshot
     * resmi tetap beku walau versi terbaru tidak merujuknya lagi.
     */
    public function deleteEvidence(User $user, RencanaAksi $header, BuktiDukung $bukti): Response
    {
        if ($bukti->berkasable_type !== 'rencana_aksi' || $bukti->berkasable_id !== $header->id) {
            return Response::deny('Bukti tidak terkait dengan rencana aksi ini.');
        }
        if ($header->status_alur === 'disahkan') {
            return Response::deny('Bukti yang dirujuk versi resmi tidak boleh dihapus.');
        }
        $frozenIds = $header->versions()->whereNotNull('disahkan_at')->get(['snapshot'])
            ->flatMap(fn ($version) => is_array($version->snapshot) ? array_values($version->snapshot['bukti_dukungs'] ?? []) : [])
            ->pluck('id')->filter()->unique()->values()->all();
        if (in_array($bukti->id, $frozenIds, true)) {
            return Response::deny('Bukti yang dirujuk versi resmi tidak boleh dihapus.');
        }
        if (! $this->resolver->allows($user, PermissionCodes::BERKAS_DELETE, (string) $header->unit_id)) {
            return Response::deny('Izin tindakan tidak tersedia atau telah dicabut.');
        }

        return Response::allow();
    }

    /** Dipakai Gate sahkan: izin kanonis lalu gabungan aturan bisnis. */
    private function capability(User $user, RencanaAksi $header, string $permission): Response
    {
        if (! $this->resolver->allows($user, $permission, (string) $header->unit_id)) {
            return Response::deny('Izin tindakan tidak tersedia atau telah dicabut.');
        }
        $errors = $this->businessErrors($user, $header);

        return $errors === [] ? Response::allow() : Response::deny(implode(' ', $errors));
    }

    /**
     * Dipakai Action setelah resolver dan Gate sahkan; kegagalan bisnis
     * menghasilkan validasi, bukan keputusan deny palsu.
     *
     * Konteks beku berasal dari snapshot versi pengajuan terbaru
     * (`rencana_aksi_versi.jadwal_snapshot_id`), bukan kolom header —
     * header tidak lagi menyimpan rujukan snapshot (D7).
     */
    public function businessErrors(User $user, RencanaAksi $header): array
    {
        $version = $header->latestVersion;
        if (! $version) {
            return ['Versi pengajuan yang direviu belum tersedia atau tidak cocok.'];
        }
        // Action sahkan memasang relasi snapshot terkunci; jalur Gate membaca
        // snapshot versi (tanpa lock) dengan kontrak data yang sama.
        /** @var JadwalSnapshot|null $snapshot */
        $snapshot = $header->relationLoaded('jadwalSnapshot') ? $header->getRelation('jadwalSnapshot') : $version->jadwalSnapshot;
        if (! $snapshot || $snapshot->indikator_id !== $header->indikator_id || $snapshot->jadwal->tahun !== $header->tahun) {
            return ['Snapshot jadwal tidak cocok dengan rencana aksi.'];
        }
        $jadwal = $snapshot->jadwal;
        $errors = [];
        // Versi yang direviu wajib merujuk snapshot yang dievaluasi;
        // dicek sebelum evaluasi penutupan/sesi koreksi.
        if ((string) $version->jadwal_snapshot_id !== (string) $snapshot->id) {
            $errors[] = 'Versi pengajuan yang direviu belum tersedia atau tidak cocok.';
        }
        if ((string) $snapshot->jadwal_id !== (string) $header->jadwal_tahunan_id) {
            $errors[] = 'Jadwal tahunan rencana aksi tidak cocok dengan snapshot jadwal.';
        }
        if ((string) $header->unit_id !== (string) $snapshot->unit_id) {
            $errors[] = 'Unit rencana aksi tidak cocok dengan snapshot jadwal.';
        }
        if ($jadwal->renstra_id !== $header->indikator->sasaranStrategis->renstra_id) {
            $errors[] = 'Renstra jadwal tidak cocok dengan indikator.';
        }
        // FIX1: bila Action sudah mengunci baris unit (SahkanRencanaAksi), nilai
        // terkunci yang dipakai — bukan baca ulang tanpa lock (anti-TOCTOU).
        // Jalur Gate tanpa transaksi memakai cek DB seperti sebelumnya.
        $unitAktif = $this->lockedUnitStatus($header)
            ?? DB::table('unit')->where('id', $snapshot->unit_id)->where('status', 'aktif')->exists();
        if (! $unitAktif) {
            $errors[] = 'Unit organisasi rencana aksi berstatus nonaktif.';
        }
        if ($jadwal->status !== 'aktif') {
            $errors[] = 'Jadwal tahunan harus aktif.';
        }
        // F1: pemisahan tugas keras jalur PIC memakai provenance beku versi, bukan created_by header.
        if ($version->diajukan_by === $user->id && $version->jalur_pengajuan === 'pic') {
            $errors[] = 'Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.';
        }
        if ($header->status_alur !== 'diverifikasi') {
            $errors[] = 'Status rencana aksi tidak sesuai untuk tindakan ini.';
        }
        $date = today(config('app.business_timezone'))->toDateString();
        $scope = $jadwal->lingkup_koreksi ?? [];
        $correction = $jadwal->koreksi_mulai && $jadwal->koreksi_sampai && now()->betweenIncluded($jadwal->koreksi_mulai, $jadwal->koreksi_sampai)
            && in_array($header->indikator_id, $scope['indikator_ids'] ?? [], true)
            && in_array('rencana_aksi', $scope['jenis_objek'] ?? [], true);
        if ($jadwal->penutupan && $date > $jadwal->penutupan->toDateString() && ! $correction) {
            $errors[] = 'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup rencana aksi ini.';
        }

        return $errors;
    }

    /**
     * Unit snapshot versi pengajuan terbaru; null bila belum ada versi.
     * Relasi versi dimuat lazy bila belum tersedia.
     */
    private function snapshotUnit(RencanaAksi $header): ?string
    {
        $version = $header->relationLoaded('latestVersion') ? $header->getRelation('latestVersion') : $header->latestVersion()->first();
        if (! $version instanceof RencanaAksiVersi) {
            return null;
        }
        $snapshot = $version->jadwalSnapshot;

        return $snapshot?->unit_id !== null ? (string) $snapshot->unit_id : null;
    }

    /**
     * Status unit dari relasi terkunci (SahkanRencanaAksi mengunci
     * jadwalSnapshot.unit via lockForUpdate). Null bila relasi tak dimuat —
     * pemanggil memakai cek DB biasa.
     */
    private function lockedUnitStatus(RencanaAksi $header): ?bool
    {
        /** @var JadwalSnapshot|null $snapshot */
        $snapshot = $header->relationLoaded('jadwalSnapshot') ? $header->getRelation('jadwalSnapshot') : null;
        $unit = $snapshot && $snapshot->relationLoaded('unit') ? $snapshot->unit : null;

        return $unit ? $unit->status === 'aktif' : null;
    }

    private function response(PermissionDecision $decision, string $pesan): Response
    {
        return $decision->allowed ? Response::allow() : Response::deny($pesan);
    }
}
