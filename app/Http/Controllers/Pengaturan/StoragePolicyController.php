<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdateStoragePolicyRequest;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\RenstraPk;
use App\Models\Role;
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
     * Pastikan seluruh kunci kebijakan storage terinisialisasi secara idempoten dan atomik di database.
     */
    protected function ensureDefaultRowsExist(): void
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

        $this->ensureDefaultRowsExist();

        $berkasSettings = Pengaturan::where('grup', 'berkas')->get();
        $existingSettings = $berkasSettings->pluck('nilai', 'kunci');

        $maxUpdatedAt = $berkasSettings->max('updated_at');
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
            'expected_version' => (int) $existingSettings->get('berkas.versi', self::POLICY_KEYS['berkas.versi']['default']),
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

        $submitted = [
            'berkas.unggahan_aktif' => $request->boolean('berkas_unggahan_aktif') ? 'true' : 'false',
            'berkas.ukuran_maks_kb' => (string) $request->input('berkas_ukuran_maks_kb'),
            'berkas.format_diizinkan' => (string) $request->input('berkas_format_diizinkan'),
            'berkas.tautan_selalu_diizinkan' => $request->boolean('berkas_tautan_selalu_diizinkan') ? 'true' : 'false',
        ];

        $expectedUpdatedAt = (string) $request->input('expected_updated_at');
        $expectedVersion = (int) $request->input('expected_version');
        $alasan = (string) $request->input('alasan');

        $result = DB::transaction(function () use ($submitted, $actor, $resolver, $auditLogger, $alasan, $expectedUpdatedAt, $expectedVersion) {
            // Pemeriksaan ulang izin di dalam transaksi: kunci seluruh sumber keputusan otorisasi
            // (mengikuti hierarki User -> Role -> Grants/Denies) dengan urutan konsisten ID untuk mencegah race condition
            /** @var User $currentActor */
            $currentActor = User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

            $actorRoleIds = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $currentActor->id)
                ->where('roles.aktif', true)
                ->pluck('roles.id')
                ->all();
            sort($actorRoleIds);
            if (! empty($actorRoleIds)) {
                Role::whereIn('id', $actorRoleIds)->orderBy('id')->sharedLock()->get();
            }

            Permission::where('kode', 'pengaturan:update')->sharedLock()->first();

            DB::table('user_permission_granted')
                ->where('user_id', $currentActor->id)
                ->orderBy('id')
                ->sharedLock()
                ->get();

            DB::table('user_permission_denied')
                ->where('user_id', $currentActor->id)
                ->orderBy('id')
                ->sharedLock()
                ->get();

            $decision = $resolver->decide($currentActor, 'pengaturan:update');
            if (! ($decision['allowed'] ?? false)) {
                return ['unauthorized' => true, 'decision' => $decision];
            }

            $this->ensureDefaultRowsExist();

            $existing = Pengaturan::where('grup', 'berkas')
                ->lockForUpdate()
                ->get()
                ->keyBy('kunci');

            // Optimistic locking prioritas 1: Versi Monotonik (kebal terhadap tabrakan detik yang sama)
            $versiRow = $existing->get('berkas.versi');
            $currentVersion = (int) ($versiRow?->nilai ?? self::POLICY_KEYS['berkas.versi']['default']);

            if ($expectedVersion !== $currentVersion) {
                throw ValidationException::withMessages([
                    'konflik' => 'Kebijakan storage telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                ]);
            }

            // Optimistic locking prioritas 2: Timestamp ISO
            $maxCurrentTimestamp = $existing->max('updated_at');
            if ($maxCurrentTimestamp !== null && $expectedUpdatedAt !== '') {
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

            // Naikkan versi counter monotonik pada setiap pembaruan
            if ($versiRow === null) {
                $versiRow = new Pengaturan;
                $versiRow->id = (string) Str::uuid();
                $versiRow->kunci = 'berkas.versi';
                $versiRow->tipe = 'integer';
                $versiRow->grup = 'berkas';
            }
            $versiRow->nilai = (string) ($currentVersion + 1);
            $versiRow->updated_by = $actor->id;
            $versiRow->updated_at = $now;
            $versiRow->save();

            // PRD §18.7 & Plan Pengembangan §10.6: Penandaan otomatis dan pencabutan penanda
            // saat status saklar global unggahan file (berkas.unggahan_aktif) berubah.
            if (isset($changedKeys['berkas.unggahan_aktif'])) {
                // Tracking penanda aktif: ambil baris penandaan yang belum dicabut
                // Menggunakan relasi eksplisit penanda_audit_id agar urutan total lifecycle
                // tidak bergantung pada keunikan timestamp (bebas race condition saat frozen clock / rapid toggle)
                $activeMarkedJbAudits = DB::table('audit_log as a')
                    ->where('a.objek_tipe', 'jenis_berkas')
                    ->where('a.tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('audit_log as a2')
                            ->whereColumn('a2.objek_id', 'a.objek_id')
                            ->whereColumn('a2.objek_tipe', 'a.objek_tipe')
                            ->where('a2.tindakan', 'berkas.cabut_tidak_dapat_dipenuhi')
                            ->where(function ($sub) {
                                $sub->whereRaw("(a2.nilai_lama->>'penanda_audit_id') = a.id::text")
                                    ->orWhereRaw("(a2.nilai_baru->>'penanda_audit_id') = a.id::text")
                                    ->orWhere(function ($fallback) {
                                        $fallback->whereNull(DB::raw("a2.nilai_lama->>'penanda_audit_id'"))
                                            ->whereNull(DB::raw("a2.nilai_baru->>'penanda_audit_id'"))
                                            ->whereColumn('a2.waktu', '>', 'a.waktu');
                                    });
                            });
                    })
                    ->get(['a.id', 'a.objek_id']);

                $activeMarkedJbIds = $activeMarkedJbAudits->pluck('objek_id')->unique()->all();

                $activeMarkedPkAudits = DB::table('audit_log as a')
                    ->where('a.objek_tipe', 'renstra_pk')
                    ->where('a.tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('audit_log as a2')
                            ->whereColumn('a2.objek_id', 'a.objek_id')
                            ->whereColumn('a2.objek_tipe', 'a.objek_tipe')
                            ->where('a2.tindakan', 'berkas.cabut_tidak_dapat_dipenuhi')
                            ->where(function ($sub) {
                                $sub->whereRaw("(a2.nilai_lama->>'penanda_audit_id') = a.id::text")
                                    ->orWhereRaw("(a2.nilai_baru->>'penanda_audit_id') = a.id::text")
                                    ->orWhere(function ($fallback) {
                                        $fallback->whereNull(DB::raw("a2.nilai_lama->>'penanda_audit_id'"))
                                            ->whereNull(DB::raw("a2.nilai_baru->>'penanda_audit_id'"))
                                            ->whereColumn('a2.waktu', '>', 'a.waktu');
                                    });
                            });
                    })
                    ->get(['a.id', 'a.objek_id']);

                $activeMarkedPkIds = $activeMarkedPkAudits->pluck('objek_id')->unique()->all();

                if ($changedKeys['berkas.unggahan_aktif']['new'] === 'false') {
                    // Transisi true -> false: tandai persyaratan wajib file-only yang belum aktif ditandai
                    $fileOnlyRequirements = JenisBerkas::query()
                        ->where('aktif', true)
                        ->where('wajib', true)
                        ->where('izinkan_file', true)
                        ->where('izinkan_tautan', false)
                        ->where('izinkan_teks', false)
                        ->whereNotIn('id', $activeMarkedJbIds)
                        ->get();

                    // Gerbang keempat lampiran PK (§2.15, §10.6): PK yang belum memiliki lampiran mode tautan/teks dan belum ditandai
                    $pkWithoutNonFile = RenstraPk::query()
                        ->whereNotExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('berkas')
                                ->whereColumn('berkas.berkasable_id', 'renstra_pk.id')
                                ->whereIn('berkas.berkasable_type', ['renstra_pk', RenstraPk::class, 'App\\Models\\PerjanjianKinerja'])
                                ->whereIn('berkas.mode', ['tautan', 'teks'])
                                ->whereNull('berkas.dihapus_pada');
                        })
                        ->whereNotIn('id', $activeMarkedPkIds)
                        ->get();

                    foreach ($fileOnlyRequirements as $persyaratan) {
                        $prevCount = DB::table('audit_log')
                            ->where('objek_tipe', 'jenis_berkas')
                            ->where('objek_id', $persyaratan->id)
                            ->where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
                            ->count();

                        $auditLogger->catat(
                            actor: $actor,
                            tindakan: 'berkas.tandai_tidak_dapat_dipenuhi',
                            objekTipe: 'jenis_berkas',
                            objekId: $persyaratan->id,
                            nilaiLama: [
                                'nama' => $persyaratan->nama,
                                'tahap' => $persyaratan->tahap,
                                'status_pemenuhan' => 'normal',
                            ],
                            nilaiBaru: [
                                'nama' => $persyaratan->nama,
                                'tahap' => $persyaratan->tahap,
                                'status_pemenuhan' => 'tidak_dapat_dipenuhi',
                                'sebab' => 'saklar_unggahan_global_nonaktif',
                                'kunci_setelan' => 'berkas.unggahan_aktif',
                                'siklus_penandaan' => $prevCount + 1,
                            ],
                            alasan: $alasan,
                            dasarIzin: $decision
                        );
                    }

                    foreach ($pkWithoutNonFile as $pk) {
                        $prevCount = DB::table('audit_log')
                            ->where('objek_tipe', 'renstra_pk')
                            ->where('objek_id', $pk->id)
                            ->where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
                            ->count();

                        $auditLogger->catat(
                            actor: $actor,
                            tindakan: 'berkas.tandai_tidak_dapat_dipenuhi',
                            objekTipe: 'renstra_pk',
                            objekId: $pk->id,
                            nilaiLama: [
                                'nomor_pk' => $pk->nomor_pk,
                                'tahun' => $pk->tahun,
                                'status_gerbang' => 'normal',
                            ],
                            nilaiBaru: [
                                'nomor_pk' => $pk->nomor_pk,
                                'tahun' => $pk->tahun,
                                'status_gerbang' => 'tidak_dapat_dipenuhi',
                                'gerbang' => 'lampiran_pk',
                                'sebab' => 'saklar_unggahan_global_nonaktif',
                                'kunci_setelan' => 'berkas.unggahan_aktif',
                                'siklus_penandaan' => $prevCount + 1,
                            ],
                            alasan: $alasan,
                            dasarIzin: $decision
                        );
                    }
                } elseif ($changedKeys['berkas.unggahan_aktif']['new'] === 'true') {
                    // Transisi false -> true: catat pencabutan HANYA untuk objek yang benar-benar aktif bertanda
                    $jbAuditsByObjek = $activeMarkedJbAudits->groupBy('objek_id');
                    $jbToRevoke = JenisBerkas::whereIn('id', array_keys($jbAuditsByObjek->all()))->get();

                    foreach ($jbToRevoke as $persyaratan) {
                        $audits = $jbAuditsByObjek->get($persyaratan->id, collect());
                        foreach ($audits as $markedAudit) {
                            $auditLogger->catat(
                                actor: $actor,
                                tindakan: 'berkas.cabut_tidak_dapat_dipenuhi',
                                objekTipe: 'jenis_berkas',
                                objekId: $persyaratan->id,
                                nilaiLama: [
                                    'nama' => $persyaratan->nama,
                                    'tahap' => $persyaratan->tahap,
                                    'status_pemenuhan' => 'tidak_dapat_dipenuhi',
                                    'sebab' => 'saklar_unggahan_global_nonaktif',
                                    'penanda_audit_id' => $markedAudit->id,
                                ],
                                nilaiBaru: [
                                    'nama' => $persyaratan->nama,
                                    'tahap' => $persyaratan->tahap,
                                    'status_pemenuhan' => 'normal',
                                    'sebab' => 'saklar_unggahan_global_aktif',
                                    'kunci_setelan' => 'berkas.unggahan_aktif',
                                    'penanda_audit_id' => $markedAudit->id,
                                ],
                                alasan: $alasan,
                                dasarIzin: $decision
                            );
                        }
                    }

                    $pkAuditsByObjek = $activeMarkedPkAudits->groupBy('objek_id');
                    $pkToRevoke = RenstraPk::whereIn('id', array_keys($pkAuditsByObjek->all()))->get();

                    foreach ($pkToRevoke as $pk) {
                        $audits = $pkAuditsByObjek->get($pk->id, collect());
                        foreach ($audits as $markedAudit) {
                            $auditLogger->catat(
                                actor: $actor,
                                tindakan: 'berkas.cabut_tidak_dapat_dipenuhi',
                                objekTipe: 'renstra_pk',
                                objekId: $pk->id,
                                nilaiLama: [
                                    'nomor_pk' => $pk->nomor_pk,
                                    'tahun' => $pk->tahun,
                                    'status_gerbang' => 'tidak_dapat_dipenuhi',
                                    'gerbang' => 'lampiran_pk',
                                    'penanda_audit_id' => $markedAudit->id,
                                ],
                                nilaiBaru: [
                                    'nomor_pk' => $pk->nomor_pk,
                                    'tahun' => $pk->tahun,
                                    'status_gerbang' => 'normal',
                                    'gerbang' => 'lampiran_pk',
                                    'sebab' => 'saklar_unggahan_global_aktif',
                                    'kunci_setelan' => 'berkas.unggahan_aktif',
                                    'penanda_audit_id' => $markedAudit->id,
                                ],
                                alasan: $alasan,
                                dasarIzin: $decision
                            );
                        }
                    }
                }
            }

            return ['changed' => true, 'count' => count($changedKeys), 'new_version' => $currentVersion + 1];
        });

        if ($result['unauthorized'] ?? false) {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'pengaturan.ubah_ditolak',
                objekTipe: 'pengaturan',
                objekId: (string) Str::uuid(),
                alasan: 'Pemeriksaan ulang izin di dalam transaksi mendeteksi izin pengaturan:update telah dicabut.',
                dasarIzin: $result['decision'] ?? null,
            );
            abort(403, 'Izin pengaturan:update telah dicabut.');
        }

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
