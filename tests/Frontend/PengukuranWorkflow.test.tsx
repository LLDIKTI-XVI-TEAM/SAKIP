import type { ReactNode } from 'react';
import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import PengukuranEdit from '@/Pages/Pengukuran/Edit';
import VerifikasiShow from '@/Pages/Verifikasi/Show';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const measurement: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1,
    jalur_pengajuan: 'pic', self_approval: false, status: 'draft', target: 100,
    nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual', catatan: '', alasan_tidak_dapat_dihitung: null,
    komponen: [], prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [], unggahan_aktif: true,
    can: { view: true, evidence: true, uploadEvidence: true, update: true, submit: true, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I' }, bukti_dukungs: [], bukti_count: 0, riwayats: [], snapshot: null,
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
beforeEach(() => { vi.spyOn(router, 'post').mockImplementation(() => undefined); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('Alur pengukuran', () => {
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
        await user.click(screen.getByRole('button', { name: 'Simpan Sebagai Draft' }));
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
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('alert')));
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
        await user.click(screen.getByRole('button', { name: 'Simpan Sebagai Draft' }));
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
