<?php

namespace App\Services\Pengingat;

use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\JadwalActivationReadiness;
use App\Services\Jadwal\SesiKoreksiJadwal;
use App\Services\PenanggungJawab\WorkReadiness;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

/** Pengingat merupakan hasil baca; tidak membuat header, pengaturan, notifikasi, atau audit. */
class PengingatKontekstual
{
    public function __construct(private PermissionResolver $izin, private DaftarKewajibanPengukuran $kewajiban, private WorkReadiness $readiness, private JadwalActivationReadiness $aktivasi) {}

    /**
     * Periode normal termasuk terlambat, atau jendela PIC resmi di dalam sesi koreksi.
     *
     * @return Collection<int, PeriodeJadwal>
     */
    public function periode(): Collection
    {
        $today = today(config('app.business_timezone'));

        return PeriodeJadwal::with(['jadwal', 'periode'])->whereHas('jadwal', fn ($q) => $q->where('status', 'aktif'))
            ->where('pengisian_mulai', '<=', $today->toDateString())->get()
            ->filter(fn ($p) => (! $p->jadwal->tahunDitutup() || SesiKoreksiJadwal::jendelaPicTerbuka($p->jadwal, $p))
                && ! PeriodeTanpaTarget::isPeriodeLampau($p->jadwal, $p))->values();
    }

    /**
     * Query bersama hitungan dan fokus; pemeriksaan akses tetap sah sesudah semua kewajiban diajukan.
     *
     * @param  Collection<int|string, PeriodeJadwal>  $periodes  Kandidat dengan relasi jadwal dan periode yang sudah dimuat.
     */
    public function pribadiQuery(User $actor, Collection $periodes, string $aksesHalaman = 'pengukuran:read', bool $hanyaBelumTuntas = true): Builder
    {
        $q = $this->kewajiban->query($periodes->modelKeys(), $this->izin->unitDitolak($actor, 'pengukuran:read'), today(config('app.business_timezone'))->toDateString())
            ->where('pic.user_id', $actor->id)->where('k.berlaku', true)->where('u.status', 'aktif')->where('i.status', '!=', 'arsip')
            ->when($hanyaBelumTuntas, fn ($q) => $q->where(fn ($q) => $q->whereNull('h.id')->orWhereIn('h.status_alur', ['draft', 'dikembalikan'])))
            ->where(function ($q) use ($periodes) {
                $q->whereRaw('1 = 0');
                foreach ($periodes as $p) {
                    if (! $p->jadwal->tahunDitutup()) {
                        $q->orWhere('k.periode_jadwal_id', $p->id);
                    } elseif (SesiKoreksiJadwal::jendelaPicTerbuka($p->jadwal, $p)) {
                        $ids = array_values(array_filter($p->jadwal->lingkup_koreksi['indikator_ids'],
                            fn ($id) => SesiKoreksiJadwal::berlaku($p->jadwal, 'pengukuran', $id, [$p->periode_id])));
                        $q->orWhere(fn ($q) => $q->where('k.periode_jadwal_id', $p->id)->whereIn('k.indikator_id', $ids));
                    }
                }
            })
            ->whereNotIn('cs.unit_id', $this->izin->unitDitolak($actor, $aksesHalaman));
        if (! $this->izin->allows($actor, 'pengukuran:read') || ! $this->izin->allows($actor, $aksesHalaman)) {
            $q->whereRaw('1 = 0');
        }
        if ($units = $this->izin->unitTerbaca($actor, $aksesHalaman)) {
            $q->whereIn('cs.unit_id', $units);
        }

        return $q;
    }

