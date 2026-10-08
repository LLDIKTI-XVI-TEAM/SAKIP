<?php

namespace App\Actions\Perencanaan;

use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Perencanaan\KodeUrutService;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreSasaran
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ResolveLockedActor $lockedActor,
        private readonly KodeUrutService $kodeUrut,
    ) {}

    /**
     * Membuat satu Sasaran di bawah Renstra terpilih beserta audit pembuatannya.
     *
     * Kode sasaran dibangkitkan server-side (`SS-<nomor>`) dari deret kode
     * global, dihitung di dalam transaksi di bawah advisory lock agar dua
     * pembuatan bersamaan tidak menghasilkan kode yang sama. Indeks unik
     * `kode` menjadi lapisan kedua bila serialisasi gagal.
     *
     * Keputusan izin dievaluasi ulang di dalam transaksi terkunci memakai
     * state terkini (anti-TOCTOU): pencabutan peran/grant/deny atau
     * penonaktifan akun di tengah jalan membuat operasi gagal tertutup.
     *
     * Penolakan dicatat sebagai audit `sasaran.buat_ditolak` di luar
     * transaksi (agar tidak ikut rollback) lalu 403 dilempar.
     */
    public function handle(User $actor, array $validated): SasaranStrategis
    {
        $result = DB::transaction(function () use ($validated, $actor) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::SASARAN_CREATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan sasaran strategis ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            // 3. Serialisasi deret kode global lalu hitung kode & nomor urut
            // berikutnya. Advisory lock diambil sebelum kunci baris Renstra
            // agar urutan kunci antar transaksi konsisten (bebas deadlock).
            $this->kodeUrut->kunci('sasaran');
            $berikutnya = $this->kodeUrut->berikutnya(
                KodeUrutService::PREFIX_SASARAN,
                SasaranStrategis::query()->pluck('kode'),
            );

            // 4. Kunci Renstra induk dan cek ulang keberadaan di dalam transaksi
            // (anti-TOCTOU hapus konkuren: validasi FormRequest pra-transaksi
            // tidak cukup; hapus setelah validasi lolos → FK/500 tanpa cek ulang).
            /** @var Renstra|null $renstra */
            $renstra = Renstra::whereKey($validated['renstra_id'])->sharedLock()->first();
            if (! $renstra) {
                throw ValidationException::withMessages([
                    'renstra_id' => 'Renstra yang dipilih tidak valid.',
                ]);
            }

            $created = SasaranStrategis::create([
                'renstra_id' => $validated['renstra_id'],
                'kode' => $berikutnya['kode'],
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $berikutnya['nomor'],
            ]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.buat',
                objekTipe: 'sasaran',
                objekId: (string) $created->id,
                nilaiLama: null,
                nilaiBaru: $created->toArray(),
                alasan: "Menambah sasaran strategis '{$created->kode}'.",
                dasarIzin: $dasarIzin,
            );

            return [
                'status' => 'created',
                'sasaran' => $created,
            ];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.buat_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pembuatan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang menambah sasaran strategis.');
        }

        return $result['sasaran'];
    }
}
