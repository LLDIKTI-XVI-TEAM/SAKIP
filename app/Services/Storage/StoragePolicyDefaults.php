<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoragePolicyDefaults
{
    /**
     * Whitelist kunci kebijakan storage tingkat aplikasi beserta metadata default.
     *
     * @var array<string, array{tipe: string, default: string}>
     */
    public const POLICY_KEYS = [
        'berkas.unggahan_aktif' => [
            'tipe' => 'boolean',
            'default' => 'true',
        ],
        'berkas.ukuran_maks_kb' => [
            'tipe' => 'integer',
            'default' => '10240',
        ],
        'berkas.format_diizinkan' => [
            'tipe' => 'string',
            'default' => 'pdf,docx,xlsx,jpg,jpeg,png',
        ],
        'berkas.tautan_selalu_diizinkan' => [
            'tipe' => 'boolean',
            'default' => 'true',
        ],
        'berkas.versi' => [
            'tipe' => 'integer',
            'default' => '1',
        ],
    ];

    /**
     * Isi hanya default yang hilang; transaksi seluruh operasi dimiliki caller.
     */
    public function ensure(): void
    {
        $now = now();
        foreach (self::POLICY_KEYS as $key => $meta) {
            DB::table('pengaturan')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'kunci' => $key,
                'nilai' => $meta['default'],
                'tipe' => $meta['tipe'],
                'grup' => 'berkas',
                'updated_at' => $now,
            ]);
        }
    }
}
