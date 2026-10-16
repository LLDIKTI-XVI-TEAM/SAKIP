<?php

namespace App\Actions\Rekomendasi;

use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\RekomendasiPimpinan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IndexRekomendasi
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Satu baris per indikator snapshot yang diukur pada jadwal × periode terpilih (bawaan: periode berjalan jadwal
     * aktif terbaru), beserta rekomendasi berlaku, draf terbuka, dan riwayat persetujuan. Draf hanya sampai ke pemegang
     * `rekomendasi:tetapkan`; PIC membaca rekomendasi lewat pengukuran periode berikutnya.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $query): array
    {
        if (! $this->resolver->allows($actor, PermissionCodes::REKOMENDASI_TETAPKAN)) {
            throw new AuthorizationException('Anda tidak memiliki izin mengelola rekomendasi pimpinan.');
        }
        $query = Validator::make($query, ['jadwal_id' => ['nullable', 'uuid', 'required_with:periode_id'], 'periode_id' => ['nullable', 'uuid', 'required_with:jadwal_id']])->validate();
        $schedules = JadwalTahunan::with('periode.periode')->whereIn('status', ['aktif', 'ditutup'])->orderByDesc('tahun')->orderBy('id')->get();
        $selected = $this->pilih($schedules, $query);

        return [
            'pilihan_jadwal' => $schedules->map(fn (JadwalTahunan $jadwal) => ['id' => $jadwal->id, 'tahun' => $jadwal->tahun, 'status' => $jadwal->status,
                'periode' => $jadwal->periode->sortBy('periode.urutan')->map(fn (PeriodeJadwal $p) => ['id' => $p->periode_id, 'nama_periode' => $p->periode->nama])->values()->all()])->all(),
            'filters' => ['jadwal_id' => $selected?->jadwal_id, 'periode_id' => $selected?->periode_id],
            'persetujuan_cadangan' => $actor->roles()->value('kode') !== RekomendasiPimpinan::PERAN_PENYETUJU_UTAMA,
            'rekomendasi' => $selected ? $this->baris($selected, $schedules->firstWhere('id', $selected->jadwal_id)->tahun) : [],
        ];
    }

    /** @param  Collection<int, JadwalTahunan>  $schedules */
    private function pilih($schedules, array $query): ?PeriodeJadwal
    {
        if (isset($query['jadwal_id'])) {
            $jadwal = $schedules->firstWhere('id', strtolower($query['jadwal_id'])) ?? throw ValidationException::withMessages(['jadwal_id' => 'Pilih jadwal aktif atau jadwal yang sudah ditutup.']);

            return $jadwal->periode->firstWhere('periode_id', strtolower($query['periode_id'])) ?? throw ValidationException::withMessages(['periode_id' => 'Periode bukan anggota jadwal yang dipilih.']);
        }
        $jadwal = $schedules->firstWhere('status', 'aktif') ?? $schedules->first();
        $today = today(config('app.business_timezone'))->toDateString();

        return $jadwal?->periode->filter(fn (PeriodeJadwal $p) => $p->pengisian_mulai->toDateString() <= $today)->sortByDesc('pengisian_mulai')->first()
            ?? $jadwal?->periode->sortBy('periode.urutan')->first();
    }

    /** @return list<array<string, mixed>> */
    private function baris(PeriodeJadwal $selected, int $tahun): array
    {
        // Versi snapshot terbesar yang sudah berlaku pada periode terpilih, sama dengan kombinasi sah di TetapkanRekomendasi.
        $indikator = DB::table('jadwal_snapshot as s')->join('periode as pm', 'pm.id', '=', 's.periode_mulai_id')
            ->join('indikator_kinerjas as i', 'i.id', '=', 's.indikator_id')->join('unit as u', 'u.id', '=', 's.unit_id')
            ->where('s.jadwal_id', $selected->jadwal_id)->where('pm.urutan', '<=', $selected->periode->urutan)
            ->selectRaw('DISTINCT ON (s.indikator_id) s.indikator_id, i.kode, s.nama, u.nama as unit')
            ->orderBy('s.indikator_id')->orderByDesc('s.nomor_versi')->get()->sortBy('kode')->values();
        $rekomendasi = RekomendasiPimpinan::with(['penyusun:id,nama', 'penyetuju:id,nama'])->where('tahun', $tahun)->where('periode_id', $selected->periode_id)
            ->whereIn('indikator_id', $indikator->pluck('indikator_id'))->orderByDesc('disetujui_pada')->orderByDesc('id')->get()->groupBy('indikator_id');

        return $indikator->map(function (object $row) use ($rekomendasi, $tahun, $selected): array {
            $rows = $rekomendasi->get($row->indikator_id, collect());
            $disetujui = $rows->where('status', RekomendasiPimpinan::STATUS_DISETUJUI)->values();
            $draf = $rows->firstWhere('status', RekomendasiPimpinan::STATUS_DRAF);

            return [
                'indikator' => ['id' => $row->indikator_id, 'kode' => $row->kode, 'nama' => $row->nama, 'unit' => $row->unit],
                'tahun' => $tahun, 'periode_id' => $selected->periode_id,
                'berlaku' => $disetujui->isNotEmpty() ? $this->persetujuan($disetujui->first()) : null,
                'draf' => $draf ? [...$draf->only(['id', 'isi', 'rujukan_sumber', 'versi']), 'penyusun' => $draf->penyusun->nama] : null,
                'riwayat' => $disetujui->skip(1)->map(fn (RekomendasiPimpinan $r) => $this->persetujuan($r))->values()->all(),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function persetujuan(RekomendasiPimpinan $row): array
    {
        return [...$row->only(['id', 'isi', 'rujukan_sumber', 'disetujui_oleh_role']), 'disetujui_oleh' => $row->penyetuju->nama,
            'disetujui_pada' => $row->disetujui_pada->toIso8601String(), 'persetujuan_cadangan' => $row->isCadangan()];
    }
}
