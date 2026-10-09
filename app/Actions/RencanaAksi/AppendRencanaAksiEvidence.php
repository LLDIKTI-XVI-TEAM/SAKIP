<?php

namespace App\Actions\RencanaAksi;

use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AppendRencanaAksiEvidence
{
    public function __construct(
        protected EvaluateRencanaAksiEvidence $evaluator,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Menyimpan bukti dukung baru (file/tautan/teks) pada Rencana Aksi.
     * Mendukung penggantian pendahulu (replacement chain) dan pencatatan audit tersanitasi.
     *
     * @param  array<string, mixed>  $data
     * @throws ValidationException
     */
    public function handle(RencanaAksi $rencanaAksi, array $data, User $actor): Berkas
    {
        if ($rencanaAksi->isDisahkan()) {
            throw ValidationException::withMessages([
                'rencana_aksi' => 'Bukti dukung tidak dapat ditambahkan karena Rencana Aksi telah disahkan.',
            ]);
        }

        $mode = $data['mode'] ?? null;
        if (! in_array($mode, ['file', 'tautan', 'teks'], true)) {
            throw ValidationException::withMessages([
                'mode' => 'Mode bukti dukung tidak valid. Pilihan yang tersedia: file, tautan, teks.',
            ]);
        }

        $requirement = null;
        if (! empty($data['jenis_berkas_id'])) {
            /** @var JenisBerkas|null $requirement */
            $requirement = JenisBerkas::where('id', $data['jenis_berkas_id'])
                ->where('aktif', true)
                ->first();

            if (! $requirement) {
                throw ValidationException::withMessages([
                    'jenis_berkas_id' => 'Persyaratan bukti dukung tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            if ($requirement->tahap !== 'rencana_aksi') {
                throw ValidationException::withMessages([
                    'jenis_berkas_id' => 'Persyaratan berkas bukan merupakan persyaratan tahap Rencana Aksi.',
                ]);
            }

            if ($requirement->indikator_id !== null && $requirement->indikator_id !== $rencanaAksi->indikator_id) {
                throw ValidationException::withMessages([
                    'jenis_berkas_id' => 'Persyaratan berkas tidak sesuai dengan indikator kinerja Rencana Aksi.',
                ]);
            }

            if (! (bool) $requirement->{'izinkan_'.$mode}) {
                throw ValidationException::withMessages([
                    'mode' => "Mode {$mode} tidak diizinkan pada persyaratan {$requirement->nama}.",
                ]);
            }
        }

        $predecessor = null;
        $alasanKoreksi = null;
        if (! empty($data['menggantikan_id'])) {
            /** @var Berkas|null $predecessor */
            $predecessor = Berkas::where('id', $data['menggantikan_id'])
                ->where('berkasable_type', 'rencana_aksi')
                ->where('berkasable_id', $rencanaAksi->id)
                ->whereNull('dihapus_pada')
                ->first();

            if (! $predecessor) {
                throw ValidationException::withMessages([
                    'menggantikan_id' => 'Bukti pendahulu yang akan digantikan tidak ditemukan pada Rencana Aksi ini.',
                ]);
            }

            if ($requirement && $predecessor->jenis_berkas_id !== $requirement->id) {
                throw ValidationException::withMessages([
                    'menggantikan_id' => 'Bukti pendahulu tidak cocok dengan persyaratan bukti dukung yang dipilih.',
                ]);
            }

            $alreadyReplaced = Berkas::where('menggantikan_id', $predecessor->id)
                ->where('berkasable_type', 'rencana_aksi')
                ->where('berkasable_id', $rencanaAksi->id)
                ->whereNull('dihapus_pada')
                ->exists();

            if ($alreadyReplaced) {
                throw ValidationException::withMessages([
                    'menggantikan_id' => 'Bukti pendahulu sudah pernah digantikan oleh bukti lain yang masih aktif.',
                ]);
            }

            $alasanKoreksi = trim((string) ($data['alasan_koreksi'] ?? ''));
            if ($alasanKoreksi === '') {
                throw ValidationException::withMessages([
                    'alasan_koreksi' => 'Alasan koreksi wajib diisi saat menggantikan bukti sebelumnya.',
                ]);
            }
        }

        $fileAttributes = [];
        if ($mode === 'file') {
            $settings = $this->evaluator->settings();
            if (! $settings['unggahan_aktif']) {
                throw ValidationException::withMessages([
                    'file' => 'Unggahan file sedang dinonaktifkan pada setelan aplikasi.',
                ]);
            }

            $maxKb = $requirement?->ukuran_maks_kb ?? $settings['ukuran_maks_kb'];
            $formats = $requirement?->format_diizinkan ?: $settings['format_diizinkan'];

            Validator::make($data, [
                'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.$formats],
            ], [
                'file.required' => 'Berkas bukti dukung wajib diunggah.',
                'file.file' => 'Berkas bukti dukung tidak valid.',
                'file.max' => "Ukuran berkas melebihi batas maksimum {$maxKb} KB.",
                'file.mimes' => "Format berkas harus berupa salah satu dari: {$formats}.",
            ])->validate();

            $uploadedFile = $data['file'];
            $storedPath = $uploadedFile->store('berkas', 'local');
            if (! is_string($storedPath)) {
                throw new \RuntimeException('Gagal menyimpan file ke penyimpanan privat.');
            }

            $fileAttributes = [
                'nama_asli' => $uploadedFile->getClientOriginalName(),
                'path' => $storedPath,
                'mime' => $uploadedFile->getMimeType(),
                'ukuran_bytes' => $uploadedFile->getSize(),
            ];
        } elseif ($mode === 'tautan') {
            Validator::make($data, ['tautan' => ['required', 'url', 'max:2048']])->validate();
            $fileAttributes['tautan'] = $data['tautan'];
        } else {
            Validator::make($data, ['isi_teks' => ['required', 'string', 'max:65535']])->validate();
            $fileAttributes['isi_teks'] = $data['isi_teks'];
        }

        return DB::transaction(function () use (
            $rencanaAksi,
            $requirement,
            $predecessor,
            $alasanKoreksi,
            $mode,
            $fileAttributes,
            $actor
        ): Berkas {
            /** @var RencanaAksi $lockedRa */
            $lockedRa = RencanaAksi::where('id', $rencanaAksi->id)->lockForUpdate()->firstOrFail();

            if ($lockedRa->isDisahkan()) {
                throw ValidationException::withMessages([
                    'rencana_aksi' => 'Bukti dukung tidak dapat ditambahkan karena Rencana Aksi telah disahkan.',
                ]);
            }

            $berkas = Berkas::create([
                'jenis_berkas_id' => $requirement?->id,
                'berkasable_type' => 'rencana_aksi',
                'berkasable_id' => $lockedRa->id,
                'menggantikan_id' => $predecessor?->id,
                'alasan_koreksi' => $alasanKoreksi,
                'mode' => $mode,
                'nama_asli' => $fileAttributes['nama_asli'] ?? null,
                'path' => $fileAttributes['path'] ?? null,
                'mime' => $fileAttributes['mime'] ?? null,
                'ukuran_bytes' => $fileAttributes['ukuran_bytes'] ?? null,
                'tautan' => $fileAttributes['tautan'] ?? null,
                'isi_teks' => $fileAttributes['isi_teks'] ?? null,
                'uploaded_by' => $actor->id,
            ]);

            $auditNilaiBaru = [
                'mode' => $mode,
                'jenis_berkas_id' => $requirement?->id,
                'rencana_aksi_id' => $lockedRa->id,
            ];

            if ($mode === 'file') {
                $auditNilaiBaru['nama_asli'] = $berkas->nama_asli;
                $auditNilaiBaru['mime'] = $berkas->mime;
                $auditNilaiBaru['ukuran_bytes'] = $berkas->ukuran_bytes;
            } elseif ($mode === 'tautan') {
                $auditNilaiBaru['tautan'] = $berkas->tautan;
            } else {
                $auditNilaiBaru['panjang_karakter'] = mb_strlen((string) $berkas->isi_teks);
            }

            if ($predecessor) {
                $auditNilaiBaru['menggantikan_id'] = $predecessor->id;
                $auditNilaiBaru['alasan_koreksi'] = $alasanKoreksi;
            }

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.unggah',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: null,
                nilaiBaru: $auditNilaiBaru,
                alasan: $predecessor ? $alasanKoreksi : 'Pemenuhan bukti dukung Rencana Aksi.',
            );

            return $berkas;
        });
    }
}
