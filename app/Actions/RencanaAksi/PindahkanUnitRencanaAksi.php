<?php

namespace App\Actions\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PindahkanUnitRencanaAksi
{
    public function __construct(
        private readonly ResolveLockedActor $lockedActor,
        private readonly PermissionResolver $resolver,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, string $id, array $data): void
    {
        $dasarIzin = null;
        try {
            DB::transaction(function () use ($actor, $id, $data, &$dasarIzin): void {
                $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_UPDATE);
                $pengunci = $kunci['aktor'];
                if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
                    $dasarIzin = $kunci['keputusan']->toAuditBasis();
                    throw new AuthorizationException('Akun pengguna tidak aktif.');
                }
                Periode::lockConfiguration();
                $header = RencanaAksi::lockForUpdate()->findOrFail($id);
                $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($header->indikator_id);
                $jadwal = JadwalTahunan::lockForUpdate()->findOrFail($header->jadwal_tahunan_id);
                $lama = (string) $header->unit_id;
                $baru = (string) $indikator->unit_id;
                $asal = $this->resolver->resolve($pengunci, PermissionCodes::RENCANA_AKSI_UPDATE, $lama);
                $tujuan = $this->resolver->resolve($pengunci, PermissionCodes::RENCANA_AKSI_UPDATE, $baru);
                $dasarIzin = ['unit_asal' => $asal->toAuditBasis(), 'unit_tujuan' => $tujuan->toAuditBasis()];
                if (! $this->jendela->jalurPerencanaan($asal) || ! $this->jendela->jalurPerencanaan($tujuan)) {
                    throw new AuthorizationException('Izin pemindahan pada kedua unit tidak tersedia.');
                }
                $input = Validator::make($data, [
                    'expected_versi' => ['required', 'integer', 'min:1'],
                    'alasan' => ['required', 'string', 'max:1000'],
                    'unit_id' => ['prohibited'],
                ])->validate();
                $alasan = trim(AuditReason::sanitize($input['alasan']));
                if ($alasan === '') {
                    throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi.']);
                }
                if ($header->versi !== (int) $input['expected_versi']) {
                    throw ValidationException::withMessages(['expected_versi' => 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.'])->status(409);
                }
                if ($lama === $baru) {
                    throw ValidationException::withMessages(['unit_id' => 'Rencana aksi sudah berada pada unit indikator.']);
                }
                if (Unit::whereKey($baru)->sharedLock()->value('status') !== 'aktif') {
                    throw ValidationException::withMessages(['unit_id' => 'Unit tujuan tidak aktif.']);
                }
                if ($jadwal->status !== 'aktif' || $jadwal->tahunDitutup() || $indikator->isArsip()) {
                    throw ValidationException::withMessages(['jadwal' => 'Pemindahan hanya tersedia pada jadwal aktif dan indikator yang tidak diarsipkan.']);
                }
                if ($header->status_alur !== RencanaAksi::STATUS_DRAFT || $header->versions()->exists()) {
                    throw ValidationException::withMessages(['status_alur' => 'Rencana aksi yang pernah diajukan tidak dapat dipindahkan.']);
                }
                $ids = KlaimKegiatan::where('rencana_aksi_id', $id)->orderBy('id')->lockForUpdate()->pluck('kegiatan_id')->unique()->values()->all();
                $kegiatan = Kegiatan::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($kegiatan->contains(fn (Kegiatan $item): bool => $item->status !== 'rencana' || (string) $item->unit_id !== $lama)
                    || KlaimKegiatan::whereIn('kegiatan_id', $ids)->where('rencana_aksi_id', '!=', $id)->exists()) {
                    throw ValidationException::withMessages(['kegiatan' => 'Kegiatan tidak dapat dipindahkan karena status atau klaim lain.']);
                }
                foreach ($ids as $kegiatanId) {
                    foreach (['rencana_aksi_versi', 'pengukuran_versi'] as $table) {
                        if (DB::table($table)->whereJsonContains('snapshot->klaim', [['kegiatan_id' => $kegiatanId]])->exists()) {
                            throw ValidationException::withMessages(['kegiatan' => 'Kegiatan memiliki riwayat beku dan tidak dapat dipindahkan.']);
                        }
                    }
                }
                Kegiatan::whereIn('id', $ids)->update(['unit_id' => $baru]);
                $header->unit_id = $baru;
                $header->versi++;
                $header->save();
                $this->audit->catat(actor: $pengunci, tindakan: 'rencana_aksi.pindah_unit', objekTipe: 'rencana_aksi', objekId: $id,
                    nilaiLama: ['unit_id' => $lama], nilaiBaru: ['unit_id' => $baru, 'kegiatan_ids' => $ids], alasan: $alasan, dasarIzin: $dasarIzin);
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && RencanaAksi::whereKey($id)->exists()) {
                $this->audit->catat(actor: $actor, tindakan: 'rencana_aksi.pindah_unit_ditolak', objekTipe: 'rencana_aksi', objekId: $id,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Pemindahan unit rencana aksi ditolak.'), dasarIzin: $dasarIzin);
            }
            throw $exception;
        }
    }
}
