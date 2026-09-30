<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use App\Models\UserPermissionGrant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateUnitRequest extends FormRequest
{
    private Unit $unit;

    private array $mutationData;

    public function authorize(): bool
    {
        // Lookup mendahului Gate agar urutan respons 404/403 tetap sama.
        $this->unit = Unit::findOrFail($this->route('id'));
        Gate::authorize('update', $this->unit);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => trim($this->input('nama'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        $fail('Nama unit organisasi tidak boleh kosong atau hanya berisi spasi.');

                        return;
                    }
                    if (Unit::where('id', '!=', $this->unit->id)->whereRaw('LOWER(nama) = ?', [mb_strtolower($trimmed)])->exists()) {
                        $fail('Nama unit organisasi sudah digunakan.');
                    }
                },
            ],
            'status' => [
                'required', 'in:aktif,nonaktif',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'nonaktif' && UserPermissionGrant::where('unit_id', $this->unit->id)->exists()) {
                        $fail('Unit tidak dapat dinonaktifkan karena masih memiliki grant izin aktif. Cabut semua grant unit terlebih dahulu.');
                    }
                },
            ],
            'version_token' => ['nullable', 'string'],
            'versi_token' => ['nullable', 'string'],
            'expected_nama' => ['nullable', 'string'],
            'expected_status' => ['nullable', 'string', 'in:aktif,nonaktif'],
            'snapshot' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama unit organisasi wajib diisi.',
            'nama.max' => 'Nama unit organisasi maksimal 255 karakter.',
        ];
    }

    protected function passedValidation(): void
    {
        // Alias konkurensi sengaja memakai input() agar prioritas dan normalisasi tipe tetap terjaga.
        // Hanya field konkurensi dipetakan manual; nama/status mutasi tetap memakai validated().
        $versionToken = $this->input('version_token') ?? $this->input('versi_token')
            ?? $this->input('token') ?? $this->input('expected_state');
        $expectedNama = $this->input('expected_nama') ?? $this->input('initial_nama')
            ?? $this->input('snapshot.nama') ?? $this->input('expected_snapshot.nama');
        $expectedStatus = $this->input('expected_status') ?? $this->input('initial_status')
            ?? $this->input('snapshot.status') ?? $this->input('expected_snapshot.status');
        $snapshot = $this->input('snapshot') ?? $this->input('expected_snapshot');
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        // Pemeriksaan kelengkapan menerima alias JSON; pembandingan nilai tetap milik Unit terkunci.
        $completeNama = $expectedNama;
        $completeStatus = $expectedStatus;
        if (is_array($snapshot)) {
            if (! $completeNama && isset($snapshot['nama']) && is_string($snapshot['nama'])) {
                $completeNama = $snapshot['nama'];
            }
            if (! $completeStatus && isset($snapshot['status']) && is_string($snapshot['status'])) {
                $completeStatus = $snapshot['status'];
            }
        }
        $hasToken = is_string($versionToken) && trim($versionToken) !== '';
        $hasSnapshot = is_string($completeNama) && trim($completeNama) !== ''
            && is_string($completeStatus) && trim($completeStatus) !== '';
        if (! $hasToken && ! $hasSnapshot) {
            throw ValidationException::withMessages([
                'version_token' => 'Pembaruan unit organisasi mewajibkan token versi yang valid atau snapshot lengkap berisi nama dan status awal.',
            ]);
        }

        $this->mutationData = [
            'nama' => $this->validated('nama'),
            'status' => $this->validated('status'),
            'version_token' => is_string($versionToken) ? $versionToken : null,
            'expected_nama' => is_string($expectedNama) ? $expectedNama : null,
            'expected_status' => is_string($expectedStatus) ? $expectedStatus : null,
            'snapshot' => is_array($snapshot) ? $snapshot : null,
        ];
    }

    /** @return array{nama:string,status:'aktif'|'nonaktif',version_token:?string,expected_nama:?string,expected_status:?string,snapshot:?array{nama?:mixed,status?:mixed}} */
    public function mutationData(): array
    {
        return $this->mutationData;
    }
}
