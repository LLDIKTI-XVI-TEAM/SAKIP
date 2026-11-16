<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalTahunan extends Model
{
    use HasUuids;

    protected $table = 'jadwal_tahunan';

    public $timestamps = false;

    protected $fillable = ['renstra_id', 'tahun', 'penutupan', 'status', 'renstra_pk_id', 'activated_at', 'closed_at', 'koreksi_mulai', 'koreksi_sampai', 'lingkup_koreksi'];

    protected $casts = ['tahun' => 'integer', 'penutupan' => 'date', 'revisi' => 'integer', 'pakai_persetujuan_pimpinan' => 'boolean', 'persetujuan_mulai' => 'date', 'persetujuan_selesai' => 'date', 'koreksi_mulai' => 'datetime', 'koreksi_sampai' => 'datetime', 'lingkup_koreksi' => 'array'];

    protected $appends = ['is_terkunci'];

    /** @return BelongsTo<Renstra, $this> */
    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class);
    }

    /**
     * Jadwal yang terkait langsung ke PK atau ke Renstra+tahun yang sama (fallback legacy).
     *
     * @param  Builder<self>  $query
     */
    public function scopeTerkaitPk(Builder $query, RenstraPk $pk): void
    {
        $query->where(function (Builder $sub) use ($pk) {
            $sub->where('renstra_pk_id', $pk->id)
                ->orWhere(fn (Builder $legacy) => $legacy->where('renstra_id', $pk->renstra_id)->where('tahun', $pk->tahun));
        });
    }

    /**
     * Jadwal yang membuat PK tidak lagi boleh diubah tanpa alasan: aktif, ditutup, atau pernah diaktifkan.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMengunciPk(Builder $query): void
    {
        $query->where(function (Builder $sub) {
            $sub->whereIn('status', ['aktif', 'ditutup'])
                ->orWhereNotNull('activated_at');
        });
    }

    /** @return HasMany<PeriodeJadwal, $this> */
    public function periode(): HasMany
    {
        return $this->hasMany(PeriodeJadwal::class, 'jadwal_id');
    }

    /** Snapshot allowlist kalender; pemanggil menjaga koherensi parent dan children dalam transaksi. @return array<string, mixed> */
    public function draftAttributes(): array
    {
        return [
            ...$this->only(['id', 'renstra_id', 'tahun', 'status', 'revisi']),
            'penutupan' => $this->penutupan?->format('Y-m-d'),
            'periode' => $this->periode->sortBy('periode_id')->map(fn (PeriodeJadwal $window): array => [
                'periode_id' => $window->periode_id,
                'pengisian_mulai' => $window->pengisian_mulai->format('Y-m-d'),
                'pengisian_selesai' => $window->pengisian_selesai->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    /** Aktivasi pertama sudah terjadi dan jadwal masih aktif; dasar replay no-op dan blocker sudah_aktif. */
    public function isActivated(): bool
    {
        return $this->status === 'aktif' && $this->activated_at !== null;
    }

    /** Sesi koreksi tidak membuka kembali mutasi normal, termasuk sebelum penutupan asli dan setelah deadline sesi. */
    public function tahunDitutup(?CarbonInterface $pada = null): bool
    {
        if ($this->status === 'ditutup' || $this->koreksi_mulai !== null || $this->koreksi_sampai !== null || $this->lingkup_koreksi !== null) {
            return true;
        }
        $penutupan = $this->penutupan?->toDateString();

        return is_string($penutupan) && ($pada ?? now())->copy()->setTimezone(config('app.business_timezone'))->toDateString() > $penutupan;
    }

    public function getIsTerkunciAttribute(): bool
    {
        return $this->status === 'aktif'
            || $this->status === 'ditutup'
            || $this->activated_at !== null;
    }

    /**
     * Indikator aktif PK tahun jadwal ini: indikator yang punya snapshot pada jadwal ini (termasuk yang ditambahkan
     * di tengah tahun) dan tidak berstatus arsip. Setiap versi PK menyimpan target seluruh indikator ini.
     *
     * @return Builder<IndikatorKinerja>
     */
    public function indikatorAktif(): Builder
    {
        return IndikatorKinerja::query()->where('indikator_kinerjas.status', '!=', IndikatorKinerja::STATUS_ARSIP)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('jadwal_snapshot')
                ->whereColumn('jadwal_snapshot.indikator_id', 'indikator_kinerjas.id')->where('jadwal_snapshot.jadwal_id', $this->id));
    }

    /**
     * Periode berurutan terendah yang belum dilaporkan: masih ada indikator aktif yang kewajiban pengukurannya
     * berlaku pada periode itu (ada versi snapshot yang mulai pada atau sebelum periode itu) tetapi header
     * pengukurannya untuk indikator × tahun × periode belum `disahkan`, termasuk yang belum dibuat.
     * Indikator `dinilai_tahunan` ikut dihitung. Tanpa tanggal: jendela pengisian yang sudah lewat tidak menutup periode.
     */
    public function periodePertamaBelumDilaporkan(): ?PeriodeJadwal
    {
        return PeriodeJadwal::query()->with('periode')->where('jadwal_periode.jadwal_id', $this->id)
            ->join('periode as pr', 'pr.id', '=', 'jadwal_periode.periode_id')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('jadwal_snapshot as s')
                ->join('periode as pm', 'pm.id', '=', 's.periode_mulai_id')
                ->join('indikator_kinerjas as i', 'i.id', '=', 's.indikator_id')
                ->where('s.jadwal_id', $this->id)->where('i.status', '!=', IndikatorKinerja::STATUS_ARSIP)
                ->whereColumn('pm.urutan', '<=', 'pr.urutan')
                ->whereNotExists(fn ($h) => $h->selectRaw('1')->from('pengukuran_kinerjas as h')
                    ->whereColumn('h.indikator_id', 's.indikator_id')->where('h.tahun', $this->tahun)
                    ->whereColumn('h.periode_id', 'jadwal_periode.periode_id')->where('h.status_alur', 'disahkan')))
            ->orderBy('pr.urutan')->select('jadwal_periode.*')->first();
    }

    /**
     * Snapshot terbaru (nomor versi terbesar) tiap indikator aktif, berkunci `indikator_id`. Targetnya adalah nilai
     * dasar revisi PK. Bentuk NOT EXISTS dipakai karena PostgreSQL menolak FOR UPDATE bersama DISTINCT ON atau window.
     *
     * @return Collection<string, JadwalSnapshot>
     */
    public function snapshotDasar(bool $kunci = false): Collection
    {
        $query = JadwalSnapshot::query()->where('jadwal_snapshot.jadwal_id', $this->id)
            ->whereIn('jadwal_snapshot.indikator_id', $this->indikatorAktif()->select('indikator_kinerjas.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('jadwal_snapshot as baru')
                ->whereColumn('baru.jadwal_id', 'jadwal_snapshot.jadwal_id')->whereColumn('baru.indikator_id', 'jadwal_snapshot.indikator_id')
                ->whereColumn('baru.nomor_versi', '>', 'jadwal_snapshot.nomor_versi'))
            ->orderBy('jadwal_snapshot.indikator_id');

        return ($kunci ? $query->lockForUpdate() : $query)->get()->keyBy('indikator_id');
    }

    /**
     * Pengukuran indikator yang belum disahkan pada periode jadwal sebelum periode mulai versi snapshot baru;
     * pengukuran itu tetap memakai versi lama, jadi pengguna mengonfirmasinya lebih dulu.
     *
     * @return Builder<PengukuranKinerja>
     */
    public function pengukuranBelumDisahkanSebelum(string $indikatorId, Periode $mulai): Builder
    {
        $this->loadMissing('periode.periode');
        $sebelum = $this->periode->filter(fn (PeriodeJadwal $window): bool => $window->periode->urutan < $mulai->urutan)->pluck('periode_id')->all();

        return PengukuranKinerja::belumDisahkanPadaJadwal($this->id)->where('pengukuran_kinerjas.indikator_id', $indikatorId)
            ->whereIn('pengukuran_kinerjas.periode_id', $sebelum)->orderBy('pengukuran_kinerjas.id');
    }

    /**
     * Token isi dasar revisi PK: berubah bila ada versi snapshot baru atau indikator aktif bertambah/berkurang.
     *
     * @param  Collection<string, JadwalSnapshot>  $snapshotDasar
     */
    public static function tokenDasar(Collection $snapshotDasar): string
    {
        return hash('sha256', $snapshotDasar->pluck('id')->sort()->implode(','));
    }
}
