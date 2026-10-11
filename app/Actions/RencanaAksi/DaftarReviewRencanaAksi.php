<?php

namespace App\Actions\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DaftarReviewRencanaAksi
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $query): array
    {
        Gate::forUser($actor)->authorize('viewAny', RencanaAksi::class);
        $query = Validator::make($query, ['page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['diajukan', 'diverifikasi', 'dikembalikan', 'disahkan', 'semua'])]])->validate();
        $status = $query['status'] ?? 'diajukan';
        $terbaru = RencanaAksiVersi::query()->selectRaw('DISTINCT ON (rencana_aksi_id) rencana_aksi_id, nomor, snapshot')
            ->orderBy('rencana_aksi_id')->orderByDesc('nomor');
        $halaman = RencanaAksi::query()->select('rencana_aksi.*', 'v.nomor as nomor_pengajuan', 'v.snapshot as snapshot_pengajuan')
            ->joinSub($terbaru, 'v', 'v.rencana_aksi_id', '=', 'rencana_aksi.id')
            ->whereNotIn('rencana_aksi.unit_id', $this->resolver->unitDitolak($actor, PermissionCodes::RENCANA_AKSI_READ))
            ->when($status !== 'semua', fn ($q) => $q->where('rencana_aksi.status_alur', $status))
            ->orderByDesc('rencana_aksi.updated_at')->orderBy('rencana_aksi.id')->paginate(20, page: $query['page'] ?? 1)->withQueryString();

        return ['daftar' => $halaman->getCollection()->map(function (RencanaAksi $header): array {
            $beku = json_decode((string) $header->getAttribute('snapshot_pengajuan'), true) ?? [];

            return ['id' => $header->id, 'tahun' => $header->tahun, 'status_alur' => $header->status_alur,
                'nomor_pengajuan' => (int) $header->getAttribute('nomor_pengajuan'), 'indikator' => ['kode' => $beku['indikator']['kode'] ?? '—',
                    'nama' => $beku['indikator']['nama'] ?? 'Konteks versi belum lengkap'], 'unit_nama' => $beku['unit_kerja']['nama'] ?? 'Konteks versi belum lengkap'];
        })->all(), 'filters' => ['status' => $status],
            'pagination' => ['current_page' => $halaman->currentPage(), 'last_page' => $halaman->lastPage(), 'total' => $halaman->total(),
                'prev_page_url' => $halaman->previousPageUrl(), 'next_page_url' => $halaman->nextPageUrl()]];
    }
}
