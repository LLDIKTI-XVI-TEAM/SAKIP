<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $revisi Token perubahan metadata untuk mendeteksi editor stale.
 * @property int|null $bulan_mulai
 * @property int|null $hari_mulai
 * @property int|null $bulan_selesai
 * @property int|null $hari_selesai
 */
class Periode extends Model
{
    use HasUuids;

    /** Tanggal mulai dan selesai tanpa tahun; keempatnya terisi atau kosong bersamaan. */
    public const TANGGAL = ['bulan_mulai', 'hari_mulai', 'bulan_selesai', 'hari_selesai'];

    protected $table = 'periode';

    public $timestamps = false;

    protected $fillable = ['nama', 'urutan', 'aktif', 'is_nilai_akhir', 'bulan_mulai', 'hari_mulai', 'bulan_selesai', 'hari_selesai'];

    protected $casts = ['urutan' => 'integer', 'aktif' => 'boolean', 'is_nilai_akhir' => 'boolean', 'revisi' => 'integer',
        'bulan_mulai' => 'integer', 'hari_mulai' => 'integer', 'bulan_selesai' => 'integer', 'hari_selesai' => 'integer'];

    /** @return HasMany<PeriodeJadwal, $this> */
    public function jendela(): HasMany
    {
        return $this->hasMany(PeriodeJadwal::class, 'periode_id');
    }

    /** Dipanggil dalam transaksi sebelum lock domain; pembaca/simpan jadwal berbagi lock katalog. */
    public static function lockConfiguration(bool $exclusive = false): void
    {
        // ponytail: mutasi master global menahan simpan kalender; pecah lock per katalog bila contention terukur.
        $function = $exclusive ? 'pg_advisory_xact_lock' : 'pg_advisory_xact_lock_shared';
        DB::select("SELECT $function(hashtextextended('sakip:periode-konfigurasi', 0))");
    }

    /** @return array<string, mixed> */
    public function masterAttributes(): array
    {
        return $this->only(['id', 'nama', 'urutan', 'aktif', 'is_nilai_akhir', 'revisi', ...self::TANGGAL]);
    }

    public function metadataLocked(): bool
    {
        return $this->jendela()->whereHas('jadwal', fn ($query) => $query->whereIn('status', ['aktif', 'ditutup']))->exists();
    }

    /**
     * Tanggal tanpa tahun (Q70 §70.1) dibentuk menjadi rentang pada tahun jadwal atau kegiatan; null bila belum diisi.
     *
     * @return array{mulai: string, selesai: string}|null
     */
    public function rentangPadaTahun(int $tahun): ?array
    {
        if ($this->bulan_mulai === null || $this->hari_mulai === null || $this->bulan_selesai === null || $this->hari_selesai === null) {
            return null;
        }

        return [
            'mulai' => sprintf('%04d-%02d-%02d', $tahun, $this->bulan_mulai, $this->hari_mulai),
            'selesai' => sprintf('%04d-%02d-%02d', $tahun, $this->bulan_selesai, $this->hari_selesai),
        ];
    }

    /** Jendela pengisian paling cepat tanggal 1 bulan terakhir periode pada tahun jadwal (Q70 §70.2 butir 2); null bila belum diisi. */
    public function batasAwalPengisian(int $tahun): ?string
    {
        return $this->bulan_selesai === null ? null : sprintf('%04d-%02d-01', $tahun, $this->bulan_selesai);
    }
}
