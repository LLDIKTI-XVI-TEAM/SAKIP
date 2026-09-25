<?php

namespace App\Http\Requests\Renstra;

use App\Models\Pengaturan;
use App\Models\Renstra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class RenstraMutationRequest extends FormRequest
{
    protected ?bool $isUploadActiveCached = null;

    /**
     * @return array<string, mixed>
     */
    protected function mutationRules(bool $requireReason = false): array
    {
        $routeRenstra = $this->route('renstra');
        $renstraModel = null;
        if ($routeRenstra instanceof Renstra) {
            $renstraModel = $routeRenstra;
        } elseif (is_string($routeRenstra) && $routeRenstra !== '') {
            $renstraModel = Renstra::query()->find($routeRenstra);
        }

        $renstraId = $renstraModel?->id;
        $currentRegulasiId = $renstraModel?->regulasi_id;

        $uniqueKode = Rule::unique('renstras', 'kode');
        if ($renstraId !== null) {
            $uniqueKode->ignore($renstraId);
        }

        $regulasiExistsRule = Rule::exists('regulasi', 'id')->where(function ($query) use ($currentRegulasiId) {
            if ($currentRegulasiId !== null) {
                $query->where(function ($q) use ($currentRegulasiId) {
                    $q->where('aktif', true)->orWhere('id', $currentRegulasiId);
                });
            } else {
                $query->where('aktif', true);
            }
        });

        $settings = Pengaturan::where('grup', 'berkas')->pluck('nilai', 'kunci');
        $isUploadActive = filter_var($settings->get('berkas.unggahan_aktif', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->isUploadActiveCached = $isUploadActive;
        $maxKb = (int) $settings->get('berkas.ukuran_maks_kb', 10240);
        $formats = (string) $settings->get('berkas.format_diizinkan', 'pdf,docx,xlsx,jpg,jpeg,png');
        $formatsClean = str_replace(' ', '', $formats);

        $fileRules = $isUploadActive
            ? ['exclude_unless:lampiran.*.mode,file', 'required', 'file', 'mimes:'.$formatsClean, 'max:'.$maxKb]
            : ['exclude_unless:lampiran.*.mode,file'];

        return [
            'kode' => ['sometimes', 'nullable', 'string', 'max:50', $uniqueKode],
            'nama' => ['required', 'string', 'max:255'],
            'tahun_mulai' => ['required', 'integer', 'between:2000,2100'],
            'tahun_selesai' => ['sometimes', 'integer', 'between:2000,2100', 'gte:tahun_mulai'],
            'tahun_akhir' => ['sometimes', 'integer', 'between:2000,2100', 'gte:tahun_mulai'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'dasar_hukum' => ['nullable', 'string', 'max:5000'],
            'regulasi_id' => ['nullable', 'uuid', $regulasiExistsRule],
            'alasan' => $requireReason
                ? ['required', 'string', 'min:5', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
            'lampiran' => ['sometimes', 'array'],
            'lampiran.*.mode' => ['required', Rule::in(['file', 'tautan', 'teks'])],
            'lampiran.*.file' => $fileRules,
            'lampiran.*.tautan' => ['exclude_unless:lampiran.*.mode,tautan', 'required', 'url:http,https', 'max:2048'],
            'lampiran.*.isi_teks' => ['exclude_unless:lampiran.*.mode,teks', 'required', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tahunMulai = $this->input('tahun_mulai');
            $tahunSelesai = $this->input('tahun_selesai') ?? $this->input('tahun_akhir');

            if ($tahunMulai !== null && $tahunSelesai !== null && (int) $tahunSelesai < (int) $tahunMulai) {
                $pesan = 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.';
                $validator->errors()->add('tahun_akhir', $pesan);
                $validator->errors()->add('tahun_selesai', $pesan);
            }

            if ($this->isUploadActiveCached === false) {
                $lampiran = $this->input('lampiran', []);
                if (is_array($lampiran)) {
                    foreach ($lampiran as $index => $item) {
                        if (($item['mode'] ?? null) === 'file') {
                            $validator->errors()->add("lampiran.{$index}.file", 'Unggahan file sedang dinonaktifkan pada setelan aplikasi. Gunakan mode tautan atau teks.');
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama Renstra wajib diisi.',
            'tahun_mulai.required' => 'Tahun mulai wajib diisi.',
            'tahun_selesai.gte' => 'Tahun selesai harus lebih besar atau sama dengan tahun mulai.',
            'tahun_akhir.gte' => 'Tahun akhir harus lebih besar atau sama dengan tahun mulai.',
            'regulasi_id.exists' => 'Dasar aturan regulasi yang dipilih tidak ditemukan.',
            'alasan.required' => 'Alasan perubahan wajib diisi.',
            'alasan.min' => 'Alasan perubahan minimal 5 karakter.',
        ];
    }
}
