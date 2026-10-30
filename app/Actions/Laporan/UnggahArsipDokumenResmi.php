<?php

namespace App\Actions\Laporan;

use App\Models\Berkas;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Laporan\ArsipDokumenResmi;
use App\Support\AlasanAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UnggahArsipDokumenResmi
{
    public function __construct(private readonly ArsipDokumenResmi $arsip, private readonly ResolveLockedActor $aktor, private readonly AuditLogger $audit) {}

    /** Unggahan hanya menambah arsip; status tahun tidak membuka mutasi data kinerja. */
    public function handle(User $actor, array $data): Berkas
    {
        try {
            return DB::transaction(function () use ($actor, $data): Berkas {
                $locked = $this->aktor->handle($actor, 'laporan:unggah');
                if (! $locked['keputusan']->allowed) {
                    throw new AuthorizationException;
                }
                $data = Validator::make($data, ArsipDokumenResmi::rules(), ArsipDokumenResmi::messages())->validate();
                $induk = $this->arsip->induk($data['jadwal_id'], $data['periode_jadwal_id'] ?? null, true);
                $lama = null;
                if (! empty($data['menggantikan_id'])) {
                    $lama = $this->arsip->berkas($induk)->whereKey($data['menggantikan_id'])->lockForUpdate()->first();
                    if (! $lama || Berkas::where('menggantikan_id', $lama->id)->exists()) {
                        throw ValidationException::withMessages(['menggantikan_id' => 'Arsip sudah diganti atau bukan milik dokumen ini. Muat ulang daftar arsip.']);
                    }
                    if (trim($data['alasan_koreksi'] ?? '') === '') {
                        throw ValidationException::withMessages(['alasan_koreksi' => 'Alasan penggantian wajib diisi.']);
                    }
                }
                /** @var UploadedFile $file */
                $file = $data['file'];
                $path = $file->store('berkas/'.$this->arsip->tipe($induk).'/'.$induk->id, 'local');
                if (! is_string($path)) {
                    throw new \RuntimeException('Arsip gagal disimpan.');
                }
                try {
                    $berkas = new Berkas;
                    $berkas->forceFill(['berkasable_type' => $this->arsip->tipe($induk), 'berkasable_id' => $induk->id,
                        'jenis_berkas_id' => null, 'mode' => 'file', 'path' => $path, 'nama_asli' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(), 'ukuran_bytes' => $file->getSize(), 'uploaded_by' => $actor->id,
                        'keterangan' => $data['keterangan'] ?? null, 'menggantikan_id' => $lama?->id,
                        'alasan_koreksi' => $lama ? trim($data['alasan_koreksi']) : null])->save();
                    $this->audit->catat($actor, 'berkas.unggah', 'berkas', $berkas->id,
                        nilaiBaru: $berkas->only(['id', 'berkasable_type', 'berkasable_id', 'keterangan', 'menggantikan_id', 'alasan_koreksi', 'nama_asli', 'mime', 'ukuran_bytes']),
                        alasan: AlasanAudit::sanitasi($lama ? $data['alasan_koreksi'] : null, 'Mengunggah arsip dokumen resmi.'),
                        dasarIzin: $locked['keputusan']->toAuditBasis());
                } catch (Throwable $exception) {
                    // Hanya file baru yang belum berhasil dicatat yang dikompensasi.
                    Storage::disk('local')->delete($path);
                    throw $exception;
                }

                return $berkas;
            });
        } catch (AuthorizationException|ValidationException $exception) {
            $this->audit->catat($actor, 'berkas.unggah_ditolak', 'users', $actor->id, alasan: 'Unggah arsip ditolak karena izin atau validasi tidak terpenuhi.');
            throw $exception;
        }
    }
}
