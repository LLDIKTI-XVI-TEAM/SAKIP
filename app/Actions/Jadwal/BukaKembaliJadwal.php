<?php

namespace App\Actions\Jadwal;

use App\Http\Requests\Jadwal\BukaKembaliJadwalRequest;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BukaKembaliJadwal
{
    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /** Buka sesi atomik; aktor/ACL lalu jadwal dikunci, snapshot dan tanggal aktivasi pertama tetap utuh. @param array<string, mixed> $data */
    public function handle(User $actor, string $jadwalId, array $data): JadwalTahunan
    {
        /** @var PermissionDecision|null $decision */
        $decision = null;
        $jadwalId = strtolower($jadwalId);
        try {
            return DB::transaction(function () use ($actor, $jadwalId, $data, &$decision): JadwalTahunan {
                $decision = $this->lockedActor->handle($actor, PermissionCodes::JADWAL_BUKA_KEMBALI)['keputusan'];
                if (! $decision->allowed) {
                    throw new AuthorizationException('Anda tidak memiliki izin membuka kembali jadwal.');
                }
                $request = new BukaKembaliJadwalRequest;
                if (array_diff(array_keys($data), array_keys($request->rules())) !== []) {
                    throw ValidationException::withMessages(['jadwal' => 'Permintaan memuat field yang tidak didukung.']);
                }
                $data = Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->validate();
                $jadwal = JadwalTahunan::whereKey($jadwalId)->lockForUpdate()->firstOrFail();
                if ($jadwal->status !== 'ditutup') {
                    throw ValidationException::withMessages(['jadwal' => 'Hanya jadwal berstatus ditutup yang dapat dibuka kembali.']);
                }
                if ($jadwal->revisi !== (int) $data['expected_revisi']) {
                    throw ValidationException::withMessages(['jadwal' => 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum membuka kembali.']);
                }
                $scope = $data['lingkup_koreksi'];
                foreach (['periode_ids', 'indikator_ids'] as $key) {
                    $scope[$key] = array_map(strtolower(...), $scope[$key]);
                }
                if (PeriodeJadwal::where('jadwal_id', $jadwal->id)->whereIn('periode_id', $scope['periode_ids'])->count() !== count($scope['periode_ids'])) {
                    throw ValidationException::withMessages(['lingkup_koreksi.periode_ids' => 'Seluruh periode wajib merupakan anggota jadwal ini.']);
                }
                if (JadwalSnapshot::where('jadwal_id', $jadwal->id)->whereIn('indikator_id', $scope['indikator_ids'])->distinct()->count('indikator_id') !== count($scope['indikator_ids'])) {
                    throw ValidationException::withMessages(['lingkup_koreksi.indikator_ids' => 'Seluruh indikator wajib memiliki snapshot pada jadwal ini.']);
                }
                $fields = ['status', 'revisi', 'closed_at', 'koreksi_mulai', 'koreksi_sampai', 'lingkup_koreksi'];
                $before = $jadwal->only($fields);
                $jadwal->forceFill(['status' => 'aktif', 'closed_at' => null, 'revisi' => $jadwal->revisi + 1,
                    'koreksi_mulai' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['koreksi_mulai'], config('app.business_timezone'))->utc()->format('Y-m-d H:i:s'),
                    'koreksi_sampai' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['koreksi_sampai'], config('app.business_timezone'))->utc()->format('Y-m-d H:i:s'),
                    'lingkup_koreksi' => $scope])->save();
                $this->audit->catat(actor: $actor, tindakan: 'jadwal.buka_kembali', objekTipe: 'jadwal', objekId: $jadwal->id,
                    nilaiLama: $before, nilaiBaru: $jadwal->only($fields), alasan: AuditReason::sanitize($data['alasan']), dasarIzin: $decision->toAuditBasis());

                return $jadwal;
            });
        } catch (AuthorizationException|ValidationException $exception) {
            $this->audit->catat(actor: $actor, tindakan: 'jadwal.buka_kembali_ditolak', objekTipe: 'jadwal', objekId: $jadwalId,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_sesi_koreksi'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }
}
