<?php

namespace App\Services\Notifikasi;

use App\Models\PengukuranKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Menentukan penerima notifikasi di server untuk listener in-app; dipisah dari listener
 * karena dipakai ulang kanal notifikasi lain (eksternal) dengan aturan penerima yang sama.
 * Setiap method mengembalikan akun unik yang aktif dan berperan aktif: akun tanpa peran
 * tidak punya izin apa pun (penugasan PJ tidak memberi akses), jadi dilewati.
 * Hanya membaca ACL lewat PermissionResolver dan Policy; tanpa transaksi, kunci, atau audit.
 * Pemanggil: KirimNotifikasiInApp dan AntrekanPembukaanJendela. Item yang sudah ditulis tidak disaring ulang saat request;
 * tautannya diotorisasi route tujuan.
 */
class PenerimaNotifikasi
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly DaftarKewajibanPengukuran $kewajiban) {}

    /**
     * Saring akun aktif berperan aktif, buang duplikat dan `$kecuali`; yang dilewati dicatat sebagai id saja.
     *
     * @param  list<string|null>  $userIds
     * @return list<User>
     */
    public function aktif(array $userIds, ?string $kecuali = null): array
    {
        $ids = array_values(array_unique(array_filter($userIds, fn (?string $id): bool => $id !== null && $id !== $kecuali)));
        if ($ids === []) {
            return [];
        }
        $users = User::whereIn('id', $this->resolver->penggunaBerperanAktif($ids))->where('status', 'aktif')->get()->all();
        $dilewati = array_values(array_diff($ids, array_map(fn (User $u): string => $u->id, $users)));
        if ($dilewati !== []) {
            Log::info('Penerima notifikasi dilewati: akun nonaktif atau tanpa peran aktif.', ['user_ids' => $dilewati]);
        }

        return $users;
    }

    /**
     * PJ efektif penerima pengembalian/buka-kembali: akun aktif berperan aktif yang tidak ditolak eksplisit
     * membaca objek pada unitnya (deny menang, keputusan PM atas R8). PJ yang hanya belum punya grant unit
     * tetap menerima item dan ditolak 403 saat membukanya; deny yang ditetapkan sesudah item dibuat
     * ditangani halaman tujuan.
     *
     * @return list<User>
     */
    public function pjPengembalian(?string $penerimaId, string $izinBaca, string $unitId): array
    {
        $users = $this->aktif([$penerimaId]);
        $ditolak = array_filter($users, fn (User $u): bool => $this->resolver->decide($u, $izinBaca, $unitId)['reason'] === 'explicit_deny');
        if ($ditolak !== []) {
            Log::info('Penerima notifikasi dilewati: ditolak eksplisit membaca unit objek.', ['user_ids' => array_values(array_map(fn (User $u): string => $u->id, $ditolak))]);
        }

        return array_values(array_diff_key($users, $ditolak));
    }

    /**
     * Perencanaan aktif yang lolos gerbang halaman tujuan: baca objek dan izin verifikasi pada unit itu
     * (deny menang). Pengaju dikecualikan; Superadmin tidak termasuk karena bukan peran Perencanaan.
     *
     * @return list<User>
     */
    public function peninjauPerencanaan(RencanaAksi|PengukuranKinerja $objek, string $izinVerifikasi, string $unitId, string $kecuali): array
    {
        $kandidat = User::where('status', 'aktif')->where('id', '!=', $kecuali)
            ->whereIn('id', DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('roles.kode', 'perencanaan')->where('roles.aktif', true)->select('user_roles.user_id'))
            ->orderBy('id')->get();

        // Izin dinilai per kandidat lewat resolver; layak untuk puluhan akun Perencanaan.
        // Bila kandidat mencapai ratusan, ganti dengan evaluasi ACL batch yang diuji setara dengan resolver.
        return $kandidat->filter(fn (User $u): bool => Gate::forUser($u)->allows('view', $objek)
            && $this->resolver->decide($u, $izinVerifikasi, $unitId)['allowed'])->values()->all();
    }

    /**
     * Penerima rekap progres: akun aktif berperan Perencanaan aktif yang boleh membuka Dashboard, tujuan tautan
     * pesan (deny menang). Superadmin tidak termasuk karena bukan peran Perencanaan. `$userIds` membatasi
     * penilaian pada akun tertentu, dipakai gerbang kirim untuk menilai ulang satu penerima.
     *
     * @param  list<string>|null  $userIds
     * @return list<User>
     */
    public function penerimaRekap(?array $userIds = null): array
    {
        $kandidat = User::where('status', 'aktif')
            ->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds))
            ->whereIn('id', DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('roles.kode', 'perencanaan')->where('roles.aktif', true)->select('user_roles.user_id'))
            ->orderBy('id')->get();

        // Izin dinilai per kandidat lewat resolver; layak untuk puluhan akun Perencanaan.
        return $kandidat->filter(fn (User $u): bool => $this->resolver->allows($u, 'dashboard:read'))->values()->all();
    }

    /**
     * Sidik daftar unit yang ditolak baca bagi penerima rekap. Dibekukan bersama angka saat antre dan dibandingkan
     * di gerbang kirim, agar rekap yang memuat unit yang kemudian ditolak tidak terkirim tanpa menghitung ulang angka.
     */
    public function cakupanRekap(User $user): string
    {
        return hash('sha256', $this->resolver->unitDitolak($user, 'dashboard:read')->orderBy('user_permission_denied.unit_id')->pluck('unit_id')->implode(','));
    }

    /**
     * PJ efektif pada `$tanggalBisnis` untuk indikator yang wajib diisi pada periode itu: versi snapshot berlaku,
     * unit aktif, indikator non-arsip. `$indikatorIds` null = semua indikator; `[]` = tidak ada (lingkup sesi koreksi kosong).
     * Deny baca per pembaca tidak disaring di sini: PJ yang ditolak baca unit tetap menerima item,
     * dan daftar pengukuran menyaring baris unit itu saat dibuka.
     *
     * @param  list<string>|null  $indikatorIds
     * @return list<User>
     */
    public function pjJendela(string $periodeJadwalId, ?array $indikatorIds, string $tanggalBisnis): array
    {
        $picIds = $this->kewajiban->query([$periodeJadwalId], DB::table('unit')->whereRaw('1 = 0')->select('id'), $tanggalBisnis)
            ->where('k.berlaku', true)->where('u.status', 'aktif')->where('i.status', '!=', 'arsip')->whereNotNull('pic.user_id')
            ->when($indikatorIds !== null, fn ($q) => $q->whereIn('k.indikator_id', $indikatorIds))
            // Urutan bawaan memakai kolom di luar SELECT, sehingga DISTINCT gagal di PostgreSQL tanpa reorder().
            ->reorder()->select('pic.user_id')->distinct()->pluck('user_id')->all();

        return $this->aktif($picIds);
    }

    /**
     * Pengelola akses: peran aktif yang memuat `akses:update` (kandidat satu query, karena izin ini tidak pernah
     * diberikan lewat grant), lalu wajib lolos `akses:update` dan `pengguna:read` agar nama akun baru
     * tidak sampai ke Admin yang ditolak membaca pengguna.
     *
     * @return list<User>
     */
    public function pengelolaAkses(): array
    {
        $kandidat = User::where('status', 'aktif')
            ->whereIn('id', DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('roles.aktif', true)->where('permissions.kode', 'akses:update')->select('user_roles.user_id'))
            ->orderBy('id')->get();

        // Izin dinilai per kandidat lewat resolver; layak untuk puluhan akun Admin.
        // Bila kandidat mencapai ratusan, ganti dengan evaluasi ACL batch yang diuji setara dengan resolver.
        return $kandidat->filter(function (User $u): bool {
            $izin = $this->resolver->decideMany($u, ['akses:update', 'pengguna:read']);

            return $izin['akses:update']['allowed'] && $izin['pengguna:read']['allowed'];
        })->values()->all();
    }
}
