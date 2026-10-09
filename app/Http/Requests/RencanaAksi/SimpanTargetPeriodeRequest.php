<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SimpanTargetPeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $id = $this->route('rencanaAksi');
        if (! is_string($id)) {
            return false;
        }

        $header = RencanaAksi::whereKey($id)->first();
        if (! $header instanceof RencanaAksi) {
            // Header tak ditemukan → 404 SEBELUM validasi
            // `exists`, cermin `PreviewTargetPeriodeRequest`.
            // Tanpa ini UUID asing + payload tak valid memberi 422 sedangkan
            // payload valid memberi 404 (oracle 422-vs-404), dan tanpa-izin
            // memberi oracle 403-vs-404. Lookup mendahului Gate agar urutan
            // respons 404/403 tetap sama. Murni `return false` ditolak: kasus
            // dengan-izin atas UUID asing wajib 404 (bukan 403).
            abort(404);
        }

        return Gate::allows('update', $header);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_versi' => ['required', 'integer', 'min:1'],
            // Token konkurensi snapshot dari IndexRencanaAksi
            // (identitas + nomor versi beku) WAJIB dikirim (`present`) pada
            // setiap penyimpanan. Null lolos validasi bentuk tetapi selalu
            // ditolak 409 oleh SimpanTargetPeriode, yang membandingkan token
            // dengan snapshot terbaru terkunci tanpa pengecualian.
            'expected_snapshot_id' => ['present', 'nullable', 'uuid', 'exists:jadwal_snapshot,id'],
            'expected_snapshot_versi' => ['present', 'nullable', 'integer', 'min:1'],
            'uraian' => ['nullable', 'string', 'max:10000'],
            'alasan_deviasi_pk' => ['nullable', 'string', 'max:10000'],
            // Batas domain = 50 komponen (ChangeIndicatorFormulaRequest)
            // × 12 periode bulanan = 600 sel. Angka 100 lama menolak matriks
            // sah (mis. 10×11=110) yang dikirim utuh oleh halaman.
            // 12 periode dikunci di StoreJadwalRequest (`periode`
            // max:12) sehingga 600 selalu cukup dari konfigurasi yang sah.
            'targets' => ['required', 'array', 'min:1', 'max:600'],
            'targets.*.periode_id' => ['required', 'uuid', 'exists:periode,id'],
            'targets.*.komponen_id' => ['nullable', 'uuid', 'exists:indikator_komponen,id'],
            'targets.*.nilai' => ['present', 'nullable', 'numeric', 'between:-999999999999999999,999999999999999999'],
            // Batas per sel; ukuran audit (seluruh matriks masuk lama+baru
            // tiap simpan) dibatasi oleh batas total matriks tersimpan di
            // SimpanTargetPeriode.
            'targets.*.keterangan' => ['nullable', 'string', 'max:1000'],
            // Jepit konteks ditulis server saja
            // (EnsureDraft saat buat, Simpan tiap simpan) — klien dilarang
            // mengirimnya agar tak dapat memalsukan rekonsiliasi/trigger.
            'snapshot_draf_id' => ['prohibited'],
            'status_alur' => ['prohibited'],
            'versi' => ['prohibited'],
            'unit_id' => ['prohibited'],
            'indikator_id' => ['prohibited'],
            'tahun' => ['prohibited'],
            'jadwal_tahunan_id' => ['prohibited'],
            'penanggung_jawab_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'disahkan_at' => ['prohibited'],
            'disahkan_by' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'present' => 'Kolom :attribute harus dikirim.',
            'targets.*.nilai.present' => 'Kolom :attribute harus dikirim; gunakan nilai kosong untuk target yang belum diisi.',
            'nullable' => 'Kolom :attribute boleh dikosongkan.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'between' => 'Nilai :attribute melebihi kapasitas penyimpanan.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute tidak memenuhi nilai minimum.',
            'max' => 'Kolom :attribute melebihi batas yang diizinkan.',
            'uuid' => 'Identitas :attribute tidak sah.',
            'exists' => 'Data :attribute tidak ditemukan.',
            'array' => 'Struktur :attribute tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server.',
            'string' => 'Kolom :attribute harus berupa teks.',
        ];
    }
}