    /** Semua tanggal dan tingkat urgensi ditentukan pada hari bisnis WITA, bukan tanggal perangkat. */
    public function tenggat(PeriodeJadwal $periode): ?array
    {
        $today = CarbonImmutable::today(config('app.business_timezone'));
        $end = CarbonImmutable::parse($periode->pengisian_selesai->toDateString(), config('app.business_timezone'));
        $days = (int) $today->diffInDays($end, false);
        $ambang = config('pengingat.ambang_hari');
        if ($days > $ambang['info']) {
            return null;
        }
        $tingkat = $days <= $ambang['danger'] ? 'danger' : ($days <= $ambang['warning'] ? 'warning' : 'info');
        $date = $end->locale('id')->translatedFormat('j F Y');

        return ['tingkat' => $tingkat, 'lewat' => $days < 0, 'tanggal' => $date,
            'judul' => $days < 0 ? 'Batas pengisian terlewat' : ($days === 0 ? 'Batas pengisian hari ini' : "Batas pengisian: $days hari lagi"),
            'keterangan' => $days < 0 ? "Batas pengisian terlewat sejak $date. Hubungi Tim Perencanaan." : "Batas pengisian $date."];
    }

    /** Payload eksplisit, tanpa model mentah dan tanpa dataset indikator penuh. */
    public function handle(User $actor, string $aksesHalaman = 'pengukuran:read', bool $pk = true): array
    {
        $periods = $this->periode()->keyBy('id');
        $q = $this->pribadiQuery($actor, $periods, $aksesHalaman)->reorder();
        if ($aksesHalaman === 'rencana_aksi:read') {
            $q->leftJoin('rencana_aksi as ra', fn ($join) => $join->on('ra.indikator_id', '=', 'k.indikator_id')->on('ra.tahun', '=', 'k.tahun'));
        }
        $groups = $q->select(['k.periode_jadwal_id', 'cs.unit_id'])->selectRaw("count(*) as jumlah, count(*) FILTER (WHERE h.id IS NULL) as absen, count(*) FILTER (WHERE h.status_alur = 'draft') as draft, count(*) FILTER (WHERE h.status_alur = 'dikembalikan') as revisi")
            ->when($aksesHalaman === 'rencana_aksi:read', fn ($q) => $q->selectRaw("count(*) FILTER (WHERE ra.id IS NULL OR ra.status_alur <> 'disahkan') as ra_belum"))
            ->groupBy('k.periode_jadwal_id', 'cs.unit_id')->get();
        $personal = [];
        $rights = [];
        $decisions = $groups->isEmpty() ? [] : $this->izin->decideManyForUnits($actor, PermissionCatalog::UNIT_SCOPED, $groups->pluck('unit_id')->unique()->all());
        foreach ($groups->groupBy('periode_jadwal_id') as $periodId => $entries) {
            $p = $periods[$periodId];
            $g = (object) ['absen' => $entries->sum('absen'), 'draft' => $entries->sum('draft'), 'revisi' => $entries->sum('revisi'), 'jumlah' => $entries->sum('jumlah')];
            $deadline = $this->tenggat($p);
            if ($deadline === null) {
                continue;
            }
            $missing = false;
            foreach ($entries as $entry) {
                $rights[$entry->unit_id] ??= $this->readiness->forUser($actor, $entry->unit_id, $decisions[$entry->unit_id]);
                $available = $rights[$entry->unit_id]['available'];
                $missing = $missing || ($entry->absen > 0 && ! in_array('pengukuran:create', $available, true))
                    || (($entry->draft + $entry->revisi) > 0 && ! in_array('pengukuran:update', $available, true));
            }
            $counts = [];
            if ($g->absen > 0) {
                $counts[] = "$g->absen Belum mengisi";
            }
            if ($g->draft > 0) {
                $counts[] = "$g->draft ".($deadline['lewat'] ? 'Tidak mengisi' : 'Dalam proses · Draf');
            }
            if ($g->revisi > 0) {
                $counts[] = "$g->revisi Dalam proses · Dikembalikan";
            }
            $description = $deadline['keterangan'].' '.implode('; ', $counts).'.';
            if ($aksesHalaman === 'rencana_aksi:read' && $entries->sum('ra_belum') > 0) {
                $description .= ' Rencana Aksi belum disahkan.';
            }
            if ($missing) {
                $description .= ' Hak input pada unit ini belum tersedia; hubungi Tim Perencanaan atau pengelola akses';
            }
            $href = $deadline['lewat'] || $missing ? null : route('pengukuran.index', ['fokus' => 'kewajiban', 'jadwal_id' => $p->jadwal_id, 'periode_id' => $p->periode_id, 'asal' => $aksesHalaman]);
            $personal[] = $this->item('pribadi:'.$p->id, $deadline['tingkat'], $deadline['judul'].' · '.$p->periode->nama.' '.$p->jadwal->tahun,
                $description, $href, $p->pengisian_selesai->toDateString().':'.$g->jumlah);
        }

        return ['pribadi' => $personal, 'pk' => $pk ? $this->pk($actor) : [], 'ringkasan' => []];
    }

