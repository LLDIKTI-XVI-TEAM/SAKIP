<?php

namespace Database\Seeders;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndikatorKomponenFixtureSeeder extends Seeder
{
    /**
     * Identitas fixture yang diketahui dan stabil.
     *
     * Lookup creator SELALU memakai email ini (terurut, bukan User::first()),
     * sehingga hasil sama di DB kosong maupun berisi. Keycloak tetap ini
     * hanya untuk baris yang baru dibuat; baris lama dengan email sama
     * dipakai apa adanya (keycloak acak historis tidak ditulis ulang).
     * Ini identitas data fixture dev/test, bukan jalur auth production
     * (login tetap via Keycloak SSO + JIT provisioning).
     */
    private const FIXTURE_USER_EMAIL = 'perencanaan@sakip.local';

    private const FIXTURE_USER_KEYCLOAK_ID = 'fixture-perencanaan-sakip-local';

    private const FIXTURE_USER_NAMA = 'Perencanaan SAKIP';

    /**
     * Menanam fixture IKU-3 dan IKU-8 beserta komponennya.
     *
     * Provenance jujur: created_by_role diturunkan dari peran aktual creator,
     * bukan hardcode. Bila creator belum punya peran, peran perencanaan
     * dilekatkan dulu agar jejak audit mencerminkan penugasan nyata.
     *
     * created_by/created_by_role/status adalah create-only: hanya dipakai saat
     * create, tidak pernah disertakan pada payload update (immutable).
     * Baris existing dipertahankan apa adanya, termasuk yang berstatus arsip
     * (arsip adalah final via endpoint/audit, bukan via seeder).
     */
    public function run(): void
    {
        $creator = User::where('email', self::FIXTURE_USER_EMAIL)->orderBy('id')->first();

        if (! $creator instanceof User) {
            $creator = User::create([
                'id' => (string) Str::uuid(),
                'keycloak_id' => self::FIXTURE_USER_KEYCLOAK_ID,
                'nama' => self::FIXTURE_USER_NAMA,
                'email' => self::FIXTURE_USER_EMAIL,
                'status' => 'aktif',
            ]);
        }

        $creator->loadMissing('roles');

        if ($creator->roles->isEmpty()) {
            $perencanaanRole = Role::where('kode', 'perencanaan')->where('aktif', true)->first();

            if ($perencanaanRole !== null) {
                $creator->roles()->attach($perencanaanRole->id, [
                    'id' => (string) Str::uuid(),
                    // Kolom user_roles.sumber_pemberian hanya mengizinkan
                    // manual|sso_onboarding|bootstrap (CHECK DB), sehingga
                    // penugasan fixture dicatat sebagai manual oleh diri sendiri.
                    'sumber_pemberian' => 'manual',
                    'diberikan_oleh' => $creator->id,
                    'created_at' => now(),
                ]);
                $creator->load('roles');
            }
        }

        $creatorRole = $creator->roles->first()?->kode;

        if ($creatorRole === null) {
            throw new \LogicException('Seeder membutuhkan minimal satu peran aktif pada katalog (jalankan AccessCatalogSeeder dulu).');
        }

        $unit = Unit::first() ?? Unit::create([
            'nama' => 'Bagian Perencanaan dan Kerjasama',
            'status' => 'aktif',
            'created_by' => $creator->id,
        ]);

        $renstra = Renstra::where('kode', 'RENSTRA-2025-2029')->first() ?? Renstra::create([
            'kode' => 'RENSTRA-2025-2029',
            'created_by' => $creator->id,
            'nama' => 'Rencana Strategis LLDIKTI Wilayah XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);

        $sasaran = SasaranStrategis::where('renstra_id', $renstra->id)->where('kode', 'SS-01')->first()
            ?? SasaranStrategis::create([
                'renstra_id' => $renstra->id,
                'kode' => 'SS-01',
                'deskripsi' => 'Meningkatnya tata kelola dan akuntabilitas kinerja LLDIKTI Wilayah XVI',
                'urutan' => 1,
            ]);

        // 1. Fixture IKU-3: Formula Final 2 Input Efektif (sakip & zi_wbk, bobot 0.5)
        // created_by/created_by_role/status hanya untuk create (immutable, guard
        // model + trigger DB menolak perubahan), tidak ikut payload update.
        // Status existing dipertahankan (arsip final, tak direaktivasi seeder).
        $iku3Mutable = [
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'nama' => 'Nilai Akuntabilitas Kinerja dan Pembangunan Zona Integritas',
            'satuan' => 'Indeks',
            'tipe_perhitungan' => 'penjumlahan',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'tahun_mulai_berlaku' => $renstra->tahun_mulai,
        ];
        $iku3CreateOnly = [
            'status' => 'aktif',
            'created_by' => $creator->id,
            'created_by_role' => $creatorRole,
        ];
        $iku3 = $this->syncIndikator('IKU-3', $iku3Mutable, $iku3CreateOnly);

        $this->syncKomponen($iku3->id, 'sakip', [
            'label' => 'Skor SAKIP',
            'peran' => 'penjumlah',
            'bobot' => 0.5,
            'urutan' => 1,
            'satuan' => 'Skor',
            'aktif' => true,
        ], ['created_by' => $creator->id]);

        $this->syncKomponen($iku3->id, 'zi_wbk', [
            'label' => 'Skor ZI-WBK',
            'peran' => 'penjumlah',
            'bobot' => 0.5,
            'urutan' => 2,
            'satuan' => 'Skor',
            'aktif' => true,
        ], ['created_by' => $creator->id]);

        // 2. Fixture IKU-8: Rasio Persen n/t (tanpa konstanta 84)
        $iku8Mutable = [
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'nama' => 'Persentase Publikasi Ilmiah PTS Terakreditasi Wilayah Kerja',
            'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'tahun_mulai_berlaku' => $renstra->tahun_mulai,
        ];
        $iku8CreateOnly = [
            'status' => 'aktif',
            'created_by' => $creator->id,
            'created_by_role' => $creatorRole,
        ];
        $iku8 = $this->syncIndikator('IKU-8', $iku8Mutable, $iku8CreateOnly);

        $this->syncKomponen($iku8->id, 'n', [
            'label' => 'Jumlah publikasi ilmiah PTS yang terakreditasi',
            'peran' => 'pembilang',
            'bobot' => 1.0,
            'urutan' => 1,
            'satuan' => 'publikasi',
            'aktif' => true,
        ], ['created_by' => $creator->id]);

        $this->syncKomponen($iku8->id, 't', [
            'label' => 'total publikasi seluruh PTS wilayah kerja',
            'peran' => 'penyebut',
            'bobot' => 1.0,
            'urutan' => 2,
            'satuan' => 'publikasi',
            'aktif' => true,
        ], ['created_by' => $creator->id]);
    }

    /**
     * Sinkronisasi indikator per kode: update hanya field mutable,
     * create memakai gabungan mutable + create-only.
     *
     * @param  array<string, mixed>  $mutable
     * @param  array<string, mixed>  $createOnly
     */
    private function syncIndikator(string $kode, array $mutable, array $createOnly): IndikatorKinerja
    {
        $existing = IndikatorKinerja::where('kode', $kode)->first();

        if ($existing instanceof IndikatorKinerja) {
            $existing->update($mutable);

            return $existing->refresh();
        }

        return IndikatorKinerja::create(array_merge(['kode' => $kode], $mutable, $createOnly));
    }

    /**
     * Sinkronisasi komponen per (indikator, kode): created_by hanya untuk create.
     *
     * @param  array<string, mixed>  $mutable
     * @param  array<string, mixed>  $createOnly
     */
    private function syncKomponen(string $indikatorId, string $kode, array $mutable, array $createOnly): IndikatorKomponen
    {
        $existing = IndikatorKomponen::where('indikator_id', $indikatorId)->where('kode', $kode)->first();

        if ($existing instanceof IndikatorKomponen) {
            $existing->update($mutable);

            return $existing->refresh();
        }

        return IndikatorKomponen::create(array_merge(['indikator_id' => $indikatorId, 'kode' => $kode], $mutable, $createOnly));
    }
}
