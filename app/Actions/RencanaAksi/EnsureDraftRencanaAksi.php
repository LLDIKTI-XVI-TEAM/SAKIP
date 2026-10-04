<?php

namespace App\Actions\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Perencanaan\IndikatorArsipGuard;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EnsureDraftRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly IndikatorArsipGuard $arsipGuard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Membuat header draf secara idempoten untuk satu kombinasi indikator × tahun.
     *
     * Unit disalin dari indikator terkunci, jadwal diambil dari jadwal aktif
     * tahun tersebut, dan penanggung jawab diisi PIC efektif pada hari ini
     * zona Asia/Makassar. Indikator arsip dan ketiadaan jadwal aktif ditolak
     * validasi. Keberadaan header diperiksa di bawah kunci sehingga pemanggilan
     * ulang mengembalikan baris yang sama tanpa duplikat.
     */
    public function handle(User $actor, string $indikatorId, int $tahun): RencanaAksi
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;

        try {
            return DB::transaction(function () use ($actor, $indikatorId, $tahun, &$dasarIzin) {
                $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_CREATE);
                /** @var User|null $pengunci */
                $pengunci = $kunci['aktor'];
                if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
                    $dasarIzin = $kunci['keputusan']->toAuditBasis();
                    throw new AuthorizationException('Akun pengguna tidak aktif.');
                }

                $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($indikatorId);
                $indikator->loadMissing('sasaranStrategis');
                $this->arsipGuard->pastikanDapatDibuatkan($indikator, 'rencana_aksi');

                $unitId = (string) $indikator->unit_id;
                $keputusan = $this->resolver->decide($pengunci, PermissionCodes::RENCANA_AKSI_CREATE, $unitId);
                $dasarIzin = $keputusan;
                if (! $keputusan['allowed']) {
                    throw new AuthorizationException('Izin pembuatan rencana aksi tidak tersedia atau telah dicabut.');
                }

                if (! DB::table('unit')->where('id', $unitId)->where('status', 'aktif')->exists()) {
                    throw ValidationException::withMessages(['indikator_id' => 'Unit pemilik indikator berstatus nonaktif.']);
                }

                $renstraId = $indikator->sasaranStrategis?->renstra_id;
                if (! is_string($renstraId) || $renstraId === '') {
                    throw ValidationException::withMessages(['indikator_id' => 'Sasaran strategis indikator tidak memiliki Renstra induk yang sah.']);
                }

                $jadwal = JadwalTahunan::where('renstra_id', $renstraId)
                    ->where('tahun', $tahun)
                    ->where('status', 'aktif')
                    ->lockForUpdate()
                    ->first();
                if (! $jadwal instanceof JadwalTahunan) {
                    throw ValidationException::withMessages(['tahun' => 'Jadwal tahunan aktif untuk tahun ini belum tersedia.']);
                }

                $hariIni = today(config('app.business_timezone'))->toDateString();
                $pic = PenugasanIndikator::where('indikator_id', $indikator->id)
                    ->whereDate('tanggal_mulai_berlaku', '<=', $hariIni)
                    ->orderByDesc('tanggal_mulai_berlaku')
                    ->orderByDesc('created_at')
                    ->lockForUpdate()
                    ->first();
                if (! $pic instanceof PenugasanIndikator) {
                    throw ValidationException::withMessages(['indikator_id' => 'Penugasan PIC efektif belum tersedia untuk indikator ini.']);
                }

                $existing = RencanaAksi::where('indikator_id', $indikator->id)
                    ->where('tahun', $tahun)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof RencanaAksi) {
                    return $existing;
                }

                try {
                    $created = RencanaAksi::create([
                        'indikator_id' => $indikator->id,
                        'tahun' => $tahun,
                        'unit_id' => $unitId,
                        'jadwal_tahunan_id' => $jadwal->id,
                        'penanggung_jawab_id' => $pic->user_id,
                        'uraian' => null,
                        'status_alur' => RencanaAksi::STATUS_DRAFT,
                        'versi' => 1,
                        'alasan_revisi' => null,
                        'alasan_deviasi_pk' => null,
                        'created_by' => $pengunci->id,
                    ]);
                } catch (QueryException $exception) {
                    if ($exception->getCode() === '23505') {
                        $lomba = RencanaAksi::where('indikator_id', $indikator->id)->where('tahun', $tahun)->first();
                        if ($lomba instanceof RencanaAksi) {
                            return $lomba;
                        }
                    }

                    throw $exception;
                }

                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'rencana_aksi.buat',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) $created->id,
                    nilaiLama: null,
                    nilaiBaru: $this->auditState($created),
                    alasan: "Membuat draf rencana aksi indikator '{$indikator->kode}' tahun {$tahun}.",
                    dasarIzin: $dasarIzin,
                );

                return $created;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'rencana_aksi.buat_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) Str::uuid(),
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Pembuatan draf rencana aksi ditolak.'),
                    dasarIzin: is_array($dasarIzin) ? $dasarIzin : null,
                );
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(RencanaAksi $rencanaAksi): array
    {
        return $rencanaAksi->only(['indikator_id', 'tahun', 'unit_id', 'jadwal_tahunan_id', 'penanggung_jawab_id', 'status_alur', 'versi']);
    }
}
