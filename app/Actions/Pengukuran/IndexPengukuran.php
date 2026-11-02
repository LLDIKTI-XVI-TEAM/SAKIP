<?php

namespace App\Actions\Pengukuran;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\SesiKoreksiJadwal;
use App\Services\Pengingat\PengingatKontekstual;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IndexPengukuran
{
    public function __construct(private PresentPengukuran $present, private PermissionResolver $resolver, private PengukuranKinerjaPolicy $policy, private DaftarKewajibanPengukuran $kewajiban) {}

    /**
     * Daftar kewajiban pengukuran: indikator snapshot × periode berjalan setiap jadwal aktif lalu jadwal ditutup sebagai baris
     * baca saja (pengisian_mulai terakhir yang sudah dimulai), atau tepat satu jadwal × periode pilihan jalur Perencanaan
     * sampai tanggal penutupan, atau kapan pun bila jadwal ditutup. Header existing
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
        $focus = ($query['fokus'] ?? null) === 'kewajiban';
        $pengingat = app(PengingatKontekstual::class);
        $filtered = array_key_exists('jadwal_id', $query) || array_key_exists('periode_id', $query);
        if ($filtered && ! $canSelect && ! $focus) {
            throw new AuthorizationException('Pemilihan periode hanya tersedia untuk jalur Perencanaan.');
        }
        $query = Validator::make($query, ['page' => ['nullable', 'integer', 'min:1'], 'fokus' => ['nullable', 'in:kewajiban'], 'asal' => ['nullable', 'in:pengukuran:read,dashboard:read,rencana_aksi:read'],
            ...($filtered ? ['jadwal_id' => ['required', 'uuid'], 'periode_id' => ['required', 'uuid']] : [])])->validate();
        $aksesPengingat = $focus ? ($query['asal'] ?? 'pengukuran:read') : 'pengukuran:read';
        $today = today(config('app.business_timezone'))->toDateString();
        // shortcut: memuat semua jadwal aktif dan ditutup (±1 per tahun, ≤12 periode per jadwal), batasi tahun histori bila jadwal ditutup mencapai puluhan.
        $schedules = JadwalTahunan::whereIn('status', ['aktif', 'ditutup'])->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END")->orderBy('tahun')->orderBy('id')->get()->keyBy('id');
        $closedOn = $schedules->where('status', 'ditutup')->whereNotNull('closed_at')
            ->map(fn ($schedule) => Carbon::parse($schedule->closed_at)->timezone(config('app.business_timezone'))->toDateString());
        if ($filtered) {
            $periods = $this->selectedPeriod($schedules, $query, $today);
        } else {
            $started = PeriodeJadwal::with('periode')->whereIn('jadwal_id', $schedules->modelKeys())->where('pengisian_mulai', '<=', $today)
                ->orderByDesc('pengisian_mulai')->orderBy('id')->get()
                // Tahun ditutup berhenti pada periode yang sudah dimulai saat penutupan, tidak ikut maju bersama hari ini.
                ->reject(fn ($period) => $period->pengisian_mulai->toDateString() > ($closedOn[$period->jadwal_id] ?? $today));
            // Setiap jendela PIC yang terbuka di dalam sesi koreksi ikut tampil karena PIC tidak dapat memakai filter periode.
            $periods = $started->unique('jadwal_id')
                ->merge($started->filter(fn ($period) => SesiKoreksiJadwal::jendelaPicTerbuka($schedules[$period->jadwal_id], $period)))->keyBy('id');
        }

        if ($focus) {
            abort_unless($filtered && $periods->count() === 1, 403);
            $permitted = $pengingat->periode()->modelKeys();
            abort_unless(in_array($periods->first()->id, $permitted, true), 403);
            abort_unless($pengingat->pribadiQuery($actor, $periods->keys()->all(), $aksesPengingat, hanyaBelumTuntas: false)->exists(), 403);
            $rowsQuery = $pengingat->pribadiQuery($actor, $periods->keys()->all(), $aksesPengingat);
        } else {
            $rowsQuery = $this->kewajiban->query($periods->keys()->all(), $this->resolver->unitDitolak($actor, 'pengukuran:read'), $today);
        }
        $page = $rowsQuery->paginate(20, page: $query['page'] ?? 1)->withQueryString();
        $items = collect($page->items());
        $headers = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->withCount(['buktiDukungs' => fn ($q) => $q->current()])->whereIn('id', $items->pluck('pengukuran_id')->filter())->get()->keyBy('id');
        if ($headers->isNotEmpty()) {
            $this->present->prepareSummary($headers);
        }
        $creatable = $items->whereNull('pengukuran_id')->where('berlaku', true)->values();
        $targets = $this->present->periodTargets($creatable->map(fn ($row) => ['indikator_id' => $row->indikator_id, 'tahun' => (int) $row->tahun,
            'periode_id' => $row->periode_id, 'jadwal_snapshot_id' => $row->jadwal_snapshot_id, 'unit_id' => $row->unit_id])->all());
        $targets = array_combine($creatable->map(fn ($row) => $row->periode_jadwal_id.':'.$row->indikator_id)->all(), $targets);
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
                    'pengukuran' => Arr::only($summary, ['id', 'status', 'nilai', 'status_perhitungan', 'target', 'tanpa_target_periode', 'bukti_count', 'self_approval', 'reviu_terlambat'])];
            }
            $create = false;
            if ($row->berlaku) {
                $decision = $decisions[$row->unit_id]['pengukuran:create'];
                $snapshot = $snapshots[$row->jadwal_snapshot_id]->setRelation('jadwal', $schedules[$row->jadwal_id]);
                $create = $decision['allowed'] && $this->policy->creationErrors($actor, $snapshot, $periods[$row->periode_jadwal_id], $decision, $row->pic_id) === [];
            }

            return [...$base, 'jenis' => 'absent', 'berlaku' => (bool) $row->berlaku, 'pengukuran' => null,
                'target' => $row->berlaku ? $targets[$row->periode_jadwal_id.':'.$row->indikator_id]['target'] : null,
                'tanpa_target_periode' => $row->berlaku && $targets[$row->periode_jadwal_id.':'.$row->indikator_id]['tanpa_target_periode'], 'can' => ['create' => $create]];
        })->all();

        $selected = $filtered ? $schedules->only($periods->first()->jadwal_id) : $schedules;
        $options = $schedules->filter(fn ($schedule) => $schedule->status === 'ditutup' || $schedule->lingkup_koreksi !== null || $today <= $schedule->penutupan->toDateString());

        return [
            'pengingat' => $pengingat->handle($actor, $aksesPengingat),
            'konteks' => $selected->map(fn ($schedule) => ['jadwal_id' => $schedule->id, 'tahun' => $schedule->tahun,
                'periode' => ($period = $periods->firstWhere('jadwal_id', $schedule->id)) ? $this->periodDto($period, $schedule->tahun) : null])->values()->all(),
            'filters' => ['jadwal_id' => $filtered ? $periods->first()->jadwal_id : null, 'periode_id' => $filtered ? $periods->first()->periode_id : null, ...($focus ? ['fokus' => 'kewajiban'] : [])],
            'can' => ['selectPeriod' => $canSelect],
            ...($canSelect ? ['pilihan_jadwal' => $this->scheduleOptions($options)] : []),
            'pengukurans' => $rows,
            'pagination' => ['links' => $page->linkCollection()->all(), 'from' => $page->firstItem(), 'to' => $page->lastItem(), 'total' => $page->total()],
        ];
    }

    /**
     * Filter jalur Perencanaan juga mempertahankan jadwal sesi koreksi pascapenutupan; mutasi tetap dinilai Policy.
     *
     * @param  Collection<int, JadwalTahunan>  $schedules
     * @return Collection<string, PeriodeJadwal>
     */
    private function selectedPeriod(Collection $schedules, array $query, string $today): Collection
    {
        $schedule = $schedules->firstWhere('id', strtolower($query['jadwal_id']));
        if (! $schedule || ($schedule->status === 'aktif' && $schedule->lingkup_koreksi === null && $today > $schedule->penutupan->toDateString())) {
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

        return $schedules->map(fn ($schedule) => ['id' => $schedule->id, 'tahun' => $schedule->tahun, 'status' => $schedule->status,
            'periode' => ($periods[$schedule->id] ?? collect())->sortBy('periode.urutan')->map(fn ($period) => $this->periodDto($period, $schedule->tahun))->values()->all()])->values()->all();
    }

    private function periodDto(PeriodeJadwal $period, int $tahun): array
    {
        return ['id' => $period->periode_id, 'nama_periode' => $period->periode->nama, 'urutan' => $period->periode->urutan, 'tahun' => $tahun, 'is_nilai_akhir' => $period->periode->is_nilai_akhir,
            'tanggal' => $period->periode->only(Periode::TANGGAL)];
    }
}
