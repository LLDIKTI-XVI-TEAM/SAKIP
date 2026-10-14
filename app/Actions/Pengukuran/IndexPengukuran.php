<?php

namespace App\Actions\Pengukuran;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IndexPengukuran
{
    public function __construct(private PresentPengukuran $present, private PermissionResolver $resolver, private PengukuranKinerjaPolicy $policy) {}

    /**
     * Daftar kewajiban pengukuran: indikator snapshot × periode berjalan setiap jadwal aktif (pengisian_mulai terakhir yang
     * sudah dimulai), atau tepat satu jadwal × periode pilihan jalur Perencanaan sampai tanggal penutupan. Header existing
     * memakai snapshot yang dijepit; tanpa header memakai nomor_versi terbesar yang sudah efektif pada urutan periode, atau
     * versi terendah sebagai identitas "Tidak berlaku". Deny baca disaring per unit baris (unit snapshot tersebut, bukan unit
     * master) sebelum paginate, dan indikator arsip tanpa header dikeluarkan. Baris existing hanya ringkasan; izin create
     * seluruh unit halaman dievaluasi satu batch dan guard pembentukan memakai data pramuat, tanpa query per baris/unit.
     *
     * @param  array<string, mixed>  $query  query string halaman
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $query): array
    {
        Gate::forUser($actor)->authorize('viewAny', PengukuranKinerja::class);
        $canSelect = $this->resolver->allowsRoleNavigation($actor, 'pengukuran:update');
        $filtered = array_key_exists('jadwal_id', $query) || array_key_exists('periode_id', $query);
        if ($filtered && ! $canSelect) {
            throw new AuthorizationException('Pemilihan periode hanya tersedia untuk jalur Perencanaan.');
        }
        $query = Validator::make($query, ['page' => ['nullable', 'integer', 'min:1'],
            ...($filtered ? ['jadwal_id' => ['required', 'uuid'], 'periode_id' => ['required', 'uuid']] : [])])->validate();
        $today = today(config('app.business_timezone'))->toDateString();
        $schedules = JadwalTahunan::where('status', 'aktif')->orderBy('tahun')->orderBy('id')->get();
        $periods = $filtered ? $this->selectedPeriod($schedules, $query, $today)
            : PeriodeJadwal::with('periode')->whereIn('jadwal_id', $schedules->modelKeys())->where('pengisian_mulai', '<=', $today)
                ->orderByDesc('pengisian_mulai')->orderBy('id')->get()->unique('jadwal_id')->keyBy('id');
        $schedules = $schedules->keyBy('id');

        $page = $this->obligations($periods->keys()->all(), $actor, $today)->paginate(20, page: $query['page'] ?? 1)->withQueryString();
        $items = collect($page->items());
        $headers = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->withCount(['buktiDukungs' => fn ($q) => $q->current()])->whereIn('id', $items->pluck('pengukuran_id')->filter())->get()->keyBy('id');
        if ($headers->isNotEmpty()) {
            $this->present->prepareSummary($headers);
        }
        $creatable = $items->whereNull('pengukuran_id')->where('berlaku', true)->values();
        $targets = $this->present->periodTargets($creatable->map(fn ($row) => ['indikator_id' => $row->indikator_id, 'tahun' => (int) $row->tahun,
            'periode_id' => $row->periode_id, 'jadwal_snapshot_id' => $row->jadwal_snapshot_id, 'unit_id' => $row->unit_id])->all());
        $targets = array_combine($creatable->map(fn ($row) => $row->jadwal_id.':'.$row->indikator_id)->all(), $targets);
        $snapshots = $creatable->isEmpty() ? collect() : JadwalSnapshot::with('unit')->whereIn('id', $creatable->pluck('jadwal_snapshot_id'))->get()->keyBy('id');
        $decisions = $this->resolver->decideManyForUnits($actor, ['pengukuran:create'], $creatable->pluck('unit_id')->unique()->values()->all());

        $rows = $items->map(function ($row) use ($actor, $schedules, $periods, $headers, $targets, $snapshots, $decisions): array {
            $base = ['indikator_id' => $row->indikator_id, 'jadwal_id' => $row->jadwal_id, 'jadwal_snapshot_id' => $row->jadwal_snapshot_id, 'tahun' => (int) $row->tahun,
                'periode' => $this->periodDto($periods[$row->periode_jadwal_id], (int) $row->tahun),
                'indikator' => ['kode' => $row->kode, 'nama' => $row->nama, 'satuan' => $row->satuan, 'desimal_tampilan' => (int) $row->desimal_tampilan],
                'unit' => ['id' => $row->unit_id, 'nama' => $row->unit_nama], 'pic' => $row->pic_id ? ['id' => $row->pic_id, 'nama' => $row->pic_nama] : null];
            if ($row->pengukuran_id !== null) {
                $summary = $this->present->handle($headers[$row->pengukuran_id], $actor);
                $context = $summary['penugasan_indikator'];

                // Metadata dari presenter (versi beku setelah diajukan) agar daftar sama dengan detail walau master berubah.
                return [...$base, 'periode' => $summary['periode_jadwal'], 'indikator' => Arr::only($context['indikator_kinerja'], ['kode', 'nama', 'satuan', 'desimal_tampilan']),
                    'unit' => $context['unit_kerja'], 'pic' => $context['pic'], 'jenis' => 'existing', 'can' => ['create' => false],
                    'pengukuran' => Arr::only($summary, ['id', 'status', 'nilai', 'status_perhitungan', 'target', 'bukti_count', 'self_approval', 'reviu_terlambat'])];
            }
            $create = false;
            if ($row->berlaku) {
                $decision = $decisions[$row->unit_id]['pengukuran:create'];
                $snapshot = $snapshots[$row->jadwal_snapshot_id]->setRelation('jadwal', $schedules[$row->jadwal_id]);
                $create = $decision['allowed'] && $this->policy->creationErrors($actor, $snapshot, $periods[$row->periode_jadwal_id], $decision, $row->pic_id) === [];
            }

            return [...$base, 'jenis' => 'absent', 'berlaku' => (bool) $row->berlaku, 'pengukuran' => null,
                'target' => $row->berlaku ? $targets[$row->jadwal_id.':'.$row->indikator_id] : null, 'can' => ['create' => $create]];
        })->all();

        $selected = $filtered ? $schedules->only($periods->first()->jadwal_id) : $schedules;
        $options = $schedules->filter(fn ($schedule) => $today <= $schedule->penutupan->toDateString());

        return [
            'konteks' => $selected->map(fn ($schedule) => ['jadwal_id' => $schedule->id, 'tahun' => $schedule->tahun,
                'periode' => ($period = $periods->firstWhere('jadwal_id', $schedule->id)) ? $this->periodDto($period, $schedule->tahun) : null])->values()->all(),
            'filters' => ['jadwal_id' => $filtered ? $periods->first()->jadwal_id : null, 'periode_id' => $filtered ? $periods->first()->periode_id : null],
            'can' => ['selectPeriod' => $canSelect],
            ...($canSelect ? ['pilihan_jadwal' => $this->scheduleOptions($options)] : []),
            'pengukurans' => $rows,
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ];
    }

    /**
     * Satu baris per jadwal × indikator snapshot pada periode jadwal terpilih. Versi efektif terbesar dipilih lebih dulu;
     * tanpa versi efektif, versi terendah menjadi identitas "Tidak berlaku". Header dicari dengan identitas unik
     * indikator × tahun × periode, lalu baris memakai snapshot yang dijepit header bila ada.
     *
     * @param  list<string>  $periodIds  id jadwal_periode terpilih, paling banyak satu per jadwal
     */
    private function obligations(array $periodIds, User $actor, string $today): Builder
    {
        $effective = 'pm.urutan <= pr.urutan';
        $selected = DB::table('jadwal_snapshot as s')->join('jadwal_periode as jp', 'jp.jadwal_id', '=', 's.jadwal_id')
            ->join('periode as pr', 'pr.id', '=', 'jp.periode_id')->join('periode as pm', 'pm.id', '=', 's.periode_mulai_id')
            ->join('jadwal_tahunan as j', 'j.id', '=', 's.jadwal_id')->whereIn('jp.id', $periodIds)
            ->selectRaw("DISTINCT ON (s.jadwal_id, s.indikator_id) s.jadwal_id, s.indikator_id, s.id as snapshot_id, jp.id as periode_jadwal_id, jp.periode_id, j.tahun, {$effective} as berlaku")
            ->orderBy('s.jadwal_id')->orderBy('s.indikator_id')->orderByRaw("{$effective} DESC")
            ->orderByRaw("CASE WHEN {$effective} THEN -s.nomor_versi ELSE s.nomor_versi END");

        return DB::query()->fromSub($selected, 'k')
            ->join('indikator_kinerjas as i', 'i.id', '=', 'k.indikator_id')
            ->leftJoin('pengukuran_kinerjas as h', fn ($join) => $join->on('h.indikator_id', '=', 'k.indikator_id')->on('h.tahun', '=', 'k.tahun')->on('h.periode_id', '=', 'k.periode_id'))
            ->join('jadwal_snapshot as cs', DB::raw('COALESCE(h.jadwal_snapshot_id, k.snapshot_id)'), '=', 'cs.id')
            ->join('unit as u', 'u.id', '=', 'cs.unit_id')
            ->leftJoinSub(PenugasanIndikator::effectiveOn($today)->select('indikator_id', 'user_id'), 'pic', 'pic.indikator_id', '=', 'k.indikator_id')
            ->leftJoin('users as picu', 'picu.id', '=', 'pic.user_id')
            ->where(fn ($q) => $q->whereNotNull('h.id')->orWhere('i.status', '!=', IndikatorKinerja::STATUS_ARSIP))
            ->whereNotIn('cs.unit_id', $this->resolver->unitDitolak($actor, 'pengukuran:read'))
            ->select(['k.jadwal_id', 'k.indikator_id', 'k.periode_jadwal_id', 'k.periode_id', 'k.tahun', 'k.berlaku', 'h.id as pengukuran_id', 'cs.id as jadwal_snapshot_id',
                'cs.nama', 'cs.satuan', 'cs.desimal_tampilan', 'i.kode', 'u.id as unit_id', 'u.nama as unit_nama', 'pic.user_id as pic_id', 'picu.nama as pic_nama'])
            ->orderBy('k.tahun')->orderBy('k.jadwal_id')->orderBy('i.kode')->orderBy('i.id');
    }

