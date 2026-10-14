import type { ReactNode } from 'react';
import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http, type HttpResponse } from '@inertiajs/core';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import PengukuranEdit from '@/Pages/Pengukuran/Edit';
import PengukuranIndex from '@/Pages/Pengukuran/Index';
import VerifikasiShow from '@/Pages/Verifikasi/Show';
import type { BarisPengukuran, Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
        usePage: () => ({ props: { pengaturan: {} } }),
    };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const measurement: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1,
    jalur_pengajuan: 'pic', self_approval: false, reviu_terlambat: false, status: 'draft', target: 100,
    nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual', catatan: '', alasan_tidak_dapat_dihitung: null,
    komponen: [], prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [], unggahan_aktif: true,
    can: { view: true, evidence: true, uploadEvidence: true, update: true, submit: true, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I', tahun: 2026, is_nilai_akhir: false }, bukti_dukungs: [], bukti_count: 0, riwayats: [], snapshot: null,
};
const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // Fokus trap dialog native diuji melalui browser, bukan shim jsdom ini.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, data: JSON.stringify({ nilai: null, status_perhitungan: 'belum_diisi' }), headers: {} });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('Alur pengukuran', () => {
    it('memperbarui pratinjau dari input komponen tanpa menyimpan atau mengirim nilai turunan', async () => {
        const user = userEvent.setup();
        vi.mocked(http.getClient().request).mockResolvedValue({ status: 200, data: JSON.stringify({ nilai: '91.25', status_perhitungan: 'terhitung' }), headers: {} });
        render(<PengukuranEdit pengukuran={{ ...measurement, sumber_nilai: 'komponen',
            komponen: [{ komponen_id: 'a', kode: 'A', label: 'Komponen A', peran: 'penjumlah', bobot: '1.25', nilai: '0' }] }} />);
        const input = screen.getByRole('spinbutton', { name: /Komponen A/ });
        await user.clear(input);
        await user.type(input, '73');
        await screen.findByText('91,25 %');
        const requests = vi.mocked(http.getClient().request).mock.calls;
        expect(JSON.parse(String(requests[requests.length - 1][0].data))).toEqual({ komponen: [{ komponen_id: 'a', nilai: '73' }] });
        expect(router.post).not.toHaveBeenCalled();
        expect(screen.getByText('80,00 %')).toBeTruthy();
    });

    it('mempertahankan string angka besar ketika membuka dan menyimpan form manual', async () => {
        const user = userEvent.setup();
        render(<PengukuranEdit pengukuran={{ ...measurement, nilai: '9007199254740993.120000000000' }} />);
        expect(screen.getByRole<HTMLInputElement>('spinbutton', { name: /Nilai realisasi/ }).value).toBe('9007199254740993.120000000000');
        expect(screen.getByText('9.007.199.254.740.993,12 %')).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ nilai: '9007199254740993.120000000000' });
        expect(http.getClient().request).not.toHaveBeenCalled();
    });

    it('menampilkan target PK, target periode, dan klaim beku dengan bukti sesuai capability', () => {
        const review: Pengukuran = { ...measurement, status: 'diajukan', target_pk: 95, reviu_terlambat: true,
            can: { ...measurement.can, viewClaims: true, claimEvidence: true }, klaim: [{
                id: 'claim', komponen_id: null, arah_dampak: 'menambah', catatan: 'Dukungan periode pertama', sumber_klaim: 'rencana_aksi',
                kegiatan: { id: 'activity', nama: 'Pendampingan QA', tujuan: 'Peningkatan layanan', status: 'tidak_terlaksana',
                    tanggal_rencana: null, tanggal_realisasi: null, sasaran_peserta: 20, realisasi_peserta: 0,
                    justifikasi: 'Jadwal berubah', uraian_pelaksanaan: 'Narasi beku', kendala: 'Ruangan tidak tersedia', strategi_tindaklanjut: 'Jadwalkan kembali',
                    bukti_dukungs: [{ id: 'text', mode: 'teks', nama_asli: null, tautan: null, isi_teks: 'Bukti koreksi beku', download_url: null, menggantikan_id: 'old', alasan_koreksi: 'Lampiran salah periode.' },
                        { id: 'file', mode: 'file', nama_asli: 'surat.pdf', tautan: null, isi_teks: null, download_url: '/pengukuran/qa/bukti-klaim/file', menggantikan_id: null, alasan_koreksi: null }],
                },
            }] };
        const { rerender } = render(<VerifikasiShow pengukuran={review} />);
        expect(screen.getByText('Target PK tahunan').parentElement?.textContent).toContain('95,00 %');
        expect(screen.getByText('Target periode RA · Semester I').parentElement?.textContent).toContain('100,00 %');
        expect(screen.getByText('Status: tidak terlaksana')).toBeTruthy();
        expect(screen.getByText('Narasi beku')).toBeTruthy();
        expect(screen.getByText('Bukti koreksi beku')).toBeTruthy();
        expect(screen.getByText('Reviu terlambat')).toBeTruthy();
        expect(screen.getByText(/Bukti koreksi · Lampiran salah periode/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Unduh bukti kegiatan' }).getAttribute('href')).toBe('/pengukuran/qa/bukti-klaim/file');
        rerender(<VerifikasiShow pengukuran={{ ...review, can: { ...review.can, claimEvidence: false } }} />);
        expect(screen.queryByText('Bukti koreksi beku')).toBeNull();
        expect(screen.queryByText(/Bukti koreksi · Lampiran salah periode/)).toBeNull();
        expect(screen.queryByRole('link', { name: 'Unduh bukti kegiatan' })).toBeNull();
    });
    it('mengirim bukti pengganti beralasan dan mempertahankan koreksi ketika server menolak', async () => {
        const user = userEvent.setup();
        render(<PengukuranEdit pengukuran={{ ...measurement, status: 'dikembalikan', bukti_dukungs: [{
            id: 'old-evidence', jenis_berkas_id: null, mode: 'teks', nama_asli: null, mime: null, ukuran_bytes: null,
            tautan: null, isi_teks: 'Bukti semula', download_url: null, menggantikan_id: null, alasan_koreksi: null,
        }] }} />);
        await user.click(screen.getByRole('checkbox', { name: 'Tambahkan bukti dukung' }));
        await user.selectOptions(screen.getByRole('combobox', { name: 'Bukti yang diganti (opsional)' }), 'old-evidence');
        await user.type(screen.getByRole('textbox', { name: /Alasan koreksi bukti/ }), 'Lampiran salah periode.');
        await user.selectOptions(screen.getByRole('combobox', { name: 'Mode bukti' }), 'teks');
        await user.type(screen.getByRole('textbox', { name: 'Isi bukti teks' }), 'Bukti periode yang benar');
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ bukti: {
            menggantikan_id: 'old-evidence', alasan_koreksi: 'Lampiran salah periode.', mode: 'teks', isi_teks: 'Bukti periode yang benar',
        } });
        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ 'bukti.menggantikan_id': 'Bukti telah dikoreksi.' }); });
        expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'Bukti yang diganti (opsional)' }).value).toBe('old-evidence');
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan koreksi bukti/ }).value).toBe('Lampiran salah periode.');
    });

    it('mengirim intent tombol yang benar dan Enter kembali menyimpan draft setelah pengajuan gagal', async () => {
        const user = userEvent.setup();
        render(<PengukuranEdit pengukuran={measurement} />);
        const value = screen.getByRole<HTMLInputElement>('spinbutton', { name: /Nilai realisasi/ });
        await user.clear(value);
        await user.type(value, '90');
        await user.keyboard('{Enter}');
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ versi: 4, action: 'draft', nilai: '90' });
        await user.click(screen.getByRole('button', { name: 'Ajukan ke Tim Perencanaan' }));
        expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ versi: 4, action: 'ajukan', nilai: '90' });
        await act(async () => { vi.mocked(router.post).mock.calls[1][2]?.onError?.({ catatan: 'Lengkapi catatan.' }); });
        expect(value.value).toBe('90');
        const errorSummary = document.getElementById('measurement-errors');
        expect(errorSummary?.getAttribute('role')).toBe('alert');
        expect(screen.getByRole('textbox', { name: 'Catatan' }).getAttribute('aria-describedby')?.split(' ')).toContain(errorSummary?.id);
        await waitFor(() => expect(document.activeElement).toBe(errorSummary));
        await user.click(value);
        await user.keyboard('{Enter}');
        expect(vi.mocked(router.post).mock.calls[2][1]).toMatchObject({ versi: 4, action: 'draft', nilai: '90' });
        expect(screen.getByText('80,00 %')).toBeTruthy();
    });

    it('mengirim komponen dan mode bukti yang dipilih tanpa mengirim nilai turunan, lalu mempertahankan input saat gagal', async () => {
        const user = userEvent.setup();
        render(<PengukuranEdit pengukuran={{ ...measurement, sumber_nilai: 'komponen', nilai: null, status_perhitungan: 'belum_diisi', unggahan_aktif: false,
            can: { ...measurement.can, submit: false }, prasyarat: { siap: false, alasan: ['Lengkapi komponen.'] },
            komponen: [{ komponen_id: 'numerator', kode: 'A', label: 'Pembilang', peran: 'pembilang', bobot: null, nilai: null }, { komponen_id: 'denominator', kode: 'B', label: 'Penyebut', peran: 'penyebut', bobot: null, nilai: null }],
            persyaratan_bukti: [{ id: 'requirement', nama: 'Penjelasan', wajib: true, semua_mode_wajib: false, izinkan_file: true, izinkan_tautan: false, izinkan_teks: true, format_diizinkan: 'pdf', ukuran_maks_kb: 10240, pemenuhan: { terpenuhi: false, mode_terpenuhi: [], mode_kurang: ['teks'], mode_dikecualikan: ['file'], alasan_pengecualian: 'Unggahan dinonaktifkan.' } }],
        }} />);
        expect(screen.queryByRole('spinbutton', { name: /Nilai realisasi/ })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Ajukan ke Tim Perencanaan' })).toBeNull();
        await user.type(screen.getByRole('spinbutton', { name: /Pembilang/ }), '0');
        await user.click(screen.getByRole('checkbox', { name: 'Tambahkan bukti dukung' }));
        await user.selectOptions(screen.getByRole('combobox', { name: 'Persyaratan yang dipenuhi' }), 'requirement');
        expect(screen.queryByRole('option', { name: 'Unggahan file' })).toBeNull();
        const evidence = screen.getByRole<HTMLTextAreaElement>('textbox', { name: 'Isi bukti teks' });
        await user.type(evidence, 'Penjelasan hasil pengukuran');
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        const payload = vi.mocked(router.post).mock.calls[0][1];
        expect(payload).toMatchObject({ komponen: [{ komponen_id: 'numerator', nilai: '0' }, { komponen_id: 'denominator', nilai: null }], bukti: { jenis_berkas_id: 'requirement', mode: 'teks', isi_teks: 'Penjelasan hasil pengukuran' } });
        expect(payload).not.toHaveProperty('nilai');
        expect(payload).not.toHaveProperty('bukti.file');
        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ 'komponen.1.nilai': 'Penyebut belum diisi.' }); });
        expect(evidence.value).toBe('Penjelasan hasil pengukuran');
        expect(screen.getByRole('spinbutton', { name: /Penyebut/ }).getAttribute('aria-invalid')).toBe('true');
    });

    it('menampilkan keputusan dari capability dan mengirim versi tanpa narasi untuk verifikasi', async () => {
        const user = userEvent.setup();
        render(<VerifikasiShow pengukuran={{ ...measurement, status: 'diajukan', can: { ...measurement.can, update: false, submit: false, verify: true } }} />);
        expect(screen.queryByRole('button', { name: 'Sahkan kinerja resmi' })).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Verifikasi pengukuran' }));
        await user.click(screen.getByRole('button', { name: 'Konfirmasi' }));
        expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual([`/verifikasi/${measurement.id}/verifikasi`, { versi: 4 }]);
    });

    it('mempertahankan catatan pengembalian dan modal pada validasi gagal', async () => {
        const user = userEvent.setup();
        render(<VerifikasiShow pengukuran={{ ...measurement, status: 'diajukan', can: { ...measurement.can, update: false, submit: false, return: true } }} />);
        await user.click(screen.getByRole('button', { name: 'Kembalikan untuk revisi' }));
        const note = screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Catatan perbaikan/ });
        await user.type(note, 'Perjelas rincian realisasi');
        await user.click(screen.getByRole('button', { name: 'Konfirmasi' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toEqual({ versi: 4, catatan: 'Perjelas rincian realisasi' });
        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ catatan: 'Catatan perlu diperjelas.' }); });
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(note.value).toBe('Perjelas rincian realisasi');
        expect(note.getAttribute('aria-invalid')).toBe('true');
        expect(document.activeElement).toBe(note);
    });
});


