<?php

namespace App\Services\Laporan;

use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Storage\UploadLimits;
use App\Support\AuditReason;
use Illuminate\Database\Eloquent\Builder;

class ArsipDokumenResmi
{
    public const AKSES_DIBATASI = 'Dokumen resmi memuat seluruh unit; akses Anda dibatasi pada sebagian unit.';

    public function __construct(private readonly PermissionResolver $izin) {}

    /** Dokumen bertanda tangan tidak dapat disaring; seluruh cakupan baca harus tersedia. */
    public function dapatUnduh(User $actor): bool
    {
        $codes = ['laporan:read', 'laporan:ekspor', 'rencana_aksi:read', 'kegiatan:read'];
        foreach ($codes as $code) {
            if (! $this->izin->allows($actor, $code) || $this->izin->unitDitolak($actor, $code)->exists()) {
                return false;
            }
        }
        foreach (['rencana_aksi:read', 'kegiatan:read'] as $code) {
            $units = $this->izin->unitTerbaca($actor, $code);
            if ($units !== null && Unit::whereNotIn('id', $units)->exists()) {
                return false;
            }
        }

        return true;
    }

    /** Izin dasar tetap memakai resolver kanonis dan identitas terkini. */
    public function izinkan(User $actor, string $permission): void
    {
        abort_unless($this->izin->allows($actor, $permission), 403);
    }

    /** Induk ditentukan server; periode harus benar-benar milik jadwal terpilih. */
    public function induk(string $jadwalId, ?string $periodeId, bool $kunci = false): JadwalTahunan|PeriodeJadwal
    {
        $jadwal = JadwalTahunan::whereKey($jadwalId)->whereIn('status', ['aktif', 'ditutup'])
            ->when($kunci, fn ($q) => $q->lockForUpdate())->firstOrFail();

        return $periodeId === null ? $jadwal : PeriodeJadwal::whereKey($periodeId)->where('jadwal_id', $jadwal->id)
            ->when($kunci, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }

    public function tipe(JadwalTahunan|PeriodeJadwal $induk): string
    {
        return $induk instanceof JadwalTahunan ? 'jadwal_tahunan' : 'jadwal_periode';
    }

    /** @return Builder<Berkas> */
    public function berkas(JadwalTahunan|PeriodeJadwal $induk): Builder
    {
        return Berkas::where('berkasable_type', $this->tipe($induk))->where('berkasable_id', $induk->id);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['jadwal_id' => ['required', 'uuid'], 'periode_jadwal_id' => ['nullable', 'uuid'],
            'file' => ['required', ...UploadLimits::fileRules(null, 'pdf')],
            'keterangan' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_string($value) || str_contains($value, "\0") || ! mb_check_encoding($value, 'UTF-8')) {
                    $fail('Keterangan tidak boleh mengandung karakter byte NUL dan harus berupa teks UTF-8 yang valid.');
                }
            }], 'menggantikan_id' => ['nullable', 'uuid'],
            'alasan_koreksi' => ['required_with:menggantikan_id', 'nullable', 'string', 'max:1000', AuditReason::validate(...)]];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['alasan_koreksi.required_with' => 'Alasan penggantian wajib diisi.'];
    }
}
