<?php

namespace App\Services\Pengingat;

use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Carbon\CarbonImmutable;

/**
 * Gerbang terakhir kelayakan satu PIC sebagai penerima pengingat EWS pada instan kirim; broadcast
 * pembukaan jendela memakainya juga dengan H sama dengan sisa hari sampai tenggat.
 *
 * Penerima harus aktif, memegang peran resmi aktif, dan diizinkan `pengukuran:read` seperti pada
 * daftar Pengukuran. Minimal satu kewajiban belum selesai harus berada di unit yang tidak ditolak
 * baca dan memenuhi hak isi: `pengukuran:create` untuk header yang belum ada atau `pengukuran:update`
 * untuk draf/dikembalikan. Pengingat in-app memakai syarat baca dan isi yang sama.
 *
 * Aturan "belum selesai" (header kosong/draft/dikembalikan, sesi koreksi, unit nonaktif,
 * indikator arsip) tetap milik {@see KandidatPengingatTenggat}; service ini mengevaluasi ulang
 * hasilnya agar daftar kandidat yang dipegang pemanggil tidak dipakai setelah tenggat, ACL,
 * atau status akun berubah.
 *
 * Murni baca: tanpa tulis, audit, outbox, atau queue. Data yang hilang/nonaktif berakhir
 * `false`, bukan exception (fail-closed).
 */
final class KelayakanPenerimaEws
{
    public function __construct(
        private KandidatPengingatTenggat $kandidat,
        private DaftarKewajibanPengukuran $kewajiban,
        private PermissionResolver $izin,
    ) {}

    /**
     * @param  int  $h  H-minus yang diminta pemanggil (EWS: 7, 3, 1; pembukaan: sisa hari sampai tenggat)
     * @param  CarbonImmutable  $saat  Instan kirim; dinormalkan ke zona bisnis sebelum dinilai
     */
    public function layak(User $user, PeriodeJadwal $periodeJadwal, int $h, CarbonImmutable $saat): bool
    {
        // Status akun dapat berubah setelah model pemanggil dibentuk; muat ulang agar
        // penilaian pada instan kirim tidak memakai status/relasi yang sudah basi.
        $user = User::query()->find($user->getKey());
        if (! $user instanceof User || ! $this->izin->allows($user, 'pengukuran:read')) {
            return false;
        }

        // Periode sah, H berjalan, dan kewajiban belum selesai dinilai ulang oleh evaluator pada instan kirim.
        $entri = collect($this->kandidat->untukTanggal($saat, [$h])['kandidat'])
            ->first(fn (array $k): bool => $k['periode_jadwal_id'] === $periodeJadwal->id && $k['user_id'] === $user->id);
        if ($entri === null) {
            return false;
        }

        $tanggalBisnis = $saat->setTimezone(config('app.business_timezone'))->startOfDay();
        // Deny baca dan hak isi dinilai pada unit snapshot dari baris kewajiban yang sama.
        $baris = $this->kewajiban->query(
            [$periodeJadwal->id],
            $this->izin->unitDitolak($user, 'pengukuran:read'),
            $tanggalBisnis->toDateString(),
        )->where('pic.user_id', $user->id)
            ->whereIn('k.indikator_id', array_column($entri['indikator'], 'indikator_id'))
            ->reorder()
            ->select(['cs.unit_id', 'h.id as header_id'])
            ->get();
        $izin = $this->izin->decideManyForUnits($user, ['pengukuran:create', 'pengukuran:update'], $baris->pluck('unit_id')->unique()->values()->all());

        // Sama dengan pengingat in-app: header belum ada perlu create, draf/dikembalikan perlu update.
        return $baris->contains(fn ($r): bool => $izin[$r->unit_id][$r->header_id === null ? 'pengukuran:create' : 'pengukuran:update']['allowed']);
    }
}
