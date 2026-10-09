<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pemenuhan bukti rencana aksi tiga mode (PRD §18.4–18.5, Workflow §10.3).
 * Mode harus termasuk mode yang diizinkan persyaratan; batas ukuran/format
 * file mengikuti persyaratan lalu fallback kunci `berkas.*` pengaturan;
 * lampiran bebas (tanpa persyaratan) boleh mode apa pun.
 */
class TambahBuktiRencanaAksi
{
    public function __construct(
        private readonly GerbangBuktiRencanaAksi $gerbang,
        private readonly EvaluateEvidence $evidence,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Hasil validasi `StoreBuktiRencanaAksiRequest`.
     */
    public function handle(User $actor, string $id, array $data): BuktiDukung
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;
        /** @var string|null $path */
        $path = null;

        try {
            return DB::transaction(function () use ($actor, $id, $data, &$dasarIzin, &$path): BuktiDukung {
                ['pengunci' => $pengunci, 'header' => $header] = $this->gerbang->kunci($actor, $id, PermissionCodes::BERKAS_UPLOAD, $dasarIzin);
                $mode = (string) $data['mode'];

                $requirement = null;
                if (! empty($data['jenis_berkas_id'])) {
                    $requirement = JenisBerkas::whereKey($data['jenis_berkas_id'])->where('aktif', true)->where('tahap', 'rencana_aksi')
                        ->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $header->indikator_id))
                        ->lockForUpdate()->first();
                    if (! $requirement instanceof JenisBerkas) {
                        throw ValidationException::withMessages(['jenis_berkas_id' => 'Persyaratan bukti tidak berlaku untuk rencana aksi ini.']);
                    }
                    if (! $requirement->{'izinkan_'.$mode}) {
                        throw ValidationException::withMessages(['mode' => "Mode {$mode} tidak diizinkan pada persyaratan {$requirement->nama}."]);
                    }
                }

                $attributes = ['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $header->id, 'jenis_berkas_id' => $requirement?->id,
                    'mode' => $mode, 'uploaded_by' => $pengunci->id, 'created_at' => now()];

                if ($mode === 'file') {
                    $settings = $this->evidence->settings();
                    if (! $settings['unggahan_aktif']) {
                        throw ValidationException::withMessages(['file' => 'Unggahan file sedang dinonaktifkan. Mode tautan/teks tetap mengikuti persyaratannya.']);
                    }
                    $max = $requirement?->ukuran_maks_kb ?? $settings['ukuran_maks_kb'];
                    $formats = $requirement?->format_diizinkan ?: $settings['format_diizinkan'];
                    Validator::make($data, ['file' => ['required', 'file', "max:{$max}", "mimes:{$formats}"]], [
                        'file.max' => "Ukuran berkas melebihi batas {$max} KB.",
                        'file.mimes' => "Format berkas harus salah satu dari: {$formats}.",
                    ])->validate();

                    /** @var UploadedFile $file */
                    $file = $data['file'];
                    $stored = $file->store("berkas/rencana_aksi/{$header->id}", 'local');
                    if (! is_string($stored)) {
                        throw new \RuntimeException('Berkas bukti gagal disimpan ke penyimpanan privat.');
                    }
                    $path = $stored;
                    $attributes += ['nama_asli' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'ukuran_bytes' => $file->getSize()];
                } elseif ($mode === 'tautan') {
                    $attributes['tautan'] = $data['tautan'];
                } else {
                    $attributes['isi_teks'] = $data['isi_teks'];
                }

                $bukti = BuktiDukung::create($attributes);

                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'berkas.unggah',
                    objekTipe: 'berkas',
                    objekId: (string) $bukti->id,
                    nilaiBaru: [...GerbangBuktiRencanaAksi::metadataAudit($bukti), 'rencana_aksi_id' => (string) $header->id],
                    alasan: 'Pemenuhan bukti dukung rencana aksi.',
                    dasarIzin: $dasarIzin,
                );

                return $bukti;
            });
        } catch (Throwable $exception) {
            // Transaksi SQL tidak mengembalikan file yang sudah tersimpan di disk.
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)
                && RencanaAksi::whereKey($id)->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'berkas.unggah_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: $id,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Pemenuhan bukti dukung rencana aksi ditolak.'),
                    dasarIzin: $dasarIzin,
                );
            }

            throw $exception;
        }
    }
}
