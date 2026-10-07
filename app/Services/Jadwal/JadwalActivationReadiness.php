<?php

namespace App\Services\Jadwal;

use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Services\Kinerja\IndikatorPerhitunganService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Membaca konteks dan menilai prasyarat serta empat gerbang aktivasi jadwal.
 *
 * Dipisah dari Action karena dipakai bersama oleh ReadJadwalActivation (pratinjau
 * GET) dan ActivateJadwal (mutasi), sehingga keduanya menilai aturan yang sama.
 * Service tidak memeriksa izin, tidak membuka transaksi, tidak mengunci baris, dan
 * tidak menulis apa pun termasuk default pengaturan atau penanda pengecualian.
 * Caller menyediakan isolasi: snapshot REPEATABLE READ untuk pratinjau, atau
 * lock sumber sesuai urutan ActivateJadwal sebelum context() dipanggil untuk mutasi.
 * Hasil evaluate() aman untuk aktor activation-only: hanya agregat, tanpa nama,
 * nilai target, formula, maupun identitas lampiran.
 */
class JadwalActivationReadiness
{
    /** Batas kolom jadwal_snapshot.nama; sumber yang lebih panjang tidak dipotong diam-diam. */
    private const SNAPSHOT_NAMA_MAX = 255;

    public function __construct(private readonly JadwalDraftRules $rules, private readonly IndikatorPerhitunganService $perhitungan) {}

