<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Authorization\RoleCatalog;
use App\Services\Kinerja\KomponenMutationService;
use App\Services\Perencanaan\KodeUrutService;
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
        private readonly KomponenMutationService $komponen,
        private readonly KodeUrutService $kodeUrut,
    ) {}

    /**
     * Membuat satu Indikator beserta audit pembuatannya.
     *
     * Kode indikator dibangkitkan server-side (`IK-<nomor>`) dari deret kode
     * global, dihitung di dalam transaksi di bawah advisory lock agar dua
     * pembuatan bersamaan tidak menghasilkan kode yang sama; indeks unik
     * `kode` menjadi lapisan kedua. Kode legacy di luar pola (mis. `IKU-3`
     * yang merujuk nomor IKU resmi) tidak menggeser deret.
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
        $basis = [];
        try {
            $result = DB::transaction(function () use ($validated, $actor, &$basis) {
                // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
                $kunci = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_CREATE);
                /** @var User|null $lockedActor */
                $lockedActor = $kunci['aktor'];
                $currentDecision = $kunci['keputusan'];
                $basis = $currentDecision->toAuditBasis();
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

                $componentCreate = null;
                if (array_key_exists('komponen', $validated)) {
                    foreach (['komponen:read', 'komponen:create'] as $permission) {
                        $decision = $this->lockedActor->handle($lockedActor, $permission)['keputusan'];
                        if (! $decision->allowed && ($permission === 'komponen:read' || ! empty($validated['komponen']))) {
                            return ['status' => 'denied', 'dasarIzin' => $decision->toAuditBasis(), 'alasan' => 'Pembuatan definisi komponen ditolak karena izin tidak berlaku.'];
                        }
                        if ($permission === 'komponen:create') {
                            $componentCreate = $decision;
                        }
                    }
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

                // 2c. Serialisasi deret kode global lalu hitung kode & nomor
                // urut berikutnya. Advisory lock diambil sebelum kunci baris
                // (Regulasi/Unit/Sasaran) agar urutan kunci antar transaksi
                // konsisten dan bebas deadlock.
                $this->kodeUrut->kunci('indikator');
                $berikutnya = $this->kodeUrut->berikutnya(
                    KodeUrutService::PREFIX_INDIKATOR,
                    IndikatorKinerja::query()->pluck('kode'),
                );

                // 2d. Urutan kunci global: Regulasi dikunci SEBELUM Unit/Sasaran
                // bila regulasi_id tujuan non-null (null = lewati, tanpa kunci).
                // Kunci bersama diambil di sini agar jalur tulis indikator dan
                // jalur hapus regulasi selalu memperoleh baris Regulasi dahulu;
                // pemeriksaan aktif tetap pada 5c agar urutan galat tak berubah.
                $rawRegulasiId = $validated['regulasi_id'] ?? null;
                if ($rawRegulasiId === '') {
                    $rawRegulasiId = null;
                }
                /** @var Regulasi|null $targetRegulasiTerkunci */
                $targetRegulasiTerkunci = null;
                if ($rawRegulasiId !== null) {
                    $targetRegulasiTerkunci = Regulasi::whereKey($rawRegulasiId)->sharedLock()->first();
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

                // 5c. Validasi ulang status aktif memakai baris regulasi yang sudah
                // dikunci pada 2c (tanpa kunci ulang, agar urutan kunci global
                // Regulasi sebelum Unit/Sasaran terjaga). Tanpa regulasi_id (null)
                // tidak perlu izin/kunci — tulis null.
                // Non-null lolos guard 2b di atas sehingga berizin baca.
                $effectiveRegulasiId = null;
                if ($rawRegulasiId !== null) {
                    if (! $targetRegulasiTerkunci || ! $targetRegulasiTerkunci->aktif) {
                        throw ValidationException::withMessages([
                            'regulasi_id' => 'Rujukan regulasi tidak valid atau sudah nonaktif.',
                        ]);
                    }
                    $effectiveRegulasiId = $rawRegulasiId;
                }

                // Nilai kandidat lengkap sebelum parent maupun child ditulis.
                $candidateRows = collect($validated['komponen'] ?? [])->map(fn ($item, $index) => $this->komponen->modelKandidat('', $item, "komponen.{$index}"));
                if ($candidateRows->pluck('kode')->duplicatesStrict()->isNotEmpty()) {
                    throw ValidationException::withMessages(['kode' => $this->komponen->pesanKodeDuplikat()]);
                }
                $candidate = new IndikatorKinerja(['tipe_perhitungan' => $validated['tipe_perhitungan']]);
                $this->komponen->pastikanDefinisiValid($candidate, $candidateRows, 'tipe_perhitungan');

                $created = IndikatorKinerja::create([
                    'sasaran_strategis_id' => $validated['sasaran_strategis_id'],
                    'regulasi_id' => $effectiveRegulasiId,
                    'kode' => $berikutnya['kode'],
                    'urutan' => $berikutnya['nomor'],
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

                foreach ($candidateRows as $row) {
                    $child = $this->komponen->buat($created->id, $row->getAttributes(), $lockedActor->id);
                    $this->auditLogger->catat(actor: $lockedActor, tindakan: 'komponen.buat', objekTipe: 'indikator_komponen', objekId: $child->id,
                        nilaiLama: null, nilaiBaru: $this->komponen->formatAuditSnapshot($child), alasan: 'Menambah komponen bersama indikator.',
                        dasarIzin: $componentCreate->toAuditBasis());
                }

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
        } catch (ValidationException $exception) {
            if (array_intersect(['tipe_perhitungan', 'sasaran_strategis_id', 'unit_id', 'regulasi_id'], array_keys($exception->errors())) !== []) {
                $this->auditLogger->catat(actor: $actor, tindakan: 'indikator.buat_ditolak', objekTipe: 'indikator', objekId: (string) Str::uuid(), alasan: 'Pembuatan indikator ditolak oleh validasi domain.', dasarIzin: $basis);
            }
            throw $exception;
        }

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
