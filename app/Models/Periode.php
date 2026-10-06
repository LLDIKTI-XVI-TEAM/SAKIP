<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** @property int $revisi Token perubahan metadata untuk mendeteksi editor stale. */
class Periode extends Model
{
    use HasUuids;

    protected $table = 'periode';

    public $timestamps = false;

    protected $fillable = ['nama', 'urutan', 'aktif', 'is_nilai_akhir'];

    protected $casts = ['urutan' => 'integer', 'aktif' => 'boolean', 'is_nilai_akhir' => 'boolean', 'revisi' => 'integer'];

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
        return $this->only(['id', 'nama', 'urutan', 'aktif', 'is_nilai_akhir', 'revisi']);
    }

    public function metadataLocked(): bool
    {
        return $this->jendela()->whereHas('jadwal', fn ($query) => $query->whereIn('status', ['aktif', 'ditutup']))->exists();
    }
}
