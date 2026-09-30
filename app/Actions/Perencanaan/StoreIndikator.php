<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Authorization\RoleCatalog;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreIndikator
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
    ) {}

    /**
     * Membuat satu Indikator beserta audit pembuatannya.
     *
     * Keputusan izin dievaluasi ulang di dalam transaksi terkunci memakai
     * state terkini (-TOCTOU): pencabutan peran/grant/deny atau penonaktifan
     * akun di tengah jalan membuat operasi gagal tertutup. Provenance
     * `created_by_role` diambil dari peran pemberi pada keputusan izin —
     * izin yang hanya bersumber grant langsung tanpa peran ditolak tanpa
     * mengarang peran (fail-closed, ADR 0001). Unit tujuan dan Sasaran induk
     * dikunci dan diperiksa ulang di dalam transaksi yang sama.
     *
     * Penolakan dicatat sebagai audit `indikator.buat_ditolak` di luar
     * transaksi (agar tidak ikut rollback) lalu 403 dilempar.
     *
     * @return array{indikator: IndikatorKinerja, renstraId: ?string}
     */
    public function handle(User $actor, array $validated): array
    {
        $result = DB::transaction(function () use ($validated, $actor) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_CREATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan indikator kinerja ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2b. Guard rujukan regulasi: bila regulasi_id diisi, aktor wajib
            // lolos regulasi:read memakai state terkunci agar tebakan UUID tak
            // bisa menautkan dasar hukum tanpa izin baca. Gagal → 403 + audit.
            if (($validated['regulasi_id'] ?? null) !== null && $validated['regulasi_id'] !== '') {
                $regulasiDecision = $this->resolver->resolve($lockedActor, PermissionCodes::REGULASI_READ);
                if (! $regulasiDecision->allowed) {
                    return [
                        'status' => 'denied',
                        'alasan' => 'Penautan regulasi ditolak karena Anda tidak berwenang membaca data regulasi yang dirujuk.',
                        'dasarIzin' => $regulasiDecision->toAuditBasis(),
                    ];
                }
            }

            // 3. Ambil role aktif aktor yang memberikan izin indikator:create berdasarkan hasil resolusi izin
            // Fail-closed: jangan mengarang role bila izin diperoleh hanya dari direct grant tanpa role pemberi izin
            $grantingRoleIds = $currentDecision->basis['sumber_allow']['roles'] ?? [];
            $createdRole = null;
            if (! empty($grantingRoleIds)) {
                $createdRole = DB::table('roles')
                    ->whereIn('id', (array) $grantingRoleIds)
                    ->where('aktif', true)
                    ->whereIn('kode', RoleCatalog::codes())
                    ->orderBy('urutan')
                    ->value('kode');
            }

            if (! $createdRole || ! in_array($createdRole, IndikatorKinerja::creatableRoles(), true)) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan indikator kinerja ditolak karena wewenang pembuatan tidak bersumber dari peran resmi yang sah untuk provenance.',
                    'dasarIzin' => array_merge($currentDecision->toAuditBasis(), [
                        'penolakan_provenance' => 'Izin pembuatan indikator tidak bersumber dari peran resmi yang sah untuk provenance.',
                    ]),
                ];
            }

            // 4. Kunci dan periksa ulang status unit tujuan di dalam transaksi
            /** @var Unit|null $targetUnit */
            $targetUnit = Unit::whereKey($validated['unit_id'])->sharedLock()->first();
            if (! $targetUnit || $targetUnit->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tidak valid atau sudah nonaktif.',
                ]);
            }

            // 5. Kunci sasaran strategis induk
            $sasaran = SasaranStrategis::whereKey($validated['sasaran_strategis_id'])->sharedLock()->first();
            if (! $sasaran) {
                throw ValidationException::withMessages([
                    'sasaran_strategis_id' => 'Sasaran strategis yang dipilih tidak valid.',
                ]);
            }

            // 5b. Turunkan tahun mulai berlaku dari Renstra induk via Sasaran
            // (kolom NOT NULL; fail-closed bila Renstra tak terbaca).
            $tahunMulaiBerlaku = Renstra::whereKey($sasaran->renstra_id)->sharedLock()->value('tahun_mulai');
            if ($tahunMulaiBerlaku === null) {
                throw ValidationException::withMessages([
                    'sasaran_strategis_id' => 'Sasaran strategis yang dipilih tidak memiliki Renstra induk yang sah.',
                ]);
            }

            $created = IndikatorKinerja::create([
                'sasaran_strategis_id' => $validated['sasaran_strategis_id'],
                'regulasi_id' => $validated['regulasi_id'] ?? null,
                'kode' => trim($validated['kode']),
                'nama' => trim($validated['nama']),
                'definisi_operasional' => isset($validated['definisi_operasional']) ? trim($validated['definisi_operasional']) : null,
                'satuan' => trim($validated['satuan']),
                'unit_id' => $validated['unit_id'],
                'arah' => $validated['arah'],
                'tipe_perhitungan' => $validated['tipe_perhitungan'],
                'presisi' => $validated['presisi'] ?? 2,
                'desimal_tampilan' => $validated['desimal_tampilan'] ?? 2,
                'wajib_catatan' => (bool) ($validated['wajib_catatan'] ?? false),
                'status' => IndikatorKinerja::STATUS_AKTIF,
                'tahun_mulai_berlaku' => (int) $tahunMulaiBerlaku,
                'created_by' => $lockedActor->id,
                'created_by_role' => $createdRole,
            ]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.buat',
                objekTipe: 'indikator',
                objekId: (string) $created->id,
                nilaiLama: null,
                nilaiBaru: $created->toArray(),
                alasan: "Menambah indikator kinerja '{$created->kode} - {$created->nama}'.",
                dasarIzin: $currentDecision->toAuditBasis(),
            );

            return [
                'status' => 'created',
                'indikator' => $created,
                'renstraId' => $sasaran->renstra_id,
            ];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.buat_ditolak',
                objekTipe: 'indikator',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pembuatan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang menambah indikator kinerja.');
        }

        return ['indikator' => $result['indikator'], 'renstraId' => $result['renstraId']];
    }
}
