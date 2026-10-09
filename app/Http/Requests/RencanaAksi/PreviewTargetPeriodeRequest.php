<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PreviewTargetPeriodeRequest extends FormRequest
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
            // `exists`, bukan lolos (`return true`) yang membiarkan 422 vs
            // 404 menjadi oracle keberadaan UUID lintas unit. Lookup
            // mendahului Gate agar urutan respons 404/403 tetap sama —
            // cermin `UpdateUnitRequest::authorize` (`findOrFail` sebelum
            // `Gate`). Murni `return false` ditolak: kasus dengan-izin atas
            // UUID asing wajib 404 (bukan 403). Tanpa ubah kontrak route
            // publik (tetap string + `whereUuid`).
            abort(404);
        }

        // Pratinjau menuntut izin baca DAN tulis bersama
        // (cermin gerbang ganda di `PreviewTargetPeriode::handle`). Tanpa
        // `read` (atau kena deny) selalu 403 di sini — sebelum validasi
        // `exists` — agar tak membocorkan keberadaan UUID lintas unit via
        // 422. Otorisasi rinci tetap ditegakkan di Action via Gate agar
        // respons JSON 403 konsisten dengan pratinjau pengukuran.
        return Gate::allows('view', $header) && Gate::allows('update', $header);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Versi header wajib dikirim (`required`),
            // cermin `SimpanTargetPeriodeRequest`; pratinjau menolak konteks
            // usang 409 agar skor/deviasi tak tercampur (input form + target
            // v2 tak terlihat). Tanpa persistensi/audit — murni tolak hitung.
            'expected_versi' => ['required', 'integer', 'min:1'],
            // Token konkurensi snapshot WAJIB dikirim
            // (`present`), cermin `SimpanTargetPeriodeRequest`; null lolos
            // validasi bentuk tetapi selalu ditolak 409 oleh Action. Pratinjau
            // menolak konteks usang 409 agar yang ditampilkan = yang dipakai
            // simpan.
            'expected_snapshot_id' => ['present', 'nullable', 'uuid', 'exists:jadwal_snapshot,id'],
            'expected_snapshot_versi' => ['present', 'nullable', 'integer', 'min:1'],
            // Subset bentuk simpan — pratinjau murni kalkulasi, bukan
            // persistensi. Kelengkapan per-periode sengaja tidak dituntut
            // (sel hilang = belum_diisi, bukan 422).
            'alasan_deviasi_pk' => ['nullable', 'string', 'max:10000'],
            'targets' => ['required', 'array', 'min:1', 'max:600'],
            'targets.*.periode_id' => ['required', 'uuid', 'exists:periode,id'],
            'targets.*.komponen_id' => ['nullable', 'uuid', 'exists:indikator_komponen,id'],
            'targets.*.nilai' => ['present', 'nullable', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'targets.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'present' => 'Kolom :attribute harus dikirim; gunakan nilai kosong untuk target yang belum diisi.',
            'nullable' => 'Kolom :attribute boleh dikosongkan.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'between' => 'Nilai :attribute melebihi kapasitas penyimpanan.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'uuid' => 'Identitas :attribute tidak sah.',
            'exists' => 'Data :attribute tidak ditemukan.',
            'array' => 'Struktur :attribute tidak sah.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'min' => 'Kolom :attribute tidak memenuhi nilai minimum.',
            'max' => 'Kolom :attribute melebihi batas yang diizinkan.',
        ];
    }
}
