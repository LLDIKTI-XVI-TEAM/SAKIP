<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\Pengaturan;
use App\Models\RenstraPk;
use App\Services\AuditLogger;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! ($user?->can('create', RenstraPk::class) ?? false)) {
            return false;
        }

        $lampiran = $this->input('lampiran');
        if (! empty($lampiran) && is_array($lampiran)) {
            return $user->can('uploadBerkas', RenstraPk::class);
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        if ($user) {
            $resolver = app(PermissionResolver::class);
            $createDecision = $resolver->resolve($user, PermissionCodes::PK_CREATE);

            $lampiran = $this->input('lampiran');
            $uploadDecision = null;
            if (! empty($lampiran) && is_array($lampiran)) {
                $uploadDecision = $resolver->resolve($user, PermissionCodes::BERKAS_UPLOAD);
            }

            $primaryDecision = ! $createDecision->allowed ? $createDecision : ($uploadDecision ?? $createDecision);
            $rawAlasan = $this->input('alasan');
            $alasan = $this->sanitizeAlasan($rawAlasan);

            $nilaiBaru = array_filter([
                'renstra_id' => $this->input('renstra_id'),
                'tahun' => $this->input('tahun'),
                'nomor_pk' => $this->input('nomor_pk'),
                'tanggal_pk' => $this->input('tanggal_pk'),
                'alasan_penolakan' => ! $createDecision->allowed ? 'pk_create_denied' : 'berkas_upload_denied',
            ], fn ($val) => $val !== null);

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra_pk.buat_ditolak',
                objekTipe: 'renstra_pk',
                objekId: (string) Str::uuid(),
                nilaiBaru: $nilaiBaru,
                alasan: $alasan,
                dasarIzin: $primaryDecision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    protected function sanitizeAlasan(mixed $rawAlasan): ?string
    {
        $clean = PerjanjianKinerjaSupport::sanitizeAlasan($rawAlasan);

        return $clean !== '' ? $clean : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
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
            'renstra_id' => ['required', 'uuid', 'exists:renstras,id'],
            'tahun' => ['required', 'integer'],
            'nomor_pk' => ['required', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            'lampiran' => ['nullable', 'array'],
            'lampiran.*.mode' => ['required_with:lampiran', Rule::in(['file', 'tautan', 'teks'])],
            'lampiran.*.file' => $fileRules,
            'lampiran.*.tautan' => ['exclude_unless:lampiran.*.mode,tautan', 'required', 'string', 'url:http,https', 'max:2048'],
            'lampiran.*.isi_teks' => ['exclude_unless:lampiran.*.mode,teks', 'required', 'string', 'max:10000'],
            'lampiran.*.nama_asli' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function ($validator): void {
            $lampiran = $this->input('lampiran', []);
            if (! is_array($lampiran)) {
                return;
            }

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
                    if (! $isUploadActive) {
                        $validator->errors()->add("lampiran.{$index}.file", 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.');
                    } elseif (! $this->hasFile("lampiran.{$index}.file")) {
                        $validator->errors()->add("lampiran.{$index}.file", 'Pilih file yang akan dilampirkan.');
                    } else {
                        $file = $this->file("lampiran.{$index}.file");
                        if ($file instanceof UploadedFile && mb_strlen($file->getClientOriginalName()) > 255) {
                            $validator->errors()->add("lampiran.{$index}.file", 'Nama file lampiran tidak boleh melebihi 255 karakter.');
                        }
                    }
                }
            }
        }];
    }
}
