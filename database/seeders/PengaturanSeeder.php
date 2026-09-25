<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PengaturanSeeder extends Seeder
{
    /**
     * Kunci default pengaturan presentasional (identitas, aplikasi, tampilan, laporan).
     *
     * @var list<array{kunci: string, nilai: string, tipe: string, grup: string}>
     */
    public const DEFAULTS = [
        [
            'kunci' => 'instansi.nama',
            'nilai' => 'Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
            'tipe' => 'string',
            'grup' => 'instansi',
        ],
        [
            'kunci' => 'instansi.alamat',
            'nilai' => 'Jl. Kampus Barat, Gorontalo',
            'tipe' => 'text',
            'grup' => 'instansi',
        ],
        [
            'kunci' => 'instansi.telepon',
            'nilai' => '(0435) 821123',
            'tipe' => 'string',
            'grup' => 'instansi',
        ],
        [
            'kunci' => 'instansi.surel',
            'nilai' => 'lldikti16@kemdikbud.go.id',
            'tipe' => 'string',
            'grup' => 'instansi',
        ],
        [
            'kunci' => 'instansi.laman',
            'nilai' => 'https://lldikti16.kemdikbud.go.id',
            'tipe' => 'url',
            'grup' => 'instansi',
        ],
        [
            'kunci' => 'instansi.logo',
            'nilai' => '/img/dikti16-favicon-blue-150x150.png',
            'tipe' => 'string',
            'grup' => 'instansi',
        ],
        [
            'kunci' => 'aplikasi.nama',
            'nilai' => 'SAKIP LLDIKTI XVI',
            'tipe' => 'string',
            'grup' => 'aplikasi',
        ],
        [
            'kunci' => 'aplikasi.label_unit',
            'nilai' => 'Unit Kerja',
            'tipe' => 'string',
            'grup' => 'aplikasi',
        ],
        [
            'kunci' => 'tampilan.zona_waktu',
            'nilai' => 'Asia/Makassar',
            'tipe' => 'string',
            'grup' => 'tampilan',
        ],
        [
            'kunci' => 'tampilan.format_tanggal',
            'nilai' => 'd F Y',
            'tipe' => 'string',
            'grup' => 'tampilan',
        ],
        [
            'kunci' => 'tampilan.format_angka',
            'nilai' => 'id_ID',
            'tipe' => 'string',
            'grup' => 'tampilan',
        ],
        [
            'kunci' => 'laporan.header',
            'nilai' => "KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI\nLEMBAGA LAYANAN PENDIDIKAN TINGGI WILAYAH XVI",
            'tipe' => 'text',
            'grup' => 'laporan',
        ],
        [
            'kunci' => 'laporan.footer',
            'nilai' => 'Dicetak melalui Sistem Akuntabilitas Kinerja Instansi Pemerintah (SAKIP) LLDIKTI Wilayah XVI',
            'tipe' => 'text',
            'grup' => 'laporan',
        ],
    ];

    public function run(): void
    {
        $now = Carbon::now();

        foreach (self::DEFAULTS as $setting) {
            Pengaturan::firstOrCreate(
                ['kunci' => $setting['kunci']],
                [
                    'nilai' => $setting['nilai'],
                    'tipe' => $setting['tipe'],
                    'grup' => $setting['grup'],
                    'updated_by' => null,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