it('cancel setelah submit mempertahankan draft dan mengunci klik serta Enter ulang', async () => {
    const user = userEvent.setup();
    render(<PengukuranEdit pengukuran={measurement} />);
    const submit = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan Draft' });
    await user.click(submit);
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => { options?.onCancel?.(); });
    expect(screen.getByText(/Permintaan dibatalkan/)).toBeTruthy();
    expect(submit.disabled).toBe(true);
    await user.click(submit);
    await user.click(screen.getByRole('spinbutton', { name: /Nilai realisasi/ }));
    await user.keyboard('{Enter}');
    expect(router.post).toHaveBeenCalledTimes(1);
});

describe('Draft narasi dan Belum dapat diukur', () => {
    const bdd = () => screen.getByRole<HTMLInputElement>('checkbox', { name: 'Belum dapat diukur' });
    const komponenUji: Pengukuran = { ...measurement, sumber_nilai: 'komponen', alasan_tidak_dapat_dihitung: 'Penyebut nol',
        komponen: [{ komponen_id: 'a', kode: 'A', label: 'Komponen A', peran: 'pembilang', bobot: null, nilai: '5' }] };

    it('memilih Belum dapat diukur mengosongkan input, melepas pratinjau tanpa hasil basi, dan kembali dari kosong', async () => {
        const user = userEvent.setup();
        let finish: ((response: HttpResponse) => void) | undefined;
        const request = vi.mocked(http.getClient().request).mockImplementation(() => new Promise((resolve) => { finish = resolve; }));
        render(<PengukuranEdit pengukuran={komponenUji} />);
        const input = screen.getByRole<HTMLInputElement>('spinbutton', { name: /Komponen A/ });
        await user.clear(input);
        await user.type(input, '0');
        await waitFor(() => expect(JSON.parse(String(request.mock.calls[request.mock.calls.length - 1][0].data))).toEqual({ komponen: [{ komponen_id: 'a', nilai: '0' }] }));
        const pending = request.mock.calls[request.mock.calls.length - 1][0];

        await user.click(bdd());
        expect(pending.signal?.aborted).toBe(true);
        await act(async () => { finish?.({ status: 200, data: JSON.stringify({ nilai: '0', status_perhitungan: 'terhitung' }), headers: {} }); });
        expect(screen.queryByText('Pratinjau · belum disimpan')).toBeNull();
        expect(screen.queryByText('0,00 %')).toBeNull();
        expect(input.value).toBe('');
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan bila hasil/ }).value).toBe('');
        expect(screen.getByText('80,00 %')).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ action: 'draft', status_perhitungan: 'belum_dapat_diukur', komponen: [{ komponen_id: 'a', nilai: null }], alasan_tidak_dapat_dihitung: '' });

        await user.click(bdd());
        expect(input.value).toBe('');
        expect(input.disabled).toBe(false);
    });

    it('angka manual besar dibersihkan saat Belum dapat diukur; periode nilai akhir tidak menawarkannya tetapi alasan penyebut nol tetap', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<PengukuranEdit pengukuran={measurement} />);
        const value = screen.getByRole<HTMLInputElement>('spinbutton', { name: /Nilai realisasi/ });
        await user.clear(value);
        await user.type(value, '9007199254740993.123456789012');
        await user.click(bdd());
        expect(value.value).toBe('');
        expect(value.disabled).toBe(true);
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ status_perhitungan: 'belum_dapat_diukur', nilai: null });

        rerender(<PengukuranEdit pengukuran={{ ...komponenUji, id: 'akhir', periode_jadwal: { ...measurement.periode_jadwal, is_nilai_akhir: true } }} />);
        expect(screen.queryByRole('checkbox', { name: 'Belum dapat diukur' })).toBeNull();
        expect(screen.getByRole('textbox', { name: /Alasan bila hasil/ })).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ status_perhitungan: null });
        await act(async () => { vi.mocked(router.post).mock.calls[1][2]?.onError?.({ status_perhitungan: 'Status Belum dapat diukur tidak tersedia pada periode nilai akhir.' }); });
        expect(screen.getByText('Status Belum dapat diukur tidak tersedia pada periode nilai akhir.')).toBeTruthy();
    });

    it('narasi terkirim dan bertahan setelah galat; tindak lanjut tersembunyi tanpa rekomendasi dan tidak dikirim', async () => {
        const user = userEvent.setup();
        render(<PengukuranEdit pengukuran={{ ...measurement, progres_kegiatan: 'Progres lama', tindak_lanjut_rekomendasi: 'Isi tersimpan' }} />);
        expect(screen.queryByRole('textbox', { name: 'Tindak lanjut rekomendasi' })).toBeNull();
        expect(screen.getByRole('textbox', { name: 'Catatan' })).toBeTruthy();
        expect(screen.queryByText(/Catatan wajib/)).toBeNull();
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: 'Progres kegiatan' }).value).toBe('Progres lama');
        const kendala = screen.getByRole<HTMLTextAreaElement>('textbox', { name: 'Kendala/masalah' });
        await user.type(kendala, 'Data terlambat');
        await user.type(screen.getByRole('textbox', { name: 'Strategi tindak lanjut' }), 'Percepat rekap');
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        const payload = vi.mocked(router.post).mock.calls[0][1];
        expect(payload).toMatchObject({ status_perhitungan: null, progres_kegiatan: 'Progres lama', kendala_masalah: 'Data terlambat', strategi_tindak_lanjut: 'Percepat rekap' });
        expect(payload).not.toHaveProperty('tindak_lanjut_rekomendasi');

        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ versi: 'Data telah berubah. Muat ulang halaman.', kendala_masalah: 'Kendala/masalah maksimal 10000 karakter.' }); });
        expect(kendala.value).toBe('Data terlambat');
        expect(kendala.getAttribute('aria-invalid')).toBe('true');
        await waitFor(() => expect(document.activeElement).toBe(document.getElementById('measurement-errors')));
    });

    it('tindak lanjut tampil bersama rekomendasi approved dan hilang bila reader menyatakan tidak ada', async () => {
        const user = userEvent.setup();
        const rekomendasi = { id: 'rek-1', isi: 'Perkuat monitoring bulanan', tahun: 2025, periode_id: 'per-0', disetujui_pada: '2026-01-10T08:00:00+08:00' };
        const { rerender } = render(<PengukuranEdit pengukuran={{ ...measurement, rekomendasi_sebelumnya: rekomendasi, tindak_lanjut_rekomendasi: 'Monitoring berjalan' }} />);
        expect(screen.getByText(/Perkuat monitoring bulanan/)).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ tindak_lanjut_rekomendasi: 'Monitoring berjalan' });

        rerender(<PengukuranEdit pengukuran={{ ...measurement, rekomendasi_sebelumnya: null, tindak_lanjut_rekomendasi: 'Monitoring berjalan' }} />);
        expect(screen.queryByRole('textbox', { name: 'Tindak lanjut rekomendasi' })).toBeNull();
    });

    it('draft Belum dapat diukur tersimpan dibuka kosong dan alasan prasyarat server mengatur Ajukan', async () => {
        const user = userEvent.setup();
        const alasan = 'Pengajuan belum tersedia untuk status Belum dapat diukur. Simpan sebagai draft.';
        render(<PengukuranEdit pengukuran={{ ...measurement, nilai: null, status_perhitungan: 'belum_dapat_diukur',
            can: { ...measurement.can, submit: false }, prasyarat: { siap: false, alasan: [alasan] } }} />);
        expect(bdd().checked).toBe(true);
        expect(screen.getByRole<HTMLInputElement>('spinbutton', { name: /Nilai realisasi/ }).value).toBe('');
        expect(screen.getByText(alasan)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Ajukan ke Tim Perencanaan' })).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ action: 'draft', status_perhitungan: 'belum_dapat_diukur', nilai: null });
    });
});

