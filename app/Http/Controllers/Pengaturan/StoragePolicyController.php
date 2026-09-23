<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdateStoragePolicyRequest;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Storage\StorageMetricsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoragePolicyController extends Controller
{
    /**
     * Whitelist kunci kebijakan storage tingkat aplikasi beserta metadata default.
     *
     * @var array<string, array{tipe: string, default: string}>
     */
    protected const POLICY_KEYS = [
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
    ];

    /**
     * Tampilkan panel metrik dan formulir kebijakan storage bukti dukung.
     */
    public function index(Request $request, StorageMetricsService $metricsService): Response
    {
        /** @var User|null $actor */
        $actor = $request->user();
        $resolver = app(PermissionResolver::class);

        $canUpdate = $actor !== null && $resolver->allows($actor, 'pengaturan:update');
        $canRead = $canUpdate || ($actor !== null && $resolver->allows($actor, 'jenis_berkas:read'));

        if (! $canRead) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat kebijakan storage aplikasi.');
        }

        $existingSettings = Pengaturan::where('grup', 'berkas')
            ->pluck('nilai', 'kunci');

        $maxUpdatedAt = Pengaturan::where('grup', 'berkas')->max('updated_at');
        $expectedUpdatedAt = $maxUpdatedAt !== null ? Carbon::parse($maxUpdatedAt)->toISOString() : now()->toISOString();

        $settings = [
            'berkas_unggahan_aktif' => filter_var(
                $existingSettings->get('berkas.unggahan_aktif', self::POLICY_KEYS['berkas.unggahan_aktif']['default']),
                FILTER_VALIDATE_BOOLEAN
            ),
            'berkas_ukuran_maks_kb' => (int) $existingSettings->get(
                'berkas.ukuran_maks_kb',
                self::POLICY_KEYS['berkas.ukuran_maks_kb']['default']
            ),
            'berkas_format_diizinkan' => (string) $existingSettings->get(
                'berkas.format_diizinkan',
                self::POLICY_KEYS['berkas.format_diizinkan']['default']
            ),
            'berkas_tautan_selalu_diizinkan' => filter_var(
                $existingSettings->get('berkas.tautan_selalu_diizinkan', self::POLICY_KEYS['berkas.tautan_selalu_diizinkan']['default']),
                FILTER_VALIDATE_BOOLEAN
            ),
            'expected_updated_at' => $expectedUpdatedAt,
        ];

        $metrics = $metricsService->calculate();

        return Inertia::render('Pengaturan/StorageIndex', [
            'settings' => $settings,
            'metrics' => $metrics,
            'can' => [
                'update' => $canUpdate,
            ],
        ]);
    }

    /**
     * Perbarui kebijakan storage dan saklar unggahan secara atomik dengan pencatatan audit log per kunci.
     */
    public function update(UpdateStoragePolicyRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolver = app(PermissionResolver::class);
        $decision = $resolver->decide($actor, 'pengaturan:update');

        $submitted = [
            'berkas.unggahan_aktif' => $request->boolean('berkas_unggahan_aktif') ? 'true' : 'false',
            'berkas.ukuran_maks_kb' => (string) $request->input('berkas_ukuran_maks_kb'),
            'berkas.format_diizinkan' => (string) $request->input('berkas_format_diizinkan'),
            'berkas.tautan_selalu_diizinkan' => $request->boolean('berkas_tautan_selalu_diizinkan') ? 'true' : 'false',
        ];

        $expectedUpdatedAt = (string) $request->input('expected_updated_at');
        $alasan = (string) $request->input('alasan');

        $result = DB::transaction(function () use ($submitted, $actor, $auditLogger, $alasan, $decision, $expectedUpdatedAt) {
            $existing = Pengaturan::where('grup', 'berkas')
                ->lockForUpdate()
                ->get()
                ->keyBy('kunci');

            $maxCurrentTimestamp = $existing->max('updated_at');
            if ($maxCurrentTimestamp !== null) {
                try {
                    $currentIso = Carbon::parse($maxCurrentTimestamp)->toISOString();
                    $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                    if ($currentIso !== $expectedIso) {
                        throw ValidationException::withMessages([
                            'konflik' => 'Kebijakan storage telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                        ]);
                    }
                } catch (\Exception $e) {
                    if ($e instanceof ValidationException) {
                        throw $e;
                    }
                    throw ValidationException::withMessages([
                        'expected_updated_at' => 'Format timestamp versi tidak valid.',
                    ]);
                }
            }

            $changedKeys = [];

            foreach ($submitted as $key => $newVal) {
                $meta = self::POLICY_KEYS[$key];
                $row = $existing->get($key);
                $oldVal = $row?->nilai ?? $meta['default'];

                if ($oldVal !== $newVal) {
                    $changedKeys[$key] = [
                        'old' => $oldVal,
                        'new' => $newVal,
                        'row' => $row,
                        'tipe' => $meta['tipe'],
                    ];
                }
            }

            // Pencegahan no-op: jika tidak ada nilai yang berubah, jangan update DB atau catat audit palsu
            if (empty($changedKeys)) {
                return ['changed' => false, 'count' => 0];
            }

            $now = now();

            foreach ($changedKeys as $key => $change) {
                $row = $change['row'];
                if ($row === null) {
                    $row = new Pengaturan;
                    $row->id = (string) Str::uuid();
                    $row->kunci = $key;
                    $row->tipe = $change['tipe'];
                    $row->grup = 'berkas';
                }

                $row->nilai = $change['new'];
                $row->updated_by = $actor->id;
                $row->updated_at = $now;
                $row->save();

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'pengaturan.ubah',
                    objekTipe: 'pengaturan',
                    objekId: $row->id,
                    nilaiLama: ['kunci' => $key, 'nilai' => $change['old']],
                    nilaiBaru: ['kunci' => $key, 'nilai' => $change['new']],
                    alasan: $alasan,
                    dasarIzin: $decision
                );
            }

            return ['changed' => true, 'count' => count($changedKeys)];
        });

        if (! $result['changed']) {
            return redirect()
                ->route('pengaturan.storage.index')
                ->with('message', 'Tidak ada perubahan pada nilai kebijakan storage.');
        }

        return redirect()
            ->route('pengaturan.storage.index')
            ->with('success', "Kebijakan storage berhasil diperbarui ({$result['count']} kunci diubah).");
    }
}
