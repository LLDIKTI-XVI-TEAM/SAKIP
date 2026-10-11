<?php

namespace App\Actions\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Policies\RencanaAksiPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            $policy = app(RencanaAksiPolicy::class);
            $errors = $policy->businessErrors($actor, $header, $versi);
            if ($versi->id !== $terbaru?->id) {
                $errors['verifikasi'][] = $errors['kembalikan'][] = 'Versi historis hanya dapat dibaca; review memakai pengajuan terbaru.';
            }
            $buktiBoleh = $policy->viewEvidence($actor, $header);
            $beku = $versi->snapshot ?? [];
            $bukti = $this->baris($beku['bukti_dukungs'] ?? []);
            $buktiIds = array_values(array_filter(array_column($bukti, 'id'), fn ($id): bool => is_string($id) && Str::isUuid($id)));
            $buktiBerlaku = $buktiBoleh ? $header->buktiDukungs()->current()->whereIn('id', $buktiIds)->where('mode', 'file')->pluck('id')->all() : [];
            $histori = $header->versions()->select(['id', 'rencana_aksi_id', 'nomor', 'diajukan_at', 'jalur_pengajuan'])
                ->reorder('nomor', 'desc')->paginate(20, page: $query['page'] ?? 1)->withQueryString();

            return [
                'id' => $header->id, 'tahun' => $header->tahun, 'status_alur' => $header->status_alur, 'expected_versi' => $header->versi,
                'versi_pengajuan' => $this->ringkas($versi) + ['pengaju' => ($beku['pic']['id'] ?? null) === $versi->diajukan_by
                    ? $this->identitas($beku['pic']) : ['id' => $versi->diajukan_by, 'nama' => User::whereKey($versi->diajukan_by)->value('nama') ?? 'Pengaju versi']],
                'indikator' => ['kode' => $beku['indikator']['kode'] ?? '—', 'nama' => $beku['indikator']['nama'] ?? 'Konteks versi belum lengkap'],
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
                'bukti_dukungs' => $buktiBoleh ? array_map(fn (array $b): array => [
                    'id' => $b['id'] ?? '', 'mode' => $b['mode'] ?? '', 'nama_asli' => $b['nama_asli'] ?? null,
                    'tautan' => $b['tautan'] ?? null, 'isi_teks' => $b['isi_teks'] ?? null,
                    'download_url' => ($b['mode'] ?? '') === 'file' && in_array($b['id'] ?? null, $buktiBerlaku, true)
                        ? route('rencana-aksi.versi.bukti.download', [$header->id, $versi->id, $b['id']]) : null,
                ], array_values(array_filter($bukti, fn (array $b): bool => in_array($b['mode'] ?? null, ['file', 'tautan', 'teks'], true)))) : [],
                'can' => ['verifikasi' => $policy->verifikasi($actor, $header)->allowed() && $errors['verifikasi'] === [],
                    'kembalikan' => $policy->kembalikan($actor, $header)->allowed() && $errors['kembalikan'] === [], 'evidence' => $buktiBoleh],
                'businessErrors' => ['verifikasi' => $errors['verifikasi'], 'kembalikan' => $errors['kembalikan']],
                'histori' => $histori->getCollection()->map(fn (RencanaAksiVersi $v): array => $this->ringkas($v))->all(),
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
}
