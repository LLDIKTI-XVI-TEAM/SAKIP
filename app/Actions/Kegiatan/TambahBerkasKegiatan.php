<?php

namespace App\Actions\Kegiatan;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Services\Storage\UploadLimits;
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
 * Pemenuhan persyaratan tahap perencanaan kegiatan (mis. RAB) atau tahap
 * pelaksanaan (`kegiatan`, PRD §15.6) sebagai `berkas` berinduk kegiatan
 * (Q42 §42.3–§42.4). Persyaratan yang berlaku: global ditambah khusus
 * indikator RA pengklaim (tahap RA untuk perencanaan, seluruh klaim untuk
 * pelaksanaan, Q22). Berkas pengganti menjadi
 * baris baru dengan `menggantikan_id`, berkas lama tetap utuh (Q25).
 * Unggahan baru tetap diterima setelah RA diajukan (kegiatan bersama dapat
 * butuh persyaratan indikator lain), tetapi penggantian ditolak seperti
 * penghapusan agar berkas yang dibekukan versi RA tetap berlaku (Q36).
 */
class TambahBerkasKegiatan
{
    public function __construct(
        private readonly GerbangBerkasKegiatan $gerbang,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data Hasil validasi `StoreBerkasKegiatanRequest`. */
    public function handle(User $actor, string $kegiatanId, array $data): BuktiDukung
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;
        // Tahap dibaca sebelum lock hanya untuk memilih aturan kunci; persyaratan dicek ulang di bawah lock.
        $tahap = JenisBerkas::whereKey($data['jenis_berkas_id'])->value('tahap') === PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN
            ? PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN : PersyaratanPerencanaanKegiatan::TAHAP;
        $label = $tahap === PersyaratanPerencanaanKegiatan::TAHAP ? 'perencanaan' : 'pelaksanaan';

        try {
            return DB::transaction(function () use ($actor, $kegiatanId, $data, $tahap, $label, &$dasarIzin): BuktiDukung {
                ['pengunci' => $pengunci, 'kegiatan' => $kegiatan, 'pengklaim' => $pengklaim] = $this->gerbang->kunci($actor, $kegiatanId, PermissionCodes::BERKAS_UPLOAD, ! empty($data['menggantikan_id']), $dasarIzin, $tahap);
                $mode = (string) $data['mode'];
                $indikatorIds = $tahap === PersyaratanPerencanaanKegiatan::TAHAP
                    ? $pengklaim->map(fn (RencanaAksi $header): string => (string) $header->indikator_id)->unique()->values()->all()
                    : PersyaratanPerencanaanKegiatan::indikatorDiklaim((string) $kegiatan->id);
                $requirement = JenisBerkas::whereKey($data['jenis_berkas_id'])->where('aktif', true)->where('tahap', $tahap)
                    ->where(fn ($q) => $q->whereNull('indikator_id')->orWhereIn('indikator_id', $indikatorIds))->lockForUpdate()->first();
                if (! $requirement instanceof JenisBerkas) {
                    throw ValidationException::withMessages(['jenis_berkas_id' => 'Persyaratan tidak berlaku untuk kegiatan ini.']);
                }
                if (! $requirement->{'izinkan_'.$mode}) {
                    throw ValidationException::withMessages(['mode' => "Mode {$mode} tidak diizinkan pada persyaratan {$requirement->nama}."]);
                }
                $lama = null;
                $alasanKoreksi = null;
                if (! empty($data['menggantikan_id'])) {
                    $lama = PersyaratanPerencanaanKegiatan::buktiBerlaku((string) $kegiatan->id)->whereKey($data['menggantikan_id'])
                        ->where('jenis_berkas_id', $requirement->id)->lockForUpdate()->first()
                        ?? throw ValidationException::withMessages(['menggantikan_id' => 'Berkas yang akan diganti tidak ditemukan pada persyaratan ini.']);
                    // Q25: berkas pengganti wajib beralasan, sejalan dengan bukti pengukuran.
                    $alasanKoreksi = trim((string) ($data['alasan_koreksi'] ?? ''));
                    if ($alasanKoreksi === '') {
                        throw ValidationException::withMessages(['alasan_koreksi' => 'Alasan penggantian wajib diisi.']);
                    }
                }

                $attributes = ['berkasable_type' => 'kegiatan', 'berkasable_id' => $kegiatan->id, 'jenis_berkas_id' => $requirement->id,
                    'menggantikan_id' => $lama?->id, 'alasan_koreksi' => $alasanKoreksi,
                    'mode' => $mode, 'uploaded_by' => $pengunci->id, 'created_at' => now()];
                $path = null;
                if ($mode === 'file') {
                    $limits = UploadLimits::resolve($requirement?->ukuran_maks_kb, $requirement?->format_diizinkan);
                    $max = $limits['ukuran_maks_kb'];
                    $formats = $limits['format_diizinkan'];
                    Validator::make($data, ['file' => ['required', ...UploadLimits::fileRules($requirement?->ukuran_maks_kb, $requirement?->format_diizinkan)]], [
                        'file.max' => "Ukuran berkas melebihi batas {$max} KB.",
                        'file.mimes' => "Format berkas harus salah satu dari: {$formats}.",
                        'file.extensions' => "Ekstensi berkas harus salah satu dari: {$formats}.",
                    ])->validate();
                    /** @var UploadedFile $file */
                    $file = $data['file'];
                    $path = $file->store("berkas/kegiatan/{$kegiatan->id}", 'local');
                    if (! is_string($path)) {
                        throw new \RuntimeException('Berkas kegiatan gagal disimpan ke penyimpanan privat.');
                    }
                    $attributes += ['nama_asli' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'ukuran_bytes' => $file->getSize()];
                } elseif ($mode === 'tautan') {
                    $attributes['tautan'] = $data['tautan'];
                } else {
                    $attributes['isi_teks'] = $data['isi_teks'];
                }

                try {
                    $berkas = BuktiDukung::create($attributes);
                    $this->audit->catat(
                        actor: $pengunci,
                        tindakan: 'berkas.unggah',
                        objekTipe: 'berkas',
                        objekId: (string) $berkas->id,
                        nilaiLama: $lama ? [...GerbangBuktiRencanaAksi::metadataAudit($lama), 'id' => (string) $lama->id] : null,
                        nilaiBaru: [...GerbangBuktiRencanaAksi::metadataAudit($berkas), 'kegiatan_id' => (string) $kegiatan->id],
                        alasan: $lama ? AlasanAudit::sanitasi($alasanKoreksi, "Penggantian berkas {$label} kegiatan.") : "Pemenuhan berkas {$label} kegiatan.",
                        dasarIzin: $dasarIzin,
                    );
                } catch (Throwable $exception) {
                    // Gagal sebelum COMMIT pasti di-rollback, jadi file baru aman dihapus (Eng. Standards §7).
                    if (is_string($path)) {
                        Storage::disk('local')->delete($path);
                    }
                    throw $exception;
                }

                return $berkas;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && Kegiatan::whereKey($kegiatanId)->exists()) {
                $this->audit->catat(actor: $actor, tindakan: 'berkas.unggah_ditolak', objekTipe: 'kegiatan', objekId: $kegiatanId,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), "Pemenuhan berkas {$label} kegiatan ditolak."), dasarIzin: $dasarIzin);
            }

            throw $exception;
        }
    }
}
