<?php

namespace App\Http\Requests\Laporan;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Laporan\ArsipDokumenResmi;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UnggahArsipDokumenResmiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PermissionResolver::class)->allows($this->user(), 'laporan:unggah');
    }

    public function rules(): array
    {
        return ArsipDokumenResmi::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ArsipDokumenResmi::messages();
    }

    /** Penolakan request berada di luar transaksi mutasi sehingga audit tidak ikut rollback. */
    protected function failedValidation(Validator $validator)
    {
        $this->catatPenolakan();
        parent::failedValidation($validator);
    }

    protected function failedAuthorization()
    {
        $this->catatPenolakan();
        parent::failedAuthorization();
    }

    private function catatPenolakan(): void
    {
        app(AuditLogger::class)->catat($this->user(), 'berkas.unggah_ditolak', 'users', $this->user()->id, alasan: 'Unggah arsip ditolak karena izin atau validasi tidak terpenuhi.');
    }
}
