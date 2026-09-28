<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\Pengaturan;
use App\Models\RenstraPk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');
        if (! ($pk instanceof RenstraPk)) {
            return false;
        }

        $user = $this->user();
        if (! ($user?->can('update', $pk) ?? false)) {
            return false;
        }

        $lampiran = $this->input('lampiran');
        if (! empty($lampiran) && is_array($lampiran)) {
            return $user->can('uploadBerkas', $pk);
        }

        return true;
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
            'nomor_pk' => ['required', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            'alasan' => ['required', 'string', 'min:3', 'max:1000'],
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
