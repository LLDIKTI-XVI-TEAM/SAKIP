<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PindahUnitIndikator
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ResolveLockedActor $lockedActor,
    ) {}

    /**
     * Memindahkan unit penanggung jawab satu Indikator beserta auditnya.
     *
     * Seperti Store/Update Indikator: izin dievaluasi ulang di dalam
     * transaksi terkunci memakai state terkini, lalu baris indikator
     * dikunci eksklusif dan kedua unit (lama dan tujuan) dikunci
     * deterministik terurut agar tidak deadlock silang. Jalur ini tidak
     * menyentuh sasaran strategis sehingga tidak ada penjagaan lintas
     * Renstra. Unit tujuan wajib aktif; perpindahan ke unit yang sama
     * ditolak sebagai validasi. Audit yang ditulis hanya satu baris
     * `indikator.pindah_unit` berisi nilai lama/baru — tidak ada audit
     * `indikator.ubah` untuk delta unit.
     *
     * @return array{indikator: IndikatorKinerja, renstraId: ?string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, array $validated): array
    {
        $result = DB::transaction(function () use ($indikator, $validated, $actor) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_UPDATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pemindahan unit penanggung jawab indikator ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pemindahan unit penanggung jawab indikator ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            // Urutan kunci global: jalur ini tidak membaca/menulis regulasi_id
            // sehingga tidak mengunci baris Regulasi sama sekali (kasus null =
            // lewati, permanen) — tidak ada jalur Indikator→Regulasi di sini.
            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::query()
                ->whereKey($indikator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // 3. Kunci unit lama dan unit tujuan secara deterministik terurut
            $currentUnitId = $lockedIndikator->unit_id;
            $targetUnitId = $validated['unit_id'];
            $unitIds = array_values(array_unique([$currentUnitId, $targetUnitId]));
            sort($unitIds);

            $lockedUnits = Unit::whereIn('id', $unitIds)
                ->orderBy('id')
                ->sharedLock()
                ->get()
                ->keyBy('id');

            /** @var Unit|null $currentUnit */
            $currentUnit = $lockedUnits->get($currentUnitId);
            /** @var Unit|null $targetUnit */
            $targetUnit = $lockedUnits->get($targetUnitId);

            if (! $currentUnit) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab saat ini tidak valid.',
                ]);
            }

            if (! $targetUnit || $targetUnit->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tujuan tidak valid atau sudah nonaktif.',
                ]);
            }

            if ($currentUnitId === $targetUnitId) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Indikator sudah berada pada unit penanggung jawab tersebut.',
                ]);
            }

            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            $lockedIndikator->update(['unit_id' => $targetUnitId]);

            $nilaiBaru = $lockedIndikator->withoutRelations()->toArray();

            // 4. Audit tunggal perpindahan unit; delta unit tidak ditulis sebagai indikator.ubah
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.pindah_unit',
                objekTipe: 'indikator',
                objekId: (string) $lockedIndikator->id,
                nilaiLama: [
                    'unit_id' => $nilaiLama['unit_id'],
                    'unit_nama' => $currentUnit->nama,
                ],
                nilaiBaru: [
                    'unit_id' => $nilaiBaru['unit_id'],
                    'unit_nama' => $targetUnit->nama,
                ],
                alasan: trim($validated['alasan']),
                dasarIzin: $dasarIzin,
            );

            return [
                'status' => 'moved',
                'indikator' => $lockedIndikator,
                'renstraId' => $this->renstraIdUntuk($lockedIndikator->sasaran_strategis_id),
            ];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.pindah_unit_ditolak',
                objekTipe: 'indikator',
                objekId: (string) $indikator->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pemindahan unit penanggung jawab indikator ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang memindahkan unit penanggung jawab indikator.');
        }

        return ['indikator' => $result['indikator'], 'renstraId' => $result['renstraId']];
    }

    /**
     * Membaca renstra induk sasaran memakai kunci bersama di dalam transaksi
     * pemanggil agar controller tidak perlu query parent sendiri.
     */
    private function renstraIdUntuk(string $sasaranId): ?string
    {
        $renstraId = SasaranStrategis::whereKey($sasaranId)->sharedLock()->value('renstra_id');

        return is_string($renstraId) ? $renstraId : null;
    }
}