    /**
     * Filter jalur Perencanaan: jadwal aktif yang belum melewati penutupan dan periode anggotanya.
     *
     * @param  Collection<int, JadwalTahunan>  $schedules
     * @return Collection<string, PeriodeJadwal>
     */
    private function selectedPeriod(Collection $schedules, array $query, string $today): Collection
    {
        $schedule = $schedules->firstWhere('id', strtolower($query['jadwal_id']));
        if (! $schedule || $today > $schedule->penutupan->toDateString()) {
            throw ValidationException::withMessages(['jadwal_id' => 'Pilih jadwal aktif yang belum melewati tanggal penutupan.']);
        }
        $period = PeriodeJadwal::with('periode')->where('jadwal_id', $schedule->id)->where('periode_id', $query['periode_id'])->first()
            ?? throw ValidationException::withMessages(['periode_id' => 'Periode bukan anggota jadwal yang dipilih.']);

        return collect([$period->id => $period]);
    }

    /** @param EloquentCollection<string, JadwalTahunan> $schedules */
    private function scheduleOptions(EloquentCollection $schedules): array
    {
        $periods = PeriodeJadwal::with('periode')->whereIn('jadwal_id', $schedules->modelKeys())->get()->groupBy('jadwal_id');

        return $schedules->map(fn ($schedule) => ['id' => $schedule->id, 'tahun' => $schedule->tahun,
            'periode' => ($periods[$schedule->id] ?? collect())->sortBy('periode.urutan')->map(fn ($period) => $this->periodDto($period, $schedule->tahun))->values()->all()])->values()->all();
    }

    private function periodDto(PeriodeJadwal $period, int $tahun): array
    {
        return ['id' => $period->periode_id, 'nama_periode' => $period->periode->nama, 'urutan' => $period->periode->urutan, 'tahun' => $tahun, 'is_nilai_akhir' => $period->periode->is_nilai_akhir];
    }
}
