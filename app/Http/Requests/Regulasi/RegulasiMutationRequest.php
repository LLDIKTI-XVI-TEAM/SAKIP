<?php

namespace App\Http\Requests\Regulasi;

use App\Models\Pengaturan;
use App\Models\Regulasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class RegulasiMutationRequest extends FormRequest
{
    protected ?bool $isUploadActiveCached = null;

    /**
     * @return array<string, mixed>
     */
    protected function mutationRules(bool $requireReason = false, bool $requireVersion = false): array
    {
        $routeRegulasi = $this->route('regulasi');
        $regulasiId = $routeRegulasi instanceof Regulasi ? $routeRegulasi->id : null;

        $uniqueNomor = Rule::unique('regulasi', 'nomor')
            ->where(fn ($query) => $query
                ->where('jenis', $this->input('jenis'))
                ->where('tahun', $this->input('tahun')));

        if ($regulasiId !== null) {
            $uniqueNomor->ignore($regulasiId);
        }

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
            'jenis' => ['required', Rule::in(['kepmen', 'permen', 'perpres', 'keputusan_lainnya'])],
            'nomor' => ['required', 'string', 'max:120', $uniqueNomor],
            'tahun' => ['required', 'integer', 'between:1800,'.(now()->year + 1)],
            'tentang' => ['required', 'string', 'max:5000'],
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'tautan_sumber' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'aktif' => ['required', 'boolean'],
            'versi' => $requireVersion
                ? ['required', 'integer', 'min:1']
                : ['prohibited'],
            'alasan' => $requireReason
                ? ['required', 'string', 'min:10', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
            'lampiran' => ['sometimes', 'array'],
            'lampiran.*.mode' => ['required', Rule::in(['file', 'tautan', 'teks'])],
            'lampiran.*.file' => $fileRules,
            'lampiran.*.tautan' => ['exclude_unless:lampiran.*.mode,tautan', 'required', 'string', 'url:http,https', 'max:2048'],
            'lampiran.*.isi_teks' => ['exclude_unless:lampiran.*.mode,teks', 'required', 'string', 'max:10000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $lampiran = $this->input('lampiran', []);

            if (! is_array($lampiran)) {
                return;
            }

            $isUploadActive = $this->isUploadActiveCached
                ?? filter_var(Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai') ?? true, FILTER_VALIDATE_BOOLEAN);

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
                    }
                }

                if ($mode === 'tautan' && blank($item['tautan'] ?? null)) {
                    $validator->errors()->add("lampiran.{$index}.tautan", 'Tautan lampiran wajib diisi.');
                }

                if ($mode === 'teks' && blank($item['isi_teks'] ?? null)) {
                    $validator->errors()->add("lampiran.{$index}.isi_teks", 'Keterangan teks wajib diisi.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'jenis' => 'jenis regulasi',
            'nomor' => 'nomor regulasi',
            'tahun' => 'tahun regulasi',
            'tentang' => 'pokok pengaturan',
            'tanggal' => 'tanggal penetapan',
            'tautan_sumber' => 'tautan sumber',
            'alasan' => 'alasan audit',
            'lampiran.*.file' => 'file lampiran',
            'lampiran.*.tautan' => 'tautan lampiran',
            'lampiran.*.isi_teks' => 'keterangan lampiran',
        ];
    }
}
