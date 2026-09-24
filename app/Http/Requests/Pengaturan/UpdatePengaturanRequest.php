<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\Pengaturan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PengaturanService;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdatePengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        return app(PermissionResolver::class)->allows($user, PermissionCodes::PENGATURAN_UPDATE);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $decision = app(PermissionResolver::class)->decide($user, PermissionCodes::PENGATURAN_UPDATE);
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? mb_substr(trim($rawAlasan), 0, 255)
                : 'Percobaan pembaruan pengaturan sistem ditolak karena tidak memiliki izin.';

            $dotInput = Arr::dot($this->all());
            $targetKey = null;
            foreach (array_keys($dotInput) as $key) {
                if ($key !== 'alasan' && ! str_starts_with($key, 'expected_updated_at')) {
                    $targetKey = $key;
                    break;
                }
            }

            $objekId = $targetKey ? Pengaturan::query()->where('kunci', $targetKey)->value('id') : null;
            if (! $objekId) {
                $objekId = (string) Str::uuid();
            }

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'pengaturan.ubah_ditolak',
                objekTipe: 'pengaturan',
                objekId: (string) $objekId,
                alasan: $alasan,
                dasarIzin: $decision,
            );
        }

        parent::failedAuthorization();
    }

    protected function prepareForValidation(): void
    {
        $this->replace(Arr::undot($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'alasan' => ['required', 'string', 'min:5', 'max:255'],
            'expected_updated_at' => ['sometimes', 'nullable', 'array'],
        ];

        foreach (PengaturanService::WHITELIST as $kunci => $meta) {
            $rules[$kunci] = array_merge(['sometimes'], $meta['aturan']);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowedKeys = array_keys(PengaturanService::WHITELIST);
            $allInputDot = Arr::dot($this->all());

            // Validasi setiap token expected_updated_at dan batasi pada kunci whitelist
            $expectedInput = $this->input('expected_updated_at');
            if ($expectedInput !== null) {
                if (! is_array($expectedInput)) {
                    $validator->errors()->add('expected_updated_at', 'Format token konkurensi harus berupa array.');
                } else {
                    $expectedDot = Arr::dot($expectedInput);
                    foreach ($expectedDot as $key => $val) {
                        if (! in_array($key, $allowedKeys, true)) {
                            $validator->errors()->add(
                                "expected_updated_at.{$key}",
                                "Kunci token konkurensi '{$key}' tidak valid."
                            );

                            continue;
                        }

                        if ($val !== null) {
                            if (! is_string($val)) {
                                $validator->errors()->add(
                                    "expected_updated_at.{$key}",
                                    "Token konkurensi untuk '{$key}' harus berupa string tanggal."
                                );
                            } else {
                                try {
                                    Carbon::parse($val);
                                } catch (\Throwable) {
                                    $validator->errors()->add(
                                        "expected_updated_at.{$key}",
                                        "Token konkurensi untuk '{$key}' harus berformat tanggal/waktu yang valid."
                                    );
                                }
                            }
                        }
                    }
                }
            }

            // Validasi bahwa setiap kunci pengaturan existing yang diperbarui wajib memiliki token versi
            $inputKeys = array_keys($allInputDot);
            $dirtySettingKeys = array_values(array_filter($inputKeys, fn ($k) => ! str_starts_with($k, 'expected_updated_at') && $k !== 'alasan'));
            $validDirtyKeys = array_intersect($dirtySettingKeys, $allowedKeys);

            if ($validDirtyKeys !== []) {
                $existingKeys = Pengaturan::query()->whereIn('kunci', $validDirtyKeys)->pluck('kunci')->all();
                $expectedDot = is_array($expectedInput) ? Arr::dot($expectedInput) : [];

                foreach ($validDirtyKeys as $key) {
                    $tokenVal = $expectedDot[$key] ?? null;
                    $hasToken = is_string($tokenVal) && trim($tokenVal) !== '';
                    if (in_array($key, $existingKeys, true)) {
                        if (! $hasToken) {
                            $validator->errors()->add($key, "Token versi untuk pengaturan '{$key}' wajib disertakan.");
                            $validator->errors()->add("expected_updated_at.{$key}", "Token versi untuk pengaturan '{$key}' wajib disertakan.");
                        }
                    } else {
                        if ($hasToken) {
                            $validator->errors()->add($key, "Pengaturan '{$key}' belum tersimpan di basis data sehingga tidak memiliki token versi sebelumnya.");
                            $validator->errors()->add("expected_updated_at.{$key}", "Pengaturan '{$key}' belum tersimpan di basis data sehingga tidak memiliki token versi sebelumnya.");
                        }
                    }
                }
            }

            // Abaikan metadata penanganan konkurensi dari pengecekan whitelist pengaturan
            $checkedKeys = array_filter($inputKeys, fn ($k) => ! str_starts_with($k, 'expected_updated_at'));
            $allowedFormKeys = array_merge($allowedKeys, ['alasan']);
            $disallowed = array_diff($checkedKeys, $allowedFormKeys);
            foreach ($disallowed as $key) {
                $validator->errors()->add(
                    $key,
                    "Kunci pengaturan '{$key}' tidak diizinkan untuk diubah."
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pembaruan pengaturan wajib diisi untuk catatan audit.',
            'alasan.min' => 'Alasan pembaruan pengaturan minimal :min karakter.',
            'alasan.max' => 'Alasan pembaruan pengaturan maksimal :max karakter.',
            'instansi.nama.required' => 'Nama instansi wajib diisi.',
            'instansi.nama.max' => 'Nama instansi maksimal 255 karakter.',
            'instansi.surel.email' => 'Format surel instansi tidak valid.',
            'instansi.laman.url' => 'Format tautan laman resmi instansi harus berupa URL yang valid (cth: https://...).',
            'aplikasi.nama.required' => 'Nama aplikasi wajib diisi.',
            'aplikasi.label_unit.required' => 'Label unit kerja wajib diisi.',
            'tampilan.zona_waktu.required' => 'Zona waktu wajib dipilih.',
            'tampilan.zona_waktu.in' => 'Pilihan zona waktu tidak valid.',
            'tampilan.format_tanggal.required' => 'Format tanggal wajib dipilih.',
            'tampilan.format_tanggal.in' => 'Pilihan format tanggal tidak valid.',
            'tampilan.format_angka.required' => 'Format angka wajib dipilih.',
            'tampilan.format_angka.in' => 'Pilihan format angka tidak valid.',
        ];
    }
}
