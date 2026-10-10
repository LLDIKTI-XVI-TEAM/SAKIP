<?php

namespace App\Actions\PenanggungJawab;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Services\PenanggungJawab\WorkReadiness;
use Illuminate\Support\Facades\DB;

class ReadPenanggungJawab
{
    public function __construct(private readonly WorkReadiness $readiness) {}

    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function handle(IndikatorKinerja $indicator, array $filters): array
    {
        return DB::transaction(function () use ($indicator, $filters) {
            // Mutex bersama menjaga histori dan token pada satu state append.
            $indicator = IndikatorKinerja::whereKey($indicator->id)->sharedLock()->firstOrFail();
            $indicator->load(['unit:id,nama,status', 'sasaranStrategis.renstra:id,kode,nama,status']);
            $date = $filters['tanggal_acuan'] ?? today(config('app.business_timezone'))->toDateString();
            $effective = PenugasanIndikator::effectiveOn($date)->where('indikator_id', $indicator->id)->with(['pic:id,nama,status', 'establishedBy:id,nama'])->first();
            // Baris yang digantikan pada tanggal sama tidak akan pernah efektif, jadi bukan "Terjadwal".
            // Dihitung per baris di SQL agar tetap benar ketika pagination memotong satu tanggal.
            $history = $indicator->penugasanIndikators()->with(['pic:id,nama,status', 'establishedBy:id,nama'])
                ->select('penanggung_jawab.*')->selectRaw('exists (select 1 from penanggung_jawab later where later.indikator_id = penanggung_jawab.indikator_id
                    and later.tanggal_mulai_berlaku = penanggung_jawab.tanggal_mulai_berlaku and later.urutan > penanggung_jawab.urutan) as digantikan')
                ->orderByDesc('tanggal_mulai_berlaku')->orderByDesc('urutan')->simplePaginate(15)->appends($filters)
                ->through(fn (PenugasanIndikator $row) => $this->present($row) + [
                    'state' => $row->id === $effective?->id ? 'Efektif' : (! $row->getAttribute('digantikan') && $row->tanggal_mulai_berlaku->toDateString() > $date ? 'Terjadwal' : 'Riwayat'),
                ]);
            $blocked = $indicator->assignmentBlockReason();

            return [
                'indicator' => ['id' => $indicator->id, 'kode' => $indicator->kode, 'nama' => $indicator->nama, 'status' => $indicator->status],
                'unit' => $indicator->unit?->only(['id', 'nama', 'status']),
                'renstra' => $indicator->sasaranStrategis?->renstra?->only(['id', 'kode', 'nama', 'status']),
                'effective' => $effective ? $this->present($effective) : null,
                'readiness' => $effective?->pic ? $this->readiness->forUser($effective->pic, $indicator->unit_id) : null,
                'history' => $history,
                'has_history' => $indicator->penugasanIndikators()->exists(),
                'expected_state' => $indicator->assignmentStateToken(),
                'tanggal_acuan' => $date,
                'today' => today(config('app.business_timezone'))->toDateString(),
                'blocked_reason' => $blocked,
                'can' => ['assign' => $blocked === null],
            ];
        });
    }

    /** @return array<string,mixed> */
    private function present(PenugasanIndikator $row): array
    {
        return [
            'id' => $row->id, 'tanggal_mulai_berlaku' => $row->tanggal_mulai_berlaku->toDateString(),
            'pic' => $row->pic?->only(['id', 'nama', 'status']), 'alasan' => $row->alasan,
            'ditetapkan_oleh' => $row->establishedBy?->only(['id', 'nama']),
            'created_at' => $row->created_at?->toISOString(),
        ];

    }
}
