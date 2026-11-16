<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pengajuan rencana aksi hanya menerima token `expected_versi`; isi versi
 * beku (target, bukti, provenance) dibentuk server dari state terkunci, jadi
 * seluruh field yang ditentukan server dilarang dikirim klien.
 */
class AjukanRencanaAksiRequest extends FormRequest
{
    private ?RencanaAksi $header = null;

    private ?PermissionDecision $keputusan = null;

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $id = $this->route('rencanaAksi');
        if (! is_string($id)) {
            return false;
        }

        // Lookup mendahului Gate agar UUID asing selalu 404, baik dengan
        // maupun tanpa izin (cermin `SimpanTargetPeriodeRequest`).
        $header = RencanaAksi::whereKey($id)->first();
        if (! $header instanceof RencanaAksi) {
            abort(404);
        }
        $this->header = $header;

        // Satu keputusan izin dipakai untuk otorisasi sekaligus dasar audit
        // penolakan; tanpa method Policy karena Action membutuhkan keputusan
        // (`PermissionDecision`) untuk jendela dan provenance, bukan `Response`.
        $this->keputusan = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENCANA_AKSI_AJUKAN, (string) $header->unit_id);

        return $this->keputusan->allowed;
    }

    /**
     * Percobaan yang ditolak otorisasi dicatat di sini dengan keputusan yang
     * sama dengan `authorize()`. Penolakan di dalam transaksi
     * `AjukanRencanaAksi` diaudit Action itu, sehingga setiap percobaan
     * tercatat tepat sekali.
     */
    protected function failedAuthorization(): void
    {
        $user = $this->user();
        if ($user instanceof User && $this->header instanceof RencanaAksi && $this->keputusan instanceof PermissionDecision) {
            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'rencana_aksi.ajukan_ditolak',
                objekTipe: 'rencana_aksi',
                objekId: (string) $this->header->id,
                nilaiLama: $this->header->withoutRelations()->toArray(),
                alasan: AlasanAudit::sanitasi(null, 'Percobaan pengajuan rencana aksi ditolak oleh sistem otorisasi.'),
                dasarIzin: $this->keputusan->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_versi' => ['required', 'integer', 'min:1'],
            'status_alur' => ['prohibited'],
            'versi' => ['prohibited'],
            'snapshot_draf_id' => ['prohibited'],
            'unit_id' => ['prohibited'],
            'indikator_id' => ['prohibited'],
            'tahun' => ['prohibited'],
            'jadwal_tahunan_id' => ['prohibited'],
            'penanggung_jawab_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'disahkan_at' => ['prohibited'],
            'disahkan_by' => ['prohibited'],
            'diajukan_by' => ['prohibited'],
            'jalur_pengajuan' => ['prohibited'],
            'dasar_izin_pengajuan' => ['prohibited'],
            'jadwal_snapshot_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute tidak memenuhi nilai minimum.',
            'prohibited' => 'Kolom :attribute ditentukan server.',
        ];
    }
}
