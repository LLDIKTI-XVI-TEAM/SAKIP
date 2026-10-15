import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import VerifikasiShow from '@/Pages/Verifikasi/Show';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
        usePage: () => ({ props: { pengaturan: {} } }),
    };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));

const PESAN_ARSIP = 'Indikator berstatus arsip; pengukuran tidak dapat diubah, diajukan, atau direviu.';

const dasar: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1,
    jalur_pengajuan: 'perencanaan', self_approval: false, reviu_terlambat: false, status: 'diverifikasi', target: 100,
    nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual', catatan: '', alasan_tidak_dapat_dihitung: null,
    komponen: [], prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [], unggahan_aktif: true,
    can: { view: true, evidence: true, uploadEvidence: false, update: false, submit: false, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I' }, bukti_dukungs: [], bukti_count: 0, riwayats: [], snapshot: null,
};

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('Verifikasi alasan guard', () => {
    it('menampilkan notice alasan arsip bila panel keputusan tersembunyi', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar,
            businessErrors: { verifikasi: [PESAN_ARSIP], sahkan: [PESAN_ARSIP], kembalikan: [PESAN_ARSIP] } }} />);

        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Sahkan kinerja resmi' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Kembalikan untuk revisi' })).toBeNull();

        const notice = screen.getByRole('status');
        expect(notice.textContent).toContain('Tindakan reviu tidak tersedia');
        expect(notice.textContent).toContain(PESAN_ARSIP);
    });

    it('tidak menampilkan notice bila businessErrors kosong', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar, businessErrors: { verifikasi: [], sahkan: [], kembalikan: [] } }} />);

        expect(screen.queryByRole('status')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
    });

    it('tetap valid bila payload lama tanpa businessErrors', () => {
        render(<VerifikasiShow pengukuran={dasar} />);

        expect(screen.queryByRole('status')).toBeNull();
    });
});
