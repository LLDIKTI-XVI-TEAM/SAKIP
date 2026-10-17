<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Services\Audit\AuditLogPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CariAuditLog
{
    /**
     * Penelusuran read-only atas audit append-only. Filter dan paginasi berjalan di database dengan
     * urutan terbaru lebih dulu; baris daftar tidak membawa payload perubahan agar respons tetap ringan.
     *
     * @param  array{dari?: string|null, sampai?: string|null, aktor?: string|null, tindakan?: string|null, objek_tipe?: string|null, objek_id?: string|null}  $input
     * @return array{audit: LengthAwarePaginator<int, mixed>, filters: array{dari: string, sampai: string, aktor: string, tindakan: string, objek_tipe: string, objek_id: string}}
     */
    public function handle(array $input): array
    {
        $filters = [
            'dari' => $input['dari'] ?? '',
            'sampai' => $input['sampai'] ?? '',
            'aktor' => $input['aktor'] ?? '',
            'tindakan' => $input['tindakan'] ?? '',
            'objek_tipe' => $input['objek_tipe'] ?? '',
            'objek_id' => $input['objek_id'] ?? '',
        ];
        $mengandung = fn (string $teks): string => '%'.addcslashes($teks, '\\%_').'%';

        $audit = AuditLog::query()->with('actor:id,nama,email')
            ->when($filters['dari'] !== '', fn (Builder $query) => $query->where('waktu', '>=', $this->awalHari($filters['dari'])))
            ->when($filters['sampai'] !== '', fn (Builder $query) => $query->where('waktu', '<', $this->awalHari($filters['sampai'])->addDay()))
            // Pelaku system/operator tidak punya actor_id; provenance-nya dicari dari kolom referensi.
            ->when($filters['aktor'] !== '', fn (Builder $query) => $query->where(fn (Builder $aktor) => $aktor
                ->whereHas('actor', fn (Builder $actor) => $actor->whereAny(['nama', 'email'], 'ilike', $mengandung($filters['aktor'])))
                ->orWhereAny(['operator_reference', 'runtime_identity'], 'ilike', $mengandung($filters['aktor']))))
            ->when($filters['tindakan'] !== '', fn (Builder $query) => $query->where('tindakan', 'ilike', $mengandung($filters['tindakan'])))
            ->when($filters['objek_tipe'] !== '', fn (Builder $query) => $query->where('objek_tipe', $filters['objek_tipe']))
            ->when($filters['objek_id'] !== '', fn (Builder $query) => $query->where('objek_id', $filters['objek_id']))
            // `id` memecah waktu yang sama agar urutan antarhalaman tetap deterministik.
            ->orderByDesc('waktu')->orderByDesc('id')
            ->paginate(25, ['id', 'actor_id', 'actor_type', 'sumber', 'waktu', 'tindakan', 'objek_tipe', 'objek_id', 'alasan'])
            ->withQueryString()
            ->through(fn (AuditLog $baris) => AuditLogPresenter::ringkasan($baris));

        return ['audit' => $audit, 'filters' => $filters];
    }

    /** Tanggal filter adalah tanggal bisnis; batasnya dikonversi ke zona penyimpanan sebelum dibandingkan. */
    private function awalHari(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal, config('app.business_timezone'))->startOfDay()->setTimezone(config('app.timezone'));
    }
}
