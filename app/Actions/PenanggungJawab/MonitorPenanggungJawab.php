<?php

namespace App\Actions\PenanggungJawab;

use App\Models\PenugasanIndikator;
use App\Services\PenanggungJawab\WorkReadiness;

class MonitorPenanggungJawab
{
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
        $after = $filters['after'] ?? null;
        $items = [];
        // ponytail: scan O(kandidat) saat mayoritas PJ lengkap; gunakan batch lintas user/unit di resolver
        // kanonis bila terukur lambat. Chunk dan memo dibatasi agar payload/memori bounded.
        do {
            $rows = (clone $query)->when($after, fn ($q) => $q->where('indikator_id', '>', $after))
                ->orderBy('indikator_id')->limit(100)->get();
            $memo = [];
            foreach ($rows as $row) {
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
                if (count($items) > 20) {
                    break 2;
                }
            }
        } while ($rows->count() === 100);
        $hasMore = count($items) > 20;
        $items = array_slice($items, 0, 20);
        $next = $hasMore ? url('/penanggung-jawab').'?'.http_build_query(array_filter([
            'tanggal_acuan' => $date, 'q' => $search, 'unit_id' => $unitId, 'after' => $items[19]['indicator']['id'],
        ], fn ($value) => $value !== null && $value !== '')) : null;

        return [
            'assignments' => ['data' => $items, 'next_page_url' => $next],
            'filters' => ['q' => $search, 'tanggal_acuan' => $date, 'unit_id' => $unitId],
        ];
    }
}
