<?php

namespace App\Http\Requests\Pengukuran;

use App\Support\AuditReason;
use Illuminate\Foundation\Http\FormRequest;

class ReviewPengukuranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Catatan menjadi riwayat dan alasan audit; teks kontrol ditolak di sini agar error jatuh ke field pengguna, bukan ke audit.
        return ['versi' => ['required', 'integer', 'min:1'], 'catatan' => [$this->routeIs('verifikasi.kembalikan') ? 'required' : 'nullable', 'string', 'max:10000', AuditReason::validate(...)],
            'status_alur' => ['prohibited'], 'status' => ['prohibited'], 'self_approval' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['catatan.required' => 'Alasan pengembalian wajib diisi.', 'catatan.string' => 'Alasan harus berupa teks.', 'catatan.max' => 'Alasan maksimal 10000 karakter.',
            'versi.required' => 'Versi data wajib dikirim. Muat ulang halaman.', 'versi.integer' => 'Versi data tidak sah.', 'versi.min' => 'Versi data tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server.'];
    }
}
