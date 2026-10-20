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
const PESAN_F1 = 'Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.';
const PESAN_ALASAN_TERSEDIA = 'Alasan aksi tersedia yang tidak boleh tampil di notice.';

const dasar: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1,
    jalur_pengajuan: 'perencanaan', self_approval: false, reviu_terlambat: false, status: 'diverifikasi', target: 100,
    nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual', catatan: '', alasan_tidak_dapat_dihitung: null,
    komponen: [], prasyarat: { siap: true, alasan: [] }, persyaratan_bukti: [],
    can: { view: true, evidence: true, uploadEvidence: false, update: false, submit: false, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I', tahun: 2026, is_nilai_akhir: false }, bukti_dukungs: [], bukti_count: 0, riwayats: [], snapshot: null,
};

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('Rekomendasi beku pada verifikasi', () => {
    it('menampilkan rekomendasi dari versi pengajuan bersama tindak lanjutnya', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar,
            rekomendasi_sebelumnya: { id: 'c2af8a99-5e01-47fb-ae07-74c123880cd2', isi: 'Arahan beku sesuai pengajuan.',
                tahun: 2025, periode_id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', disetujui_pada: '2025-12-31T08:00:00Z' },
            tindak_lanjut_rekomendasi: 'Tindak lanjut atas arahan beku.' }} />);

        expect(screen.getByText('Rekomendasi periode sebelumnya')).toBeTruthy();
        expect(screen.getByText('Arahan beku sesuai pengajuan.')).toBeTruthy();
        expect(screen.getByText('Tindak lanjut atas arahan beku.')).toBeTruthy();
    });

    it.each([null, undefined])('menjelaskan rekomendasi yang tidak tersedia pada versi pengajuan (%s)', (rekomendasi) => {
        render(<VerifikasiShow pengukuran={{ ...dasar, rekomendasi_sebelumnya: rekomendasi }} />);

        expect(screen.getByText('Tidak ada rekomendasi pada versi pengajuan ini.')).toBeTruthy();
        expect(screen.getByText('Tindak lanjut rekomendasi')).toBeTruthy();
    });
});

describe('Verifikasi alasan guard', () => {
    it('menampilkan notice alasan arsip bila panel keputusan tersembunyi', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar,
            businessErrors: { verifikasi: [], sahkan: [PESAN_ARSIP], kembalikan: [PESAN_ARSIP] } }} />);

        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Sahkan kinerja resmi' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Kembalikan untuk revisi' })).toBeNull();

        const notice = screen.getByRole('status');
        expect(notice.textContent).toContain('Tindakan reviu tidak tersedia');
        expect(notice.textContent).toContain(PESAN_ARSIP);
    });

    it('menampilkan notice alasan aksi tertutup walau panel keputusan terlihat (kasus campuran F1)', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar, status: 'diajukan', jalur_pengajuan: 'pic',
            can: { ...dasar.can, verify: false, ratify: false, return: true },
            businessErrors: { verifikasi: [PESAN_F1], sahkan: [PESAN_F1], kembalikan: [PESAN_ALASAN_TERSEDIA] } }} />);

        expect(screen.getByRole('button', { name: 'Kembalikan untuk revisi' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Sahkan kinerja resmi' })).toBeNull();

        const notice = screen.getByRole('status');
        expect(notice.textContent).toContain('Sebagian tindakan tidak tersedia');
        expect(notice.textContent).toContain(PESAN_F1);
        expect(notice.textContent).not.toContain(PESAN_ALASAN_TERSEDIA);
    });

    it('tidak menampilkan notice bila businessErrors kosong', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar, businessErrors: { verifikasi: [], sahkan: [], kembalikan: [] } }} />);

        expect(screen.queryByRole('status')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Verifikasi pengukuran' })).toBeNull();
    });

    it('tidak menampilkan notice pada pengukuran disahkan berpeta kosong', () => {
        render(<VerifikasiShow pengukuran={{ ...dasar, status: 'disahkan',
            snapshot: { snapshot_hash: 'hash-uji', disahkan_pada: '2026-04-01T10:00:00+07:00', nomor_pengajuan: 1 },
            businessErrors: { verifikasi: [], sahkan: [], kembalikan: [] } }} />);

        expect(screen.getByText('Pengukuran telah disahkan')).toBeTruthy();
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('tetap valid bila payload lama tanpa businessErrors', () => {
        render(<VerifikasiShow pengukuran={dasar} />);

        expect(screen.queryByRole('status')).toBeNull();
    });
});
