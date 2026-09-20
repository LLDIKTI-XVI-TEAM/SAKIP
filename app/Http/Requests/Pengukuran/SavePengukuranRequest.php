<?php

namespace App\Http\Requests\Pengukuran;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePengukuranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['versi' => ['required', 'integer', 'min:1'], 'action' => ['required', 'in:draft,ajukan'],
            'nilai' => ['nullable', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'komponen' => ['sometimes', 'array'], 'komponen.*' => ['array:komponen_id,nilai'],
            'komponen.*.komponen_id' => ['required', 'uuid', 'distinct'], 'komponen.*.nilai' => ['present', 'nullable', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'catatan' => ['nullable', 'string', 'max:10000'], 'alasan_tidak_dapat_dihitung' => ['nullable', 'string', 'max:10000'],
            'bukti' => ['sometimes', 'nullable', 'array:jenis_berkas_id,mode,file,tautan,isi_teks'],
            'bukti.jenis_berkas_id' => ['nullable', 'uuid'], 'bukti.mode' => ['required_with:bukti', 'in:file,tautan,teks'],
            'bukti.file' => [Rule::requiredIf(fn () => $this->input('bukti.mode') === 'file'), 'nullable', 'file', Rule::prohibitedIf(fn () => $this->input('bukti.mode') !== 'file')],
            'bukti.tautan' => [Rule::requiredIf(fn () => $this->input('bukti.mode') === 'tautan'), 'nullable', 'url:http,https', 'max:2048', Rule::prohibitedIf(fn () => $this->input('bukti.mode') !== 'tautan')],
            'bukti.isi_teks' => [Rule::requiredIf(fn () => $this->input('bukti.mode') === 'teks'), 'nullable', 'string', 'max:10000', Rule::prohibitedIf(fn () => $this->input('bukti.mode') !== 'teks')],
            'status_alur' => ['prohibited'], 'status_perhitungan' => ['prohibited'], 'sumber_nilai' => ['prohibited'], 'diajukan_by' => ['prohibited'],
            'jalur_pengajuan' => ['prohibited'], 'dasar_izin_pengajuan' => ['prohibited'], 'jadwal_snapshot_id' => ['prohibited'], 'rencana_aksi_versi_id' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['required' => 'Kolom :attribute wajib diisi.', 'required_with' => 'Kolom :attribute wajib diisi saat mengirim bukti.',
            'present' => 'Kolom :attribute harus dikirim; gunakan nilai kosong untuk komponen belum diisi.',
            'numeric' => 'Kolom :attribute harus berupa angka.', 'between' => 'Nilai :attribute melebihi kapasitas penyimpanan.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.', 'min' => 'Kolom :attribute tidak memenuhi nilai minimum.',
            'max' => 'Kolom :attribute melebihi batas yang diizinkan.', 'uuid' => 'Identitas :attribute tidak sah.',
            'distinct' => 'Komponen tidak boleh dikirim berulang.', 'in' => 'Pilihan :attribute tidak tersedia.', 'array' => 'Struktur :attribute tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server atau tidak sesuai mode bukti.', 'file' => 'Bukti harus berupa berkas unggahan.',
            'url' => 'Tautan bukti harus berupa URL http atau https.', 'string' => 'Kolom :attribute harus berupa teks.'];
    }
}
