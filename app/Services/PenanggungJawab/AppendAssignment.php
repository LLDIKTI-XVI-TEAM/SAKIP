<?php

namespace App\Services\PenanggungJawab;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\AuditReason;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class AppendAssignment
{
    public function __construct(
        private readonly ResolveLockedActor $lockedActor,
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly WorkReadiness $readiness,
    ) {}

    /**
     * Transaksi dimiliki Action. Semua penetapan berbagi mutex indikator,
     * termasuk penetapan pertama ketika belum ada row histori untuk dikunci.
     *
     * @param  array{user_id:string,tanggal_mulai_berlaku:string,expected_state:string,alasan?:?string}  $data
     * @return array<string,mixed>
     */
    public function append(User $actor, IndikatorKinerja $indicator, array $data, bool $initial): array
    {
        $users = User::whereIn('id', [$actor->id, $data['user_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $actor = $users->get($actor->id) ?? $actor;
        $lock = $this->lockedActor->handle($actor, 'penanggung_jawab:update');
        $decision = $lock['keputusan'];
        $basis = $decision->toAuditBasis();
        if (! $decision->allowed) {
            return $this->rejection('authorization', 'Anda tidak memiliki izin efektif untuk menetapkan penanggung jawab.', $basis);
        }

        $current = IndikatorKinerja::whereKey($indicator->id)->lockForUpdate()->firstOrFail();
        $sasaran = SasaranStrategis::whereKey($current->sasaran_strategis_id)->sharedLock()->first();
        $renstra = $sasaran ? Renstra::whereKey($sasaran->renstra_id)->sharedLock()->first() : null;
        $unit = Unit::whereKey($current->unit_id)->sharedLock()->first();
        $current->setRelation('unit', $unit)->setRelation('sasaranStrategis', $sasaran);
        $sasaran?->setRelation('renstra', $renstra);
        if ($blocked = $current->assignmentBlockReason()) {
            return $this->rejection('indikator', $blocked, $basis);
        }
        $target = $users->get(strtolower($data['user_id']));
        if (! $target || $target->status !== 'aktif') {
            return $this->rejection('user_id', 'Pilih pengguna yang masih aktif.', $basis);
        }
        if (! hash_equals($current->assignmentStateToken(), $data['expected_state'])) {
            return $this->rejection('expected_state', 'Penugasan atau konteks indikator telah berubah. Muat data terbaru dan tinjau ulang formulir sebelum menyimpan.', $basis);
        }
        $hasHistory = $current->penugasanIndikators()->exists();
        if ($initial === $hasHistory) {
            return $this->rejection('expected_state', 'Jenis penetapan tidak sesuai histori terkini. Muat data terbaru sebelum menyimpan.', $basis);
        }
        $date = $data['tanggal_mulai_berlaku'];
        if ($current->penugasanIndikators()->where('tanggal_mulai_berlaku', $date)->exists()) {
            return $this->rejection('tanggal_mulai_berlaku', 'Sudah ada penugasan pada tanggal tersebut untuk indikator ini.', $basis);
        }
        $old = PenugasanIndikator::effectiveOn($date)->where('indikator_id', $current->id)->first();
        if ($old?->user_id === $target->id) {
            return $this->rejection('user_id', 'Pengguna ini sudah menjadi PJ efektif pada tanggal yang dipilih. Penetapan tidak mengubah tanggung jawab.', $basis);
        }
        $reason = trim(AuditReason::sanitize($data['alasan'] ?? null));
        if (($hasHistory && $reason === '') || mb_strlen($reason) > 2000) {
            return $this->rejection('alasan', 'Pergantian memerlukan alasan yang dapat dibaca, maksimal 2.000 karakter.', $basis);
        }
        // Diagnosis izin tidak memberikan hak baru dan bukan prasyarat assignment.
        $readiness = $this->readiness->forUser($target, $current->unit_id);
        $assignment = PenugasanIndikator::create([
            'indikator_id' => $current->id, 'user_id' => $target->id,
            'tanggal_mulai_berlaku' => $date, 'ditetapkan_oleh' => $actor->id,
            'alasan' => $reason === '' ? null : $reason, 'created_at' => now(),
        ]);
        $this->audit->catat(
            actor: $actor,
            tindakan: $initial ? 'penanggung_jawab.tetapkan' : 'penanggung_jawab.ganti',
            objekTipe: 'indikator', objekId: $current->id,
            nilaiLama: ['indikator_id' => $current->id, 'user_id' => $old?->user_id, 'assignment_id' => $old?->id, 'tanggal_acuan' => $date],
            nilaiBaru: ['indikator_id' => $current->id, 'assignment_id' => $assignment->id, 'user_id' => $target->id, 'tanggal_mulai_berlaku' => $date, 'alasan' => $assignment->alasan],
            alasan: $reason === '' ? 'Penetapan penanggung jawab pertama dengan tanggal mulai berlaku '.$date.'.' : $reason,
            dasarIzin: $basis,
        );

        return [
            'assignment' => $assignment,
            'warning' => $readiness['complete'] ? null : 'Hak kerja PJ belum lengkap: '.implode(', ', $readiness['missing']).'. Penugasan tidak memberikan permission; kelola izin melalui pengelolaan akses.',
        ];
    }

    /** @return array<string,mixed> */
    public function uniqueConflict(QueryException $error, User $actor): array
    {
        if (($error->errorInfo[0] ?? null) !== '23505' || ! str_contains($error->getMessage(), 'penanggung_jawab_indikator_tanggal_unique')) {
            throw $error;
        }

        return $this->rejection('tanggal_mulai_berlaku', 'Sudah ada penugasan pada tanggal tersebut untuk indikator ini.', $this->resolver->resolve($actor->fresh() ?? $actor, 'penanggung_jawab:update')->toAuditBasis());
    }

    /** Penolakan dicatat setelah transaksi berakhir, supaya tidak hilang akibat rollback.
     * @param  array<string,mixed>  $result
     * @return array{assignment:PenugasanIndikator,warning:?string}
     */
    public function finish(User $actor, IndikatorKinerja $indicator, array $result): array
    {
        if (isset($result['field'])) {
            $this->audit->catat(
                actor: $actor, tindakan: 'penanggung_jawab.ditolak', objekTipe: 'indikator', objekId: $indicator->id,
                nilaiBaru: ['hasil' => 'ditolak', 'field' => $result['field']],
                alasan: $result['message'], dasarIzin: $result['basis'],
            );
            if ($result['field'] === 'authorization') {
                throw new AuthorizationException($result['message']);
            }
            throw ValidationException::withMessages([$result['field'] => $result['message']]);
        }

        return $result;
    }

    /** @param array<string,mixed> $basis
     * @return array<string,mixed>
     */
    private function rejection(string $field, string $message, array $basis): array
    {
        return ['field' => $field, 'message' => $message, 'basis' => $basis];
    }
}
