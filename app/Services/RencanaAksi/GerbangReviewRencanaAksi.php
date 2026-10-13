<?php

namespace App\Services\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GerbangReviewRencanaAksi
{
    public function __construct(
        private readonly ResolveLockedActor $lockedActor,
        private readonly PermissionResolver $resolver,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Urutan kunci mengikuti penulis RA/PJ: aktor + ACL, header, indikator,
     * jadwal, unit, lalu versi pengajuan dan snapshot rujukannya.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $jejak
     * @return array{User, RencanaAksi, RencanaAksiVersi}
     */
    public function kunci(User $actor, string $id, array $data, string $permission, array &$jejak): array
    {
        $kunci = $this->lockedActor->handle($actor, $permission);
        $jejak['dasar_izin'] = $kunci['keputusan']->toAuditBasis();
        $pengunci = $kunci['aktor'];
        if (! $pengunci || $pengunci->status !== 'aktif') {
            throw new AuthorizationException('Akun pengguna tidak aktif.');
        }
        $header = RencanaAksi::lockForUpdate()->findOrFail($id);
        $keputusan = $this->resolver->resolve($pengunci, $permission, (string) $header->unit_id);
        $jejak['dasar_izin'] = $keputusan->toAuditBasis();
        $jejak['sebelum'] = ['status_alur' => $header->status_alur, 'versi' => $header->versi];
        if (! $keputusan->allowed) {
            throw new AuthorizationException('Izin review rencana aksi tidak tersedia atau telah dicabut.');
        }
        $header->setRelation('indikator', IndikatorKinerja::lockForUpdate()->findOrFail($header->indikator_id));
        $header->setRelation('jadwalTahunan', JadwalTahunan::lockForUpdate()->findOrFail($header->jadwal_tahunan_id));
        $header->setRelation('unit', Unit::whereKey($header->unit_id)->sharedLock()->first());
        $versi = $header->versions()->reorder('nomor', 'desc')->lockForUpdate()->first();
        Validator::make($data, [
            'expected_versi' => ['required', 'integer', 'min:1'],
            'versi_pengajuan_id' => ['required', 'uuid'],
        ])->validate();
        if ($header->versi !== (int) $data['expected_versi'] || ! $versi || $versi->id !== strtolower($data['versi_pengajuan_id'])) {
            throw ValidationException::withMessages(['expected_versi' => 'Data atau versi pengajuan telah berubah. Muat ulang sebelum mengulangi review.'])->status(409);
        }
        $versi->setRelation('jadwalSnapshot', JadwalSnapshot::whereKey($versi->jadwal_snapshot_id)->sharedLock()->first());
        // Verifikasi dan pengesahan adalah persetujuan; pengembalian tidak menandai self_approval.
        $jejak['sebelum'] += $this->provenance($pengunci, $versi, in_array($permission, [PermissionCodes::RENCANA_AKSI_VERIFIKASI, PermissionCodes::RENCANA_AKSI_SAHKAN], true));

        return [$pengunci, $header, $versi];
    }

    /** @return list<string> */
    public function businessErrors(User $actor, RencanaAksi $header, ?RencanaAksiVersi $versi, string $aksi): array
    {
        $errors = [];
        $indikator = $header->indikator;
        $jadwal = $header->jadwalTahunan;
        if (! $indikator || $indikator->isArsip()) {
            $errors[] = 'Indikator berstatus arsip; review rencana aksi ditolak.';
        }
        if ($header->unit?->status !== 'aktif') {
            $errors[] = 'Unit pemilik rencana aksi berstatus nonaktif; review ditolak.';
        }
        $status = match ($aksi) {
            'verifikasi' => [RencanaAksi::STATUS_DIAJUKAN],
            'kembalikan' => [RencanaAksi::STATUS_DIAJUKAN, RencanaAksi::STATUS_DIVERIFIKASI],
            'sahkan' => [RencanaAksi::STATUS_DIVERIFIKASI],
        };
        if (! in_array($header->status_alur, $status, true)) {
            $errors[] = 'Status rencana aksi tidak sesuai untuk tindakan ini.';
        }
        $snapshot = $versi?->jadwalSnapshot;
        $beku = $versi?->snapshot ?? [];
        $konteksSah = Validator::make($beku, [
            'indikator.kode' => ['required', 'string'], 'indikator.nama' => ['required', 'string'],
            'unit_kerja.id' => ['required', 'uuid'], 'unit_kerja.nama' => ['required', 'string'],
        ])->passes();
        $isiSah = Validator::make($beku, [
            'target_periode' => ['required', 'array', 'min:1'],
            'target_periode.*.periode_id' => ['required', 'uuid'],
            'target_periode.*.periode_nama' => ['required', 'string'],
            'target_periode.*.periode_urutan' => ['required', 'integer'],
            // Data Model §2.34: produsen hanya membekukan baris `terhitung` beserta nilainya.
            'target_periode.*.nilai' => ['required', 'numeric'],
            'target_periode.*.status_perhitungan' => ['required', 'in:terhitung'],
            'bukti_dukungs' => ['present', 'array'],
            'bukti_dukungs.*.id' => ['required', 'uuid'],
            'bukti_dukungs.*.mode' => ['required', 'in:file,tautan,teks'],
            'bukti_dukungs.*.tautan' => ['required_if:bukti_dukungs.*.mode,tautan', 'nullable', 'string'],
            'bukti_dukungs.*.isi_teks' => ['required_if:bukti_dukungs.*.mode,teks', 'nullable', 'string'],
            'bukti_dukungs.*.nama_asli' => ['required_if:bukti_dukungs.*.mode,file', 'nullable', 'string'],
        ] + ($snapshot?->tipe_perhitungan === 'manual' ? ['target_periode.*.komponen' => ['present', 'array']] : [
            'target_periode.*.komponen' => ['required', 'array', 'min:1'],
            'target_periode.*.komponen.*.komponen_id' => ['required', 'uuid'],
            'target_periode.*.komponen.*.kode' => ['required', 'string'],
            'target_periode.*.komponen.*.label' => ['required', 'string'],
            'target_periode.*.komponen.*.nilai' => ['required', 'numeric'],
        ]))->passes();
        if (! $versi || ! $snapshot || ! $jadwal
            || $snapshot->jadwal_id !== $header->jadwal_tahunan_id || $snapshot->indikator_id !== $header->indikator_id
            || $snapshot->unit_id !== $header->unit_id || $jadwal->tahun !== $header->tahun
            || ($beku['unit_kerja']['id'] ?? null) !== $header->unit_id
            || ! isset($beku['indikator']['kode'], $beku['indikator']['nama'])
            // Pengembalian tetap boleh agar versi yang isinya rusak dapat diganti versi baru.
            || ! $konteksSah || ($aksi !== 'kembalikan' && ! $isiSah)
            || ! in_array($versi->jalur_pengajuan, ['pic', 'perencanaan'], true)) {
            $errors[] = 'Konteks versi pengajuan belum lengkap atau tidak selaras; review ditolak.';
        }
        if (! $jadwal || $jadwal->status !== 'aktif') {
            $errors[] = 'Jadwal tahunan tidak aktif.';
        } elseif ($indikator && $this->jendela->tahunDitutup($jadwal) && ! $this->jendela->sesiKoreksiAktif($indikator, $jadwal)) {
            // Lingkup periode dijaga saat simpan; producer membekukan seluruh periode efektif.
            $errors[] = 'Tahun jadwal telah ditutup; review memerlukan sesi koreksi resmi untuk indikator ini.';
        }
        // F1 mengikuti provenance immutable, bukan peran atau PJ berjalan; pengembalian dikecualikan.
        if ($aksi !== 'kembalikan' && $versi?->jalur_pengajuan === 'pic' && $versi->diajukan_by === $actor->id) {
            $errors[] = 'F1: pengaju melalui jalur PIC tidak boleh memverifikasi atau mengesahkan pengajuannya sendiri.';
        }

        return $errors;
    }

    public function pastikan(User $actor, RencanaAksi $header, RencanaAksiVersi $versi, string $aksi): void
    {
        $errors = $this->businessErrors($actor, $header, $versi, $aksi);
        if ($errors !== []) {
            throw ValidationException::withMessages(['businessErrors' => $errors]);
        }
    }

    /** @return array<string, mixed> */
    public function provenance(User $actor, RencanaAksiVersi $versi, bool $persetujuan = true): array
    {
        return ['versi_pengajuan_id' => $versi->id, 'nomor_pengajuan' => $versi->nomor,
            'diajukan_by' => $versi->diajukan_by, 'jalur_pengajuan' => $versi->jalur_pengajuan,
            'self_approval' => $persetujuan && $versi->jalur_pengajuan === 'perencanaan' && $versi->diajukan_by === $actor->id];
    }

    /** @param array<string, mixed> $jejak */
    public function catatPenolakan(User $actor, string $id, string $aksi, array $jejak, AuthorizationException|ValidationException $error): void
    {
        $pesan = $error instanceof ValidationException ? implode(' ', array_merge(...array_values($error->errors()))) : $error->getMessage();
        $this->audit->catat($actor, "rencana_aksi.{$aksi}_ditolak", 'rencana_aksi', $id,
            $jejak['sebelum'] ?? null, null, mb_substr($pesan, 0, 1000), $jejak['dasar_izin'] ?? null);
    }
}
