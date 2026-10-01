<?php

namespace App\Actions\Pengaturan;

use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Storage\StorageMetricsService;
use App\Services\Storage\StoragePolicyDefaults;
use Carbon\Carbon;

class GetStoragePolicyPage
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly StoragePolicyDefaults $defaults,
        private readonly StorageMetricsService $metrics,
    ) {}

    /**
     * @return array{
     *     settings: array{berkas_unggahan_aktif: bool, berkas_ukuran_maks_kb: int, berkas_format_diizinkan: string, berkas_tautan_selalu_diizinkan: bool, expected_updated_at: string, expected_version: int},
     *     metrics: array{file_count: int, file_total_bytes: int, link_count: int, text_count: int, total_evidence_count: int, by_induk: array<string, array{induk: string, label: string, file_count: int, file_bytes: int, link_count: int, text_count: int, total_count: int}>},
     *     can: array{update: bool}
     * }
     */
    public function handle(User $actor): array
    {
        $canUpdate = $this->resolver->allows($actor, 'pengaturan:update');
        $canRead = $canUpdate || $this->resolver->allows($actor, 'jenis_berkas:read');

        if (! $canRead) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat kebijakan storage aplikasi.');
        }

        $this->defaults->ensure();

        $berkasSettings = Pengaturan::where('grup', 'berkas')->get();
        $existingSettings = $berkasSettings->pluck('nilai', 'kunci');

        $maxUpdatedAt = $berkasSettings->max('updated_at');
        $expectedUpdatedAt = $maxUpdatedAt !== null ? Carbon::parse($maxUpdatedAt)->toISOString() : now()->toISOString();

        $settings = [
            'berkas_unggahan_aktif' => filter_var(
                $existingSettings->get('berkas.unggahan_aktif', StoragePolicyDefaults::POLICY_KEYS['berkas.unggahan_aktif']['default']),
                FILTER_VALIDATE_BOOLEAN
            ),
            'berkas_ukuran_maks_kb' => (int) $existingSettings->get(
                'berkas.ukuran_maks_kb',
                StoragePolicyDefaults::POLICY_KEYS['berkas.ukuran_maks_kb']['default']
            ),
            'berkas_format_diizinkan' => (string) $existingSettings->get(
                'berkas.format_diizinkan',
                StoragePolicyDefaults::POLICY_KEYS['berkas.format_diizinkan']['default']
            ),
            'berkas_tautan_selalu_diizinkan' => filter_var(
                $existingSettings->get('berkas.tautan_selalu_diizinkan', StoragePolicyDefaults::POLICY_KEYS['berkas.tautan_selalu_diizinkan']['default']),
                FILTER_VALIDATE_BOOLEAN
            ),
            'expected_updated_at' => $expectedUpdatedAt,
            'expected_version' => (int) $existingSettings->get('berkas.versi', StoragePolicyDefaults::POLICY_KEYS['berkas.versi']['default']),
        ];

        $metrics = $this->metrics->calculate();

        return [
            'settings' => $settings,
            'metrics' => $metrics,
            'can' => [
                'update' => $canUpdate,
            ],
        ];
    }
}
