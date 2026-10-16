import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http } from '@inertiajs/core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PengukuranEdit from '@/Pages/Pengukuran/Edit';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null, usePage: () => ({ props: { pengaturan: {} } }) };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const tersimpan: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1,
    jalur_pengajuan: 'pic', self_approval: false, reviu_terlambat: false, status: 'draft', target: 100,
    nilai: null, status_perhitungan: 'tidak_dapat_dihitung', sumber_nilai: 'komponen', catatan: '', alasan_tidak_dapat_dihitung: 'Penyebut nol',
    komponen: [{ komponen_id: 'a', kode: 'A', label: 'Komponen A', peran: 'pembilang', bobot: null, nilai: '5' }],
    prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [], unggahan_aktif: true,
    can: { view: true, evidence: true, uploadEvidence: true, update: true, submit: true, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I', tahun: 2026, is_nilai_akhir: false }, bukti_dukungs: [], bukti_count: 0, riwayats: [], snapshot: null,
};
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, data: JSON.stringify({ nilai: null, status_perhitungan: 'tidak_dapat_dihitung' }), headers: {} });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const bdd = () => screen.getByRole('switch', { name: 'Belum dapat diukur' });
const komponenA = () => screen.getByRole<HTMLInputElement>('spinbutton', { name: /Komponen A/ });
// Meniru Inertia untuk POST ditolak: redirect back men-swap props halaman tanpa remount (preserveState), lalu onError.
const tolak = async (rerender: (ui: ReactNode) => void, berikutnya: Pengukuran, errors: Record<string, string>) => {
    rerender(<PengukuranEdit pengukuran={berikutnya} />);
    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.(errors); });
};

describe('Form pengukuran setelah simpan ditolak', () => {
    it('form yang menjadi baca-saja menampilkan nilai tersimpan, bukan input lokal basi', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<PengukuranEdit pengukuran={tersimpan} />);
        await user.clear(komponenA());
        await user.type(komponenA(), '12');
        await user.click(bdd());
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));

        // Aktor lain sudah mengajukan: props berikutnya membawa status beku dan can.update=false.
        await tolak(rerender, { ...tersimpan, versi: 5, status: 'diajukan', can: { ...tersimpan.can, update: false, submit: false } },
            { versi: 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.' });

        expect(screen.getByText('Formulir hanya dapat dibaca sesuai status dan izin akses Anda.')).toBeTruthy();
        expect(bdd().getAttribute('aria-checked')).toBe('false');
        expect(komponenA().value).toBe('5');
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: /Alasan bila hasil/ }).value).toBe('Penyebut nol');
        // Alasan penolakan tetap terlihat agar pengguna tahu simpanannya tidak masuk.
        expect(document.getElementById('measurement-errors')?.textContent).toContain('Data telah berubah. Muat ulang sebelum mengulangi tindakan.');
    });

    it('form yang masih dapat diubah mempertahankan input pengguna saat galat validasi', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<PengukuranEdit pengukuran={tersimpan} />);
        await user.clear(komponenA());
        await user.type(komponenA(), '12');
        await user.type(screen.getByRole('textbox', { name: 'Catatan' }), 'Catatan baru');
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));

        await tolak(rerender, { ...tersimpan }, { 'komponen.0.nilai': 'Nilai komponen tidak valid.' });

        expect(komponenA().value).toBe('12');
        expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: 'Catatan' }).value).toBe('Catatan baru');
        expect(komponenA().disabled).toBe(false);
        expect(document.getElementById('measurement-errors')?.textContent).toContain('Nilai komponen tidak valid.');
    });
});
