<?php

namespace App\Actions\JenisBerkas;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Contoh berkas persyaratan tahap perencanaan kegiatan (Q42 §42.3 butir 2–4):
 * lampiran generik dari Perencanaan, bukan bukti. Tidak dinilai gerbang dan
 * tidak dibekukan ke versi; penggantian memakai `menggantikan_id` agar
 * riwayatnya tetap utuh. Batas file mengikuti batas teknis lampiran tanpa persyaratan.
 */
class UnggahContohBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly EvaluateEvidence $evidence,
    ) {}

    /**
     * @param  array{file: UploadedFile, menggantikan_id?: string|null, alasan_koreksi?: string|null}  $data
     */
    public function handle(User $actor, string $id, array $data): void
    {
        $denied = DB::transaction(function () use (&$actor, $id, $data): ?array {
            // Selaras dengan writer jenis berkas: pengguna, role aktif, permission, lalu objek.
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::where('kode', 'jenis_berkas:update')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:update');
            if (! $decision['allowed']) {
                return $decision;
            }

            $jenis = JenisBerkas::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($jenis->tahap !== 'kegiatan_perencanaan') {
                throw ValidationException::withMessages(['file' => 'Contoh berkas hanya tersedia untuk persyaratan tahap perencanaan kegiatan.']);
            }
            $lama = null;
            if (! empty($data['menggantikan_id'])) {
                $lama = BuktiDukung::where('berkasable_type', 'jenis_berkas')->where('berkasable_id', $jenis->id)
                    ->current()->whereKey($data['menggantikan_id'])->lockForUpdate()->first()
                    ?? throw ValidationException::withMessages(['menggantikan_id' => 'Contoh berkas yang akan diganti tidak ditemukan.']);
            }

            $settings = $this->evidence->settings();
            if (! $settings['unggahan_aktif']) {
                throw ValidationException::withMessages(['file' => 'Unggahan file sedang dinonaktifkan.']);
            }
            Validator::make($data, ['file' => ['required', 'file', "max:{$settings['ukuran_maks_kb']}", "mimes:{$settings['format_diizinkan']}"]], [
                'file.max' => "Ukuran berkas melebihi batas {$settings['ukuran_maks_kb']} KB.",
                'file.mimes' => "Format berkas harus salah satu dari: {$settings['format_diizinkan']}.",
            ])->validate();

            /** @var UploadedFile $file */
            $file = $data['file'];
            $path = $file->store("berkas/jenis_berkas/{$jenis->id}", 'local');
            if (! is_string($path)) {
                throw new \RuntimeException('Contoh berkas gagal disimpan ke penyimpanan privat.');
            }

            try {
                $contoh = BuktiDukung::create([
                    'berkasable_type' => 'jenis_berkas', 'berkasable_id' => $jenis->id, 'menggantikan_id' => $lama?->id,
                    'alasan_koreksi' => $lama ? ($data['alasan_koreksi'] ?? null) : null, 'mode' => 'file',
                    'nama_asli' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'ukuran_bytes' => $file->getSize(),
                    'uploaded_by' => $actor->id, 'created_at' => now(),
                ]);
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'berkas.unggah',
                    objekTipe: 'berkas',
                    objekId: (string) $contoh->id,
                    nilaiLama: $lama ? GerbangBuktiRencanaAksi::metadataAudit($lama) + ['id' => (string) $lama->id] : null,
                    nilaiBaru: [...GerbangBuktiRencanaAksi::metadataAudit($contoh), 'contoh_untuk_jenis_berkas_id' => (string) $jenis->id],
                    alasan: $lama ? ($data['alasan_koreksi'] ?? 'Penggantian contoh berkas persyaratan.') : 'Contoh berkas persyaratan.',
                    dasarIzin: $decision,
                );
            } catch (Throwable $exception) {
                // Gagal sebelum COMMIT pasti di-rollback, jadi file baru aman dihapus (Eng. Standards §7).
                Storage::disk('local')->delete($path);
                throw $exception;
            }

            return null;
        });

        if ($denied !== null) {
            $this->audit->catat(actor: $actor, tindakan: 'berkas.unggah_ditolak', objekTipe: 'jenis_berkas', objekId: $id,
                alasan: 'Percobaan unggah contoh berkas ditolak karena tidak memiliki izin.', dasarIzin: $denied);
            abort(403);
        }
    }
}