    /** Agregat Perencanaan memakai tally yang sama dengan dashboard; tidak_mengisi sudah termasuk draft. */
    public function ringkasan(User $actor, ?JadwalTahunan $jadwal, ?PeriodeJadwal $periode, array $stats): array
    {
        if (! $jadwal || ! $periode || $jadwal->tahunDitutup() || ! $this->izin->allowsRoleNavigation($actor, 'pengukuran:update')) {
            return [];
        }
        $deadline = $this->tenggat($periode);
        $n = $stats['belum_mengisi'] + $stats['draft'] + $stats['dikembalikan'];
        if (! $deadline || $n === 0) {
            return [];
        }

        return [$this->item('ringkasan:'.$periode->id, $deadline['tingkat'], $deadline['judul'].' · '.$periode->periode->nama.' '.$jadwal->tahun,
            $deadline['keterangan']." $n dari {$stats['total']} indikator belum diajukan.", null, $periode->pengisian_selesai->toDateString().':'.$n.':'.$stats['total'])];
    }

    private function pk(User $actor): array
    {
        $pk = $this->izin->allows($actor, 'pk:create') || $this->izin->allows($actor, 'pk:update');
        $activation = $this->izin->allows($actor, 'jadwal:aktivasi');
        if (! $pk && ! $activation) {
            return [];
        }
        $year = (int) today(config('app.business_timezone'))->year;
        $items = [];
        foreach (JadwalTahunan::where('status', 'draft')->whereIn('tahun', [$year, $year + 1])->get() as $jadwal) {
            $result = $this->aktivasi->evaluate($this->aktivasi->context($jadwal), CarbonImmutable::now());
            $gates = collect($result['gates'])->whereIn('key', ['G1', 'G2', 'G4'])->where('status', 'gagal');
            if ($gates->isEmpty()) {
                continue;
            }
            if ($gates->contains('key', 'G1')) {
                $gates = $gates->reject(fn ($gate) => $gate['key'] === 'G4');
            }
            $item = $this->item('pk:'.$jadwal->id, 'warning', 'Perjanjian Kinerja '.$jadwal->tahun.' belum lengkap',
                $gates->pluck('message')->implode(' '), $pk ? route('perjanjian-kinerja.index') : route('jadwal.show', $jadwal->id), (string) $jadwal->revisi);
            $item['label'] = $pk ? 'Lihat Perjanjian Kinerja' : 'Lihat kesiapan aktivasi';
            $items[] = $item;
        }

        return $items;
    }

    private function item(string $id, string $tingkat, string $judul, string $keterangan, ?string $href, string $version): array
    {
        return ['id' => $id, 'signature' => hash('sha256', $id.$tingkat.$judul.$keterangan.$version), 'tingkat' => $tingkat,
            'judul' => $judul, 'keterangan' => $keterangan, 'href' => $href, 'label' => $href ? 'Lihat kewajiban' : null];
    }
}
