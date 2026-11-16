<?php

namespace App\Actions\PenanggungJawab;

use App\Models\PenugasanIndikator;
use App\Services\PenanggungJawab\WorkReadiness;
use Illuminate\Pagination\LengthAwarePaginator;

class MonitorPenanggungJawab
{
    private const PAGE_SIZE = 20;

    public function __construct(private readonly WorkReadiness $readiness) {}

    /**
     * Seluruh PJ efektif pada tanggal acuan, termasuk akun nonaktif (Q68); daftar "PJ tanpa hak isi" (Q32) menjadi filter izin `kurang`.
     * Pilihan unit/indikator diambil dari penugasan efektif tanggal itu; nilai di luar pilihan jatuh ke semua.
     *
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function handle(array $filters): array
    {
        $date = $filters['tanggal_acuan'] ?? today(config('app.business_timezone'))->toDateString();
        $search = trim($filters['q'] ?? '');
        $izin = $filters['izin'] ?? null;
        $rows = PenugasanIndikator::effectiveOn($date)
            ->with(['pic:id,nama,status', 'indikatorKinerja.unit:id,nama,status', 'indikatorKinerja.sasaranStrategis.renstra:id,status'])
            ->get()->sortBy(fn (PenugasanIndikator $row) => $row->indikatorKinerja->kode, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $pilihan = [
            'unit' => $rows->map(fn (PenugasanIndikator $row) => $row->indikatorKinerja->unit?->only(['id', 'nama']))->filter()
                ->unique('id')->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'indikator' => $rows->map(fn (PenugasanIndikator $row) => $row->indikatorKinerja->only(['id', 'kode', 'nama', 'unit_id'])),
        ];
        $unitId = strtolower((string) ($filters['unit_id'] ?? ''));
        $unitId = $pilihan['unit']->contains('id', $unitId) ? $unitId : null;
        $indikatorId = strtolower((string) ($filters['indikator_id'] ?? ''));
        $indikatorId = $pilihan['indikator']->contains('id', $indikatorId) ? $indikatorId : null;
        $cocok = fn (?string $teks) => $teks !== null && mb_stripos($teks, $search) !== false;
        $rows = $rows->filter(fn (PenugasanIndikator $row) => ($unitId === null || $row->indikatorKinerja->unit_id === $unitId)
            && ($indikatorId === null || $row->indikator_id === $indikatorId)
            && ($search === '' || $cocok($row->pic->nama) || $cocok($row->indikatorKinerja->kode)
                || $cocok($row->indikatorKinerja->nama) || $cocok($row->indikatorKinerja->unit?->nama)));
        $memo = [];
        // Izin dievaluasi sekali per pasangan PJ×unit dan hanya untuk baris yang dibutuhkan filter izin atau halaman tampil.
        $readiness = function (PenugasanIndikator $row) use (&$memo): array {
            $unit = $row->indikatorKinerja->unit_id;

            return $memo[$row->user_id.':'.$unit] ??= $this->readiness->forUser($row->pic, $unit);
        };
        if ($izin !== null) {
            $rows = $rows->filter(fn (PenugasanIndikator $row) => $readiness($row)['complete'] === ($izin === 'lengkap'));
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $normalized = ['q' => $search, 'tanggal_acuan' => $date, 'unit_id' => $unitId, 'indikator_id' => $indikatorId, 'izin' => $izin];
        $items = $rows->forPage($page, self::PAGE_SIZE)->map(fn (PenugasanIndikator $row) => [
            'indicator' => $row->indikatorKinerja->only(['id', 'kode', 'nama', 'status']),
            'unit' => $row->indikatorKinerja->unit?->only(['id', 'nama', 'status']),
            'pic' => $row->pic->only(['id', 'nama', 'status']),
            'tanggal_mulai_berlaku' => $row->tanggal_mulai_berlaku->toDateString(),
            'blocked_reason' => $row->indikatorKinerja->assignmentBlockReason(),
            'readiness' => $readiness($row),
        ])->values();

        return [
            'assignments' => new LengthAwarePaginator($items, $rows->count(), self::PAGE_SIZE, $page, [
                'path' => url('/penanggung-jawab'),
                'query' => array_filter($normalized, fn ($value) => $value !== null && $value !== ''),
            ]),
            'filters' => $normalized,
            'pilihan' => $pilihan,
        ];
    }
}
