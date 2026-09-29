<?php

namespace App\Services\PerjanjianKinerja;

use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class PerjanjianKinerjaSupport
{
    /**
     * Membersihkan string alasan audit dari byte NUL dan karakter kontrol ilegal.
     */
    public static function sanitizeAlasan(mixed $rawAlasan): string
    {
        if (! is_string($rawAlasan)) {
            return '';
        }

        // Pastikan encoding UTF-8 valid untuk mencegah SQLSTATE[22021]
        $clean = mb_convert_encoding($rawAlasan, 'UTF-8', 'UTF-8');

        // Hapus byte NUL untuk mencegah exception PostgreSQL SQLSTATE[22P05]
        $clean = str_replace("\0", '', $clean);

        // Hapus karakter kontrol yang tidak dapat dicetak, pertahankan newline dan tab
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean)
            ?? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean)
            ?? '';

        $clean = trim($clean);

        if ($clean === '') {
            return '';
        }

        return mb_substr($clean, 0, 1000, 'UTF-8');
    }

    /**
     * Membatasi dan membersihkan metadata audit penolakan agar aman, terprediksi, dan bounded.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function boundDeniedMetadata(array $input): array
    {
        $bounded = [];

        if (isset($input['renstra_id']) && is_string($input['renstra_id']) && Str::isUuid($input['renstra_id'])) {
            $bounded['renstra_id'] = $input['renstra_id'];
        }

        if (isset($input['tahun'])) {
            if (is_int($input['tahun']) || (is_string($input['tahun']) && ctype_digit($input['tahun']))) {
                $tahun = (int) $input['tahun'];
                if ($tahun >= 1900 && $tahun <= 2100) {
                    $bounded['tahun'] = $tahun;
                }
            }
        }

        if (isset($input['nomor_pk']) && (is_string($input['nomor_pk']) || is_numeric($input['nomor_pk']))) {
            $nomor = trim(str_replace("\0", '', (string) $input['nomor_pk']));
            if ($nomor !== '') {
                $bounded['nomor_pk'] = mb_substr($nomor, 0, 255, 'UTF-8');
            }
        }

        if (isset($input['tanggal_pk']) && is_string($input['tanggal_pk'])) {
            try {
                $bounded['tanggal_pk'] = Carbon::parse($input['tanggal_pk'])->format('Y-m-d');
            } catch (Throwable) {
                // Jangan simpan tanggal yang tidak valid
            }
        }

        if (isset($input['alasan_penolakan']) && is_string($input['alasan_penolakan'])) {
            $bounded['alasan_penolakan'] = mb_substr(trim($input['alasan_penolakan']), 0, 100, 'UTF-8');
        }

        return $bounded;
    }

    /**
     * Mengunci aktor, relasi peran, peran aktif, dan permission terkait secara deterministik
     * untuk mencegah race condition / TOCTOU pada mutasi wewenang concurrent.
     *
     * @param  list<string>  $permissionCodes
     */
    public static function lockActorAndPermissions(User $actor, array $permissionCodes): User
    {
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if ($lockedActor->status !== 'aktif') {
            throw new AuthorizationException('Pengguna tidak aktif.');
        }

        $roleIds = DB::table('user_roles')
            ->where('user_id', $lockedActor->id)
            ->lockForUpdate()
            ->pluck('role_id')
            ->all();

        if ($roleIds !== []) {
            Role::query()->whereIn('id', array_unique($roleIds))->orderBy('id')->sharedLock()->get();
        }

        if ($permissionCodes !== []) {
            Permission::query()->whereIn('kode', array_unique($permissionCodes))->orderBy('id')->sharedLock()->get();
        }

        return $lockedActor;
    }

    /**
     * Menghasilkan aturan validasi untuk lampiran dokumen Perjanjian Kinerja (mode file, tautan, teks).
     *
     * @return array<string, mixed>
     */
    public static function lampiranRules(): array
    {
        $settings = Pengaturan::where('grup', 'berkas')->pluck('nilai', 'kunci');
        $rawActive = $settings->get('berkas.unggahan_aktif');
        $isUploadActive = $rawActive === null ? true : filter_var($rawActive, FILTER_VALIDATE_BOOLEAN);
        $maxKb = (int) ($settings->get('berkas.ukuran_maks_kb') ?? 10240);
        $formats = (string) ($settings->get('berkas.format_diizinkan') ?? 'pdf,docx,xlsx,jpg,jpeg,png');
        $formatsClean = str_replace(' ', '', $formats);

        $fileRules = $isUploadActive
            ? ['exclude_unless:lampiran.*.mode,file', 'required', 'file', 'mimes:'.$formatsClean, 'max:'.$maxKb]
            : ['exclude_unless:lampiran.*.mode,file'];

        return [
            'lampiran' => ['nullable', 'array'],
            'lampiran.*.mode' => ['required_with:lampiran', Rule::in(['file', 'tautan', 'teks'])],
            'lampiran.*.file' => $fileRules,
            'lampiran.*.tautan' => ['exclude_unless:lampiran.*.mode,tautan', 'required', 'string', 'url:http,https', 'max:2048'],
            'lampiran.*.isi_teks' => ['exclude_unless:lampiran.*.mode,teks', 'required', 'string', 'max:10000'],
            'lampiran.*.nama_asli' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validasi lanjutan untuk upload lampiran berkas pada Validator.
     * Menerima array lampiran dan array resolved UploadedFile tanpa dependency ke FormRequest.
     *
     * @param  array<array-key, mixed>  $lampiran
     * @param  array<array-key, mixed>  $files
     */
    public static function validateLampiranItems(array $lampiran, array $files, Validator $validator): void
    {
        $isUploadActive = filter_var(
            Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        foreach ($lampiran as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $mode = $item['mode'] ?? null;
            if ($mode === 'file') {
                $file = $files[$index] ?? null;
                if (! $isUploadActive) {
                    $validator->errors()->add("lampiran.{$index}.file", 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.');
                } elseif (! ($file instanceof UploadedFile)) {
                    $validator->errors()->add("lampiran.{$index}.file", 'Pilih file yang akan dilampirkan.');
                } else {
                    if (mb_strlen($file->getClientOriginalName()) > 255) {
                        $validator->errors()->add("lampiran.{$index}.file", 'Nama file lampiran tidak boleh melebihi 255 karakter.');
                    }
                }
            }
        }
    }
}
