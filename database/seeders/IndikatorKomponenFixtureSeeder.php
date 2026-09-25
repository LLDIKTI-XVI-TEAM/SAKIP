<?php

namespace Database\Seeders;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndikatorKomponenFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::first() ?? User::create([
            'id' => (string) Str::uuid(),
            'keycloak_id' => (string) Str::uuid(),
            'nama' => 'Perencanaan SAKIP',
            'email' => 'perencanaan@sakip.local',
            'is_active' => true,
        ]);

        $unit = Unit::first() ?? Unit::create([
            'nama' => 'Bagian Perencanaan dan Kerjasama',
            'status' => 'aktif',
            'created_by' => $creator->id,
        ]);

        $renstra = Renstra::where('kode', 'RENSTRA-2025-2029')->first() ?? Renstra::create([
            'kode' => 'RENSTRA-2025-2029',
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
        $iku3 = IndikatorKinerja::updateOrCreate(
            ['kode' => 'IKU-3'],
            [
                'sasaran_strategis_id' => $sasaran->id,
                'unit_id' => $unit->id,
                'nama' => 'Nilai Akuntabilitas Kinerja dan Pembangunan Zona Integritas',
                'satuan' => 'Indeks',
                'tipe_perhitungan' => 'penjumlahan',
                'arah' => 'naik_baik',
                'presisi' => 2,
                'desimal_tampilan' => 2,
                'is_aktif' => true,
            ]
        );

        IndikatorKomponen::updateOrCreate(
            ['indikator_id' => $iku3->id, 'kode' => 'sakip'],
            [
                'label' => 'Skor SAKIP',
                'peran' => 'penjumlah',
                'bobot' => 0.5,
                'urutan' => 1,
                'satuan' => 'Skor',
                'aktif' => true,
                'created_by' => $creator->id,
            ]
        );

        IndikatorKomponen::updateOrCreate(
            ['indikator_id' => $iku3->id, 'kode' => 'zi_wbk'],
            [
                'label' => 'Skor ZI-WBK',
                'peran' => 'penjumlah',
                'bobot' => 0.5,
                'urutan' => 2,
                'satuan' => 'Skor',
                'aktif' => true,
                'created_by' => $creator->id,
            ]
        );

        // 2. Fixture IKU-8: Rasio Persen n/t (tanpa konstanta 84)
        $iku8 = IndikatorKinerja::updateOrCreate(
            ['kode' => 'IKU-8'],
            [
                'sasaran_strategis_id' => $sasaran->id,
                'unit_id' => $unit->id,
                'nama' => 'Persentase Publikasi Ilmiah PTS Terakreditasi Wilayah Kerja',
                'satuan' => '%',
                'tipe_perhitungan' => 'rasio_persen',
                'arah' => 'naik_baik',
                'presisi' => 2,
                'desimal_tampilan' => 2,
                'is_aktif' => true,
            ]
        );

        IndikatorKomponen::updateOrCreate(
            ['indikator_id' => $iku8->id, 'kode' => 'n'],
            [
                'label' => 'Jumlah publikasi ilmiah PTS yang terakreditasi',
                'peran' => 'pembilang',
                'bobot' => 1.0,
                'urutan' => 1,
                'satuan' => 'publikasi',
                'aktif' => true,
                'created_by' => $creator->id,
            ]
        );

        IndikatorKomponen::updateOrCreate(
            ['indikator_id' => $iku8->id, 'kode' => 't'],
            [
                'label' => 'total publikasi seluruh PTS wilayah kerja',
                'peran' => 'penyebut',
                'bobot' => 1.0,
                'urutan' => 2,
                'satuan' => 'publikasi',
                'aktif' => true,
                'created_by' => $creator->id,
            ]
        );
    }
}