const tw1 = measurement.periode_jadwal;
const konteksBaris = {
    indikator_id: 'ind-1', jadwal_id: 'jad-1', jadwal_snapshot_id: 'snap-1', tahun: 2026, periode: tw1, pic: null,
    indikator: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', desimal_tampilan: 2 }, unit: { id: 'unit-1', nama: 'Unit Uji' },
};
const ringkasan = { id: measurement.id, status: 'draft' as const, nilai: 80, status_perhitungan: 'terhitung' as const, target: 100, bukti_count: 0, self_approval: false, reviu_terlambat: false };
const baris = (kode: string, row: Partial<BarisPengukuran>): BarisPengukuran => ({ ...konteksBaris, indikator_id: kode, indikator: { ...konteksBaris.indikator, kode }, ...row } as BarisPengukuran);
const indexProps = (pengukurans: BarisPengukuran[], extra: Record<string, unknown> = {}) => ({
    konteks: [{ jadwal_id: 'jad-1', tahun: 2026, periode: tw1 }], filters: { jadwal_id: null, periode_id: null }, can: { selectPeriod: false },
    pengukurans, pagination: { current_page: 1, last_page: 1, total: pengukurans.length, prev_page_url: null, next_page_url: null }, ...extra,
});

describe('Daftar kewajiban pengukuran', () => {
    it('membuka draft atau detail existing tanpa menyimpulkan izin edit', () => {
        render(<PengukuranIndex {...indexProps([
            baris('IKU-UJI', { jenis: 'existing', pengukuran: ringkasan, can: { create: false } }),
            baris('IKU-DUA', { jenis: 'existing', pengukuran: { ...ringkasan, id: 'p-2', status: 'disahkan' }, can: { create: false } }),
        ])} />);
        expect(screen.getByRole('link', { name: 'Buka Draft IKU-UJI Semester I 2026' }).getAttribute('href')).toBe(`/pengukuran/${measurement.id}/edit`);
        expect(screen.getByRole('link', { name: 'Detail IKU-DUA Semester I 2026' }).getAttribute('href')).toBe('/pengukuran/p-2/edit');
        expect(screen.queryByRole('button', { name: /Isi Pengukuran/ })).toBeNull();
    });

    it('Isi Pengukuran mengirim snapshot dan periode server; baris tanpa izin atau tidak berlaku tidak menawarkan aksi', async () => {
        const user = userEvent.setup();
        render(<PengukuranIndex {...indexProps([
            baris('IKU-UJI', { jenis: 'absent', berlaku: true, pengukuran: null, target: 75, can: { create: true } }),
            baris('IKU-DUA', { jenis: 'absent', berlaku: true, pengukuran: null, target: null, can: { create: false } }),
            baris('IKU-TIGA', { jenis: 'absent', berlaku: false, pengukuran: null, target: null, can: { create: false } }),
        ])} />);
        // Status dirender dua kali: kolom Status (desktop) dan sel indikator (layar sempit); jsdom tidak menerapkan CSS.
        expect(screen.getAllByText('Belum Mengisi')).toHaveLength(4);
        expect(screen.getAllByText('Tidak berlaku')).toHaveLength(2);
        expect(screen.getAllByRole('button', { name: /Isi Pengukuran/ })).toHaveLength(1);

        await user.click(screen.getByRole('button', { name: 'Isi Pengukuran IKU-UJI Semester I 2026' }));
        expect(router.post).toHaveBeenCalledTimes(1);
        expect(vi.mocked(router.post).mock.calls[0].slice(0, 2)).toEqual(['/pengukuran/ensure-draft', { jadwal_snapshot_id: 'snap-1', periode_id: tw1.id }]);
        await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ pengukuran: 'Jendela pengisian PIC periode ini sudah ditutup.' }); });
        expect(screen.getByRole('alert').textContent).toBe('Jendela pengisian PIC periode ini sudah ditutup.');
    });

    it('membedakan jadwal belum tersedia, pengisian belum dimulai, dan periode tanpa indikator', () => {
        const { rerender } = render(<PengukuranIndex {...indexProps([], { konteks: [] })} />);
        expect(screen.getByText('Jadwal aktif belum tersedia')).toBeTruthy();
        rerender(<PengukuranIndex {...indexProps([], { konteks: [{ jadwal_id: 'jad-1', tahun: 2026, periode: null }] })} />);
        expect(screen.getByText('Pengisian belum dimulai')).toBeTruthy();
        rerender(<PengukuranIndex {...indexProps([])} />);
        expect(screen.getByText('Belum ada indikator pada periode ini')).toBeTruthy();
    });

    it('filter periode hanya untuk capability server dan mengirim pasangan jadwal-periode', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'get').mockImplementation(() => undefined);
        const tw2 = { ...tw1, id: 'per-2', nama_periode: 'Semester II', urutan: 2 };
        const pilihan = [{ id: 'jad-1', tahun: 2026, periode: [tw1, tw2] }, { id: 'jad-2', tahun: 2027, periode: [{ ...tw1, id: 'per-27', tahun: 2027 }] }];
        const { rerender } = render(<PengukuranIndex {...indexProps([])} />);
        expect(screen.queryByRole('combobox', { name: 'Periode' })).toBeNull();

        rerender(<PengukuranIndex {...indexProps([], { can: { selectPeriod: true }, pilihan_jadwal: pilihan })} />);
        expect(screen.queryByRole('button', { name: 'Periode berjalan' })).toBeNull();
        await user.selectOptions(screen.getByRole('combobox', { name: 'Periode' }), 'per-2');
        await user.click(screen.getByRole('button', { name: 'Tampilkan' }));
        expect(vi.mocked(router.get).mock.calls[0].slice(0, 2)).toEqual(['/pengukuran', { jadwal_id: 'jad-1', periode_id: 'per-2' }]);
        await user.selectOptions(screen.getByRole('combobox', { name: 'Jadwal' }), 'jad-2');
        expect((screen.getByRole('combobox', { name: 'Periode' }) as HTMLSelectElement).value).toBe('per-27');
        await user.click(screen.getByRole('button', { name: 'Tampilkan' }));
        expect(vi.mocked(router.get).mock.calls[1].slice(0, 2)).toEqual(['/pengukuran', { jadwal_id: 'jad-2', periode_id: 'per-27' }]);

        rerender(<PengukuranIndex {...indexProps([], { can: { selectPeriod: true }, pilihan_jadwal: pilihan, filters: { jadwal_id: 'jad-1', periode_id: 'per-2' } })} />);
        await user.click(screen.getByRole('button', { name: 'Periode berjalan' }));
        expect(vi.mocked(router.get).mock.calls[2][0]).toBe('/pengukuran');
    });

    it('pilihan awal mengabaikan jadwal konteks yang sudah melewati penutupan', async () => {
        const user = userEvent.setup();
        vi.spyOn(router, 'get').mockImplementation(() => undefined);
        // Jadwal 2025 masih aktif tetapi lewat penutupan: tetap di konteks default, tidak ada di pilihan filter.
        const tw4Lama = { ...tw1, id: 'per-25', nama_periode: 'Triwulan IV', tahun: 2025 };
        const tw3 = { ...tw1, id: 'per-3', nama_periode: 'Triwulan III', urutan: 3 };
        render(<PengukuranIndex {...indexProps([], { can: { selectPeriod: true },
            konteks: [{ jadwal_id: 'jad-25', tahun: 2025, periode: tw4Lama }, { jadwal_id: 'jad-1', tahun: 2026, periode: tw3 }],
            pilihan_jadwal: [{ id: 'jad-1', tahun: 2026, periode: [tw1, tw3] }] })} />);
        expect((screen.getByRole('combobox', { name: 'Jadwal' }) as HTMLSelectElement).value).toBe('jad-1');
        expect((screen.getByRole('combobox', { name: 'Periode' }) as HTMLSelectElement).value).toBe('per-3');
        await user.click(screen.getByRole('button', { name: 'Tampilkan' }));
        expect(vi.mocked(router.get).mock.calls[0].slice(0, 2)).toEqual(['/pengukuran', { jadwal_id: 'jad-1', periode_id: 'per-3' }]);
    });

    it('menampilkan penolakan filter dari server pada pilihan yang bersangkutan', () => {
        const pilihan = [{ id: 'jad-1', tahun: 2026, periode: [tw1] }];
        render(<PengukuranIndex {...indexProps([], { can: { selectPeriod: true }, pilihan_jadwal: pilihan,
            errors: { jadwal_id: 'Pilih jadwal aktif yang belum melewati tanggal penutupan.', periode_id: 'Periode bukan anggota jadwal yang dipilih.' } })} />);
        expect(screen.getByText('Pilih jadwal aktif yang belum melewati tanggal penutupan.')).toBeTruthy();
        expect(screen.getByText('Periode bukan anggota jadwal yang dipilih.')).toBeTruthy();
        expect(screen.getByRole('combobox', { name: 'Periode' }).getAttribute('aria-invalid')).toBe('true');
    });
});