    /**
     * @return array{jadwal: JadwalTahunan, renstra: Renstra, pk: ?RenstraPk, lampiran: int, unggahan_aktif: ?string,
     *     eligible: Collection<int, IndikatorKinerja>, targets: Collection<string, TargetKinerja>, snapshotted: list<string>, baru: Collection<int, IndikatorKinerja>}
     */
    public function context(JadwalTahunan $jadwal): array
    {
        $jadwal->load('periode.periode');
        $renstra = Renstra::findOrFail($jadwal->renstra_id);
        $pk = RenstraPk::where('renstra_id', $renstra->id)->where('tahun', $jadwal->tahun)->first();
        // Arsip dan indikator yang baru berlaku pada tahun berikutnya tidak ikut gate maupun snapshot.
        $eligible = IndikatorKinerja::query()
            ->whereIn('sasaran_strategis_id', SasaranStrategis::select('id')->where('renstra_id', $renstra->id))
            ->where('status', IndikatorKinerja::STATUS_AKTIF)->where('tahun_mulai_berlaku', '<=', $jadwal->tahun)
            ->with(['komponen' => fn ($query) => $query->where('aktif', true)->orderBy('urutan')->orderBy('kode')])
            ->orderBy('id')->get();
        $targets = TargetKinerja::whereIn('indikator_kinerja_id', $eligible->modelKeys())->where('tahun', $jadwal->tahun)->get()->keyBy('indikator_kinerja_id');
        // Pasangan dengan versi mana pun dilewati; snapshot existing tidak diselaraskan dengan master terkini.
        $snapshotted = JadwalSnapshot::where('jadwal_id', $jadwal->id)->distinct()->pluck('indikator_id')->all();

        return [
            'jadwal' => $jadwal,
            'renstra' => $renstra,
            'pk' => $pk,
            'lampiran' => $pk ? Berkas::where('berkasable_id', $pk->id)->whereIn('berkasable_type', ['renstra_pk', RenstraPk::class, 'App\Models\PerjanjianKinerja'])->count() : 0,
            'unggahan_aktif' => Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai'),
            'eligible' => $eligible,
            'targets' => $targets,
            'snapshotted' => $snapshotted,
            'baru' => $eligible->reject(fn (IndikatorKinerja $indikator): bool => in_array($indikator->id, $snapshotted, true))->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{jadwal_id: string, checked_revisi: int, checked_at: string, allowed: bool, blockers: list<array{code: string, message: string}>,
     *     gates: list<array{key: string, status: string, message: string, count: ?int}>, counts: array<string, int>, periode_lampau_ids: list<string>}
     */
    public function evaluate(array $context, CarbonImmutable $at): array
    {
        /** @var JadwalTahunan $jadwal */
        $jadwal = $context['jadwal'];
        $renstra = $context['renstra'];
        $eligible = $context['eligible'];
        // Hari operasional WITA: hari penutupan dan hari selesai pengisian berlaku sampai 23:59 lokal.
        $today = $at->setTimezone(config('app.business_timezone'))->toDateString();
        $blockers = [];

        if ($jadwal->isActivated()) {
            $blockers[] = ['code' => 'sudah_aktif', 'message' => 'Jadwal sudah aktif.'];
        } elseif ($jadwal->status !== 'draft' || $jadwal->activated_at !== null) {
            $blockers[] = ['code' => 'status', 'message' => 'Hanya jadwal draft yang belum pernah diaktifkan dapat diaktifkan.'];
        }
        if ($renstra->status !== Renstra::STATUS_AKTIF) {
            $blockers[] = ['code' => 'renstra_tidak_aktif', 'message' => 'Renstra harus berstatus aktif sebelum jadwal diaktifkan.'];
        }
        if (! $this->calendarValid($jadwal)) {
            $blockers[] = ['code' => 'kalender_tidak_valid', 'message' => 'Kalender belum lengkap atau belum valid. Perbaiki dan simpan kalender terlebih dahulu.'];
        }
        if ($jadwal->periode->contains(fn (PeriodeJadwal $window): bool => ! $window->periode->aktif)) {
            $blockers[] = ['code' => 'periode_nonaktif', 'message' => 'Seluruh periode yang dipilih harus aktif.'];
        }
        if ($eligible->isEmpty()) {
            $blockers[] = ['code' => 'indikator_kosong', 'message' => 'Belum ada indikator aktif yang berlaku pada tahun jadwal.'];
        }
        if ($jadwal->penutupan !== null && $today > $jadwal->penutupan->format('Y-m-d')) {
            $blockers[] = ['code' => 'penutupan_lewat', 'message' => 'Tanggal penutupan sudah lewat. Perbaiki tanggal penutupan sebelum aktivasi.'];
        }
        // Hanya pasangan yang akan disalin; snapshot existing tidak dibekukan ulang.
        $inconsistent = $context['baru']->filter(fn (IndikatorKinerja $indikator): bool => mb_strlen($indikator->nama) > self::SNAPSHOT_NAMA_MAX
            || ! $this->perhitungan->validateDefinisiKomponen($indikator)['is_valid'])->count();
        if ($inconsistent > 0) {
            $blockers[] = ['code' => 'data_tidak_konsisten', 'message' => "$inconsistent indikator memiliki data sumber yang tidak dapat dibekukan (nama terlalu panjang atau komponen tidak sesuai tipe perhitungan). Perbaiki pada modul indikator."];
        }

        $missing = $this->missingTargets($context);
        $gates = $this->gates($context, $missing);
        $baru = $context['baru'];

        return [
            'jadwal_id' => $jadwal->id,
            'checked_revisi' => $jadwal->revisi,
            'checked_at' => $at->toIso8601ZuluString(),
            'allowed' => $blockers === [] && collect($gates)->every(fn (array $gate): bool => $gate['status'] !== 'gagal'),
            'blockers' => $blockers,
            'gates' => $gates,
            'counts' => [
                'indikator_berlaku' => $eligible->count(),
                'target_belum_terisi' => $missing,
                'snapshot_existing' => count($context['snapshotted']),
                'snapshot_baru' => $baru->count(),
                'komponen_baru' => $baru->sum(fn (IndikatorKinerja $indikator): int => $indikator->komponen->count()),
            ],
            'periode_lampau_ids' => $jadwal->periode->sortBy(fn (PeriodeJadwal $window): int => $window->periode->urutan)
                ->filter(fn (PeriodeJadwal $window): bool => $window->pengisian_selesai->format('Y-m-d') < $today)
                ->pluck('periode_id')->values()->all(),
        ];
    }

    /** True bila G4 lolos melalui pengecualian unggahan nonaktif, bukan lampiran. @param array<string, mixed> $context */
    public function usesAttachmentException(array $context): bool
    {
        return $context['pk'] !== null && $context['lampiran'] === 0 && $context['unggahan_aktif'] === 'false';
    }

    /** @param array<string, mixed> $context @return list<array{key: string, status: string, message: string, count: ?int}> */
    private function gates(array $context, int $missing): array
    {
        $jadwal = $context['jadwal'];
        $renstra = $context['renstra'];
        $gate = fn (string $key, string $status, string $message, ?int $count = null): array => compact('key', 'status', 'message', 'count');

        return [
            $context['pk'] ? $gate('G1', 'lolos', 'Perjanjian Kinerja tahun ini tersedia.') : $gate('G1', 'gagal', 'Perjanjian Kinerja tahun ini belum dibuat.'),
            match (true) {
                $context['eligible']->isEmpty() => $gate('G2', 'gagal', 'Belum ada indikator yang berlaku untuk dinilai.', 0),
                $missing > 0 => $gate('G2', 'gagal', "$missing indikator belum memiliki target tahunan.", $missing),
                default => $gate('G2', 'lolos', 'Seluruh indikator yang berlaku memiliki target tahunan.', $context['eligible']->count()),
            },
            $jadwal->tahun >= $renstra->tahun_mulai && $jadwal->tahun <= $renstra->tahun_selesai
                ? $gate('G3', 'lolos', 'Tahun jadwal berada dalam rentang Renstra.')
                : $gate('G3', 'gagal', 'Tahun jadwal berada di luar rentang Renstra.'),
            match (true) {
                $context['pk'] === null => $gate('G4', 'gagal', 'Lampiran belum dapat dinilai karena Perjanjian Kinerja belum ada.', 0),
                $context['lampiran'] > 0 => $gate('G4', 'lolos', 'Perjanjian Kinerja memiliki lampiran.', $context['lampiran']),
                // Kunci absen memakai default canonical true; hanya nilai eksplisit false yang membuka pengecualian.
                $this->usesAttachmentException($context) => $gate('G4', 'pengecualian', 'Unggahan berkas sedang dinonaktifkan; lampiran PK dikecualikan dan akan ditandai.', 0),
                default => $gate('G4', 'gagal', 'Perjanjian Kinerja belum memiliki lampiran.', 0),
            },
        ];
    }

    /** Baris absen dan target null sama-sama belum memenuhi G2; nilai 0 sah. @param array<string, mixed> $context */
    private function missingTargets(array $context): int
    {
        return $context['eligible']->filter(fn (IndikatorKinerja $indikator): bool => $context['targets']->get($indikator->id)?->target_tahunan === null)->count();
    }

    private function calendarValid(JadwalTahunan $jadwal): bool
    {
        $data = $jadwal->draftAttributes();
        if ($data['rencana_aksi_mulai'] === null || $data['rencana_aksi_selesai'] === null || $data['penutupan'] === null || $data['periode'] === []) {
            return false;
        }
        try {
            $this->rules->validate($data, $jadwal->periode->mapWithKeys(fn (PeriodeJadwal $window): array => [$window->periode_id => ['urutan' => $window->periode->urutan]])->all());
        } catch (ValidationException) {
            return false;
        }

        return true;
    }
}
