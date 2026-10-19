<?php

namespace App\Actions\RencanaAksi;

use App\Models\AuditLog;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Policies\RencanaAksiPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BacaReviewRencanaAksi
{
    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function handle(User $actor, string $id, array $query): array
    {
        return DB::transaction(function () use ($actor, $id, $query) {
            $header = RencanaAksi::sharedLock()->findOrFail($id);
            Gate::forUser($actor)->authorize('view', $header);
            $query = Validator::make($query, ['versi' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1']])->validate();
            $header->load(['indikator', 'unit', 'jadwalTahunan']);
            $terbaru = $header->versions()->reorder('nomor', 'desc')->first();
            $versi = isset($query['versi']) ? $header->versions()->whereKey($query['versi'])->firstOrFail() : $terbaru;
            abort_unless($versi, 404, 'Belum ada versi pengajuan untuk direview.');
            $versi->load('jadwalSnapshot');
            Gate::forUser($actor)->authorize('viewVersi', [$header, $versi]);
            $policy = app(RencanaAksiPolicy::class);
            $errors = $policy->businessErrors($actor, $header, $versi);
            if ($versi->id !== $terbaru?->id) {
                $errors['verifikasi'][] = $errors['kembalikan'][] = $errors['sahkan'][] = $errors['buka_kembali'][] = 'Versi historis hanya dapat dibaca; review memakai pengajuan terbaru.';
            }
            $buktiBoleh = $policy->viewEvidence($actor, $header);
            // Detail klaim memuat data kegiatan: deny `kegiatan:read` pada unit menyembunyikannya seperti panel RA.
            $kegiatanBoleh = app(PermissionResolver::class)->allows($actor, PermissionCodes::KEGIATAN_READ, (string) $header->unit_id);
            $beku = $versi->snapshot ?? [];
            $bukti = $this->baris($beku['bukti_dukungs'] ?? []);
            $buktiIds = array_values(array_filter(array_column($bukti, 'id'), fn ($id): bool => is_string($id) && Str::isUuid($id)));
            $buktiBerlaku = $buktiBoleh ? $header->buktiDukungs()->current()->whereIn('id', $buktiIds)->where('mode', 'file')->pluck('id')->all() : [];
            // Label komponen dan nama periode klaim diambil dari versi yang sama, bukan master berjalan.
            $targetBeku = $this->baris($beku['target_periode'] ?? []);
            $namaPeriode = array_column($targetBeku, 'periode_nama', 'periode_id');
            $labelKomponen = [];
            foreach ($targetBeku as $baris) {
                foreach ($this->baris($baris['komponen'] ?? []) as $k) {
                    $labelKomponen[(string) ($k['komponen_id'] ?? '')] = ($k['kode'] ?? '—').' — '.($k['label'] ?? '');
                }
            }
            $histori = $header->versions()->select(['id', 'rencana_aksi_id', 'nomor', 'diajukan_at', 'jalur_pengajuan'])
                ->reorder('nomor', 'desc')->paginate(20, page: $query['page'] ?? 1)->withQueryString();
            // Penanda "pernah dibuka kembali" dari audit append-only; versi
            // resmi lama tetap terbaca via histori. Frontend memakai flag ini
            // untuk banner.
            $dibukaKembali = AuditLog::where('objek_tipe', 'rencana_aksi')->where('objek_id', $header->id)
                ->where('tindakan', 'rencana_aksi.buka_kembali')->exists();

            return [
                'id' => $header->id, 'tahun' => $header->tahun, 'status_alur' => $header->status_alur, 'expected_versi' => $header->versi,
                'versi_pengajuan' => $this->ringkas($versi) + ['pengaju' => ($beku['pic']['id'] ?? null) === $versi->diajukan_by
                    ? $this->identitas($beku['pic']) : ['id' => $versi->diajukan_by, 'nama' => User::whereKey($versi->diajukan_by)->value('nama') ?? 'Pengaju versi']],
                // Satuan dan desimal tampilan dari jadwal_snapshot versi, sama dengan halaman RA; bukan master berjalan.
                'indikator' => ['kode' => $beku['indikator']['kode'] ?? '—', 'nama' => $beku['indikator']['nama'] ?? 'Konteks versi belum lengkap',
                    'satuan' => (string) $versi->jadwalSnapshot->satuan, 'desimal_tampilan' => (int) $versi->jadwalSnapshot->desimal_tampilan],
                'unit_kerja' => $this->identitas($beku['unit_kerja'] ?? null), 'pic' => $this->identitas($beku['pic'] ?? null),
                'uraian' => $beku['uraian'] ?? null, 'alasan_deviasi_pk' => $beku['alasan_deviasi_pk'] ?? null,
                'target_periode' => array_map(fn (array $baris): array => [
                    'periode_id' => $baris['periode_id'] ?? '', 'periode_nama' => $baris['periode_nama'] ?? 'Konteks versi belum lengkap',
                    'periode_urutan' => $baris['periode_urutan'] ?? 0, 'nilai' => $this->nilai($baris['nilai'] ?? null),
                    'status_perhitungan' => $baris['status_perhitungan'] ?? null,
                    'keterangan' => is_string($baris['keterangan'] ?? null) ? $baris['keterangan'] : null,
                    'komponen' => array_map(fn (array $k): array => ['komponen_id' => $k['komponen_id'] ?? '', 'kode' => $k['kode'] ?? '—',
                        'label' => $k['label'] ?? 'Konteks versi belum lengkap', 'nilai' => $this->nilai($k['nilai'] ?? null),
                        'keterangan' => is_string($k['keterangan'] ?? null) ? $k['keterangan'] : null], $this->baris($baris['komponen'] ?? [])),
                ], $this->baris($beku['target_periode'] ?? [])),
                // Klaim tahap RA yang dibekukan saat pengajuan (Data Model §2.34); null untuk versi sebelum klaim dibekukan atau tanpa izin baca kegiatan.
                'klaim' => $kegiatanBoleh && is_array($beku['klaim'] ?? null) ? array_map(fn (array $k): array => [
                    'id' => $k['id'] ?? '', 'periode_nama' => $namaPeriode[$k['periode_id'] ?? ''] ?? 'Periode tidak tersedia',
                    'nama' => is_array($k['kegiatan'] ?? null) ? ($k['kegiatan']['nama'] ?? 'Konteks versi belum lengkap') : 'Konteks versi belum lengkap',
                    'status' => is_array($k['kegiatan'] ?? null) ? ($k['kegiatan']['status'] ?? null) : null,
                    'anggaran' => is_array($k['kegiatan'] ?? null) && isset($k['kegiatan']['anggaran']) ? (string) $k['kegiatan']['anggaran'] : null,
                    'komponen' => isset($k['komponen_id']) ? ($labelKomponen[(string) $k['komponen_id']] ?? 'Komponen tidak tersedia') : null,
                    'arah_dampak' => $k['arah_dampak'] ?? null, 'catatan' => is_string($k['catatan'] ?? null) ? $k['catatan'] : null,
                ], $this->baris($beku['klaim'])) : null,
                // Total beku saat pengajuan (Q42 §42.2 butir 6); null untuk versi sebelum total dibekukan atau tanpa izin baca kegiatan.
                'anggaran_kegiatan' => $kegiatanBoleh && is_array($beku['anggaran_kegiatan'] ?? null) ? $beku['anggaran_kegiatan'] : null,
                'bukti_dukungs' => $buktiBoleh ? array_map(fn (array $b): array => [
                    'id' => $b['id'] ?? '', 'mode' => $b['mode'] ?? '', 'nama_asli' => $b['nama_asli'] ?? null,
                    'tautan' => $b['tautan'] ?? null, 'isi_teks' => $b['isi_teks'] ?? null,
                    'download_url' => $this->unduhanBukti($header, $versi, $b, $buktiBerlaku),
                ], array_values(array_filter($bukti, fn (array $b): bool => in_array($b['mode'] ?? null, ['file', 'tautan', 'teks'], true)))) : [],
                'can' => ['verifikasi' => $policy->verifikasi($actor, $header)->allowed() && $errors['verifikasi'] === [],
                    'kembalikan' => $policy->kembalikan($actor, $header)->allowed() && $errors['kembalikan'] === [],
                    'sahkan' => $policy->sahkan($actor, $header)->allowed() && $errors['sahkan'] === [],
                    'buka_kembali' => $policy->bukaKembali($actor, $header)->allowed() && $errors['buka_kembali'] === [], 'evidence' => $buktiBoleh, 'kegiatan' => $kegiatanBoleh],
                'businessErrors' => ['verifikasi' => $errors['verifikasi'], 'kembalikan' => $errors['kembalikan'], 'sahkan' => $errors['sahkan'], 'buka_kembali' => $errors['buka_kembali']],
                // Penanda self-approval wajib terlihat, bukan hanya diaudit:
                // pengesah sama dengan pengaju pada jalur Perencanaan (Q41 §41.3 butir 2).
                'pengesahan' => $versi->disahkan_by === null ? null : ['oleh' => User::whereKey($versi->disahkan_by)->value('nama') ?? 'Pengesah versi',
                    'pada' => $versi->disahkan_at?->toIso8601String(),
                    'self_approval' => $versi->jalur_pengajuan === 'perencanaan' && $versi->disahkan_by === $versi->diajukan_by],
                'histori' => $histori->getCollection()->map(fn (RencanaAksiVersi $v): array => $this->ringkas($v))->all(),
                'dibuka_kembali' => $dibukaKembali,
                'pagination' => ['current_page' => $histori->currentPage(), 'last_page' => $histori->lastPage(), 'total' => $histori->total(),
                    'prev_page_url' => $histori->previousPageUrl(), 'next_page_url' => $histori->nextPageUrl()],
            ];
        });
    }

    /** @return array<string, mixed> */
    private function ringkas(RencanaAksiVersi $versi): array
    {
        return ['id' => $versi->id, 'nomor' => $versi->nomor, 'diajukan_at' => $versi->diajukan_at?->toIso8601String(), 'jalur_pengajuan' => $versi->jalur_pengajuan];
    }

    /** @return array{id: string, nama: string}|null */
    private function identitas(mixed $data): ?array
    {
        return is_array($data) && isset($data['id'], $data['nama']) ? ['id' => $data['id'], 'nama' => $data['nama']] : null;
    }

    private function nilai(mixed $nilai): ?string
    {
        return is_scalar($nilai) ? (string) $nilai : null;
    }

    /** @return list<array<string, mixed>> */
    private function baris(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    /**
     * Tautan unduh bukti versi: baris current yang masih berlaku selalu
     * diberi tautan (perilaku lama); baris historis yang current-nya sudah
     * tidak berlaku (diganti/dihapus setelah buka-kembali) hanya diberi
     * tautan untuk versi resmi (`disahkan_at` terisi) selama file fisik
     * beku versinya masih ada. Bukti yang dihapus pada versi tak disahkan
     * tetap tanpa tautan; file fisik yang hilang tetap tanpa tautan.
     *
     * @param  array<string, mixed>  $b
     * @param  list<string>  $buktiBerlaku
     */
    private function unduhanBukti(RencanaAksi $header, RencanaAksiVersi $versi, array $b, array $buktiBerlaku): ?string
    {
        if (($b['mode'] ?? '') !== 'file' || ! is_string($b['id'] ?? null) || ! Str::isUuid($b['id'])) {
            return null;
        }
        if (in_array($b['id'], $buktiBerlaku, true)) {
            return route('rencana-aksi.versi.bukti.download', [$header->id, $versi->id, $b['id']]);
        }
        if ($versi->disahkan_at === null) {
            return null;
        }
        $jalurBeku = $b['path'] ?? null;
        if (! is_string($jalurBeku) || $jalurBeku === '' || ! Storage::disk('local')->exists($jalurBeku)) {
            return null;
        }

        return route('rencana-aksi.versi.bukti.download', [$header->id, $versi->id, $b['id']]);
    }
}
