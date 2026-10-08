<?php

namespace App\Actions\PenanggungJawab;

use App\Models\PenugasanIndikator;
use App\Services\PenanggungJawab\WorkReadiness;

class MonitorPenanggungJawab
{
    private const CANDIDATE_BUDGET = 100;

    private const PAGE_SIZE = 20;

    public function __construct(private readonly WorkReadiness $readiness) {}

    /** @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function handle(array $filters): array
    {
        $date = $filters['tanggal_acuan'] ?? today(config('app.business_timezone'))->toDateString();
        $search = trim($filters['q'] ?? '');
        $unitId = $filters['unit_id'] ?? null;
        $query = PenugasanIndikator::effectiveOn($date)->whereHas('pic', fn ($q) => $q->where('status', 'aktif'))
            ->whereHas('indikatorKinerja', fn ($q) => $q->when($unitId, fn ($q) => $q->where('unit_id', $unitId)))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->whereHas('pic', fn ($p) => $p->where('nama', 'ilike', '%'.$search.'%'))
                        ->orWhereHas('indikatorKinerja', function ($i) use ($search): void {
                            $i->where('kode', 'ilike', '%'.$search.'%')->orWhere('nama', 'ilike', '%'.$search.'%')
                                ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', '%'.$search.'%'));
                        });
                });
            })->with(['pic:id,nama,status', 'indikatorKinerja.unit:id,nama,status', 'indikatorKinerja.sasaranStrategis.renstra:id,status']);
        // Cursor hanya berlaku untuk filter yang membentuknya; pergantian filter memulai scan baru.
        $scope = hash('sha256', json_encode([$date, $search, $unitId ? strtolower($unitId) : null], JSON_THROW_ON_ERROR));
        $after = ($filters['after_scope'] ?? null) === $scope ? ($filters['after'] ?? null) : null;
        $items = [];
        // Satu kandidat ekstra hanya memastikan continuation; ACL dievaluasi maksimal sesuai budget.
        $rows = $query->when($after, fn ($q) => $q->where('indikator_id', '>', $after))
            ->orderBy('indikator_id')->limit(self::CANDIDATE_BUDGET + 1)->get();
        $memo = [];
        $processed = 0;
        foreach ($rows->take(self::CANDIDATE_BUDGET) as $row) {
            $processed++;
            $after = $row->indikator_id;
            $indicator = $row->indikatorKinerja;
            $key = $row->user_id.':'.$indicator->unit_id;
            $ready = $memo[$key] ??= $this->readiness->forUser($row->pic, $indicator->unit_id);
            if ($ready['complete']) {
                continue;
            }
            $items[] = [
                'indicator' => $indicator->only(['id', 'kode', 'nama', 'status']),
                'unit' => $indicator->unit?->only(['id', 'nama', 'status']),
                'pic' => $row->pic->only(['id', 'nama', 'status']),
                'tanggal_mulai_berlaku' => $row->tanggal_mulai_berlaku->toDateString(),
                'blocked_reason' => $indicator->assignmentBlockReason(),
                'readiness' => $ready,
            ];
            if (count($items) === self::PAGE_SIZE) {
                break;
            }
        }
        // Pakai kandidat terakhir yang diproses, termasuk halaman tanpa hasil matching.
        $hasMore = $rows->count() > $processed;
        $next = $hasMore ? url('/penanggung-jawab').'?'.http_build_query(array_filter([
            'tanggal_acuan' => $date, 'q' => $search, 'unit_id' => $unitId, 'after' => $after, 'after_scope' => $scope,
        ], fn ($value) => $value !== null && $value !== '')) : null;

        return [
            'assignments' => ['data' => $items, 'next_page_url' => $next],
            'filters' => ['q' => $search, 'tanggal_acuan' => $date, 'unit_id' => $unitId],
        ];
    }
}
