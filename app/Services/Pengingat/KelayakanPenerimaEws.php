<?php

namespace App\Services\Pengingat;

use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\PenanggungJawab\WorkReadiness;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gerbang terakhir kelayakan satu PIC sebagai penerima pengingat EWS pada instan antre/kirim.
 *
 * Penerima hanya layak bila akun aktif, memegang peran resmi aktif, periode jadwal masih sah
 * pada instan tersebut, H yang diminta masih sama dengan tenggat berjalan, PIC masih punya
 * kewajiban belum selesai, dan hak isi (`pengukuran:create` atau `pengukuran:update`) tersedia
 * pada minimal satu unit indikator kandidatnya.
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
        private PengingatKontekstual $pengingat,
        private KandidatPengingatTenggat $kandidat,
        private DaftarKewajibanPengukuran $kewajiban,
        private PermissionResolver $izin,
        private WorkReadiness $readiness,
    ) {}

    /**
     * @param  int  $h  H-minus yang diminta scheduler (mis. 7, 3, 1)
     * @param  CarbonImmutable  $saat  Instan kirim; dinormalkan ke zona bisnis sebelum dinilai
     */
    public function layak(User $user, PeriodeJadwal $periodeJadwal, int $h, CarbonImmutable $saat): bool
    {
        if ($user->status !== 'aktif' || $this->izin->penggunaBerperanAktif([$user->id]) === []) {
            return false;
        }

        $tanggalBisnis = $saat->setTimezone(config('app.business_timezone'))->startOfDay();

        // Periode dinilai ulang pada instan kirim: jadwal nonaktif, tahun ditutup, periode lampau
        // 2026, dan jendela sesi koreksi semuanya berakhir di sini tanpa perlakuan khusus.
        $periode = $this->pengingat->periode($saat)
            ->first(fn (PeriodeJadwal $p): bool => $p->id === $periodeJadwal->id);
        if ($periode === null) {
            return false;
        }

        // Tenggat dibaca dari keadaan sekarang, bukan model yang dipegang pemanggil, sehingga
        // perubahan `pengisian_selesai` membuat permintaan H lama gagal tertutup.
        $jatuhTempo = CarbonImmutable::parse($periode->pengisian_selesai->toDateString(), config('app.business_timezone'));
        if ((int) $tanggalBisnis->diffInDays($jatuhTempo, false) !== $h) {
            return false;
        }

        $entri = collect($this->kandidat->untukTanggal($saat, [$h])['kandidat'])
            ->first(fn (array $k): bool => $k['periode_jadwal_id'] === $periodeJadwal->id && $k['user_id'] === $user->id);
        if ($entri === null) {
            return false;
        }

        $indikatorIds = array_column($entri['indikator'], 'indikator_id');
        // Unit diambil dari snapshot baris kewajiban (`cs.unit_id`) agar sama dengan unit yang
        // dinilai evaluator dan resolver izin. Deny kosong: penyaringan unit bukan wewenang di sini.
        $unitIds = $this->kewajiban->query(
            [$periodeJadwal->id],
            DB::table('unit')->select('id')->whereRaw('1 = 0'),
            $tanggalBisnis->toDateString(),
        )->where('pic.user_id', $user->id)
            ->whereIn('k.indikator_id', $indikatorIds)
            ->reorder()
            ->select('cs.unit_id')
            ->distinct()
            ->pluck('cs.unit_id')
            ->all();

        foreach ($unitIds as $unitId) {
            $available = $this->readiness->forUser($user, $unitId)['available'];
            if (in_array('pengukuran:create', $available, true) || in_array('pengukuran:update', $available, true)) {
                return true;
            }
        }

        return false;
    }
}
