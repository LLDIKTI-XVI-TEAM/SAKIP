import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import { router } from '@inertiajs/react';
import { http } from '@inertiajs/core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PengukuranEdit from '@/Pages/Pengukuran/Edit';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null, usePage: () => ({ props: { pengaturan: {} } }) };
});
// Layout tiruan sengaja ikut merender headerActions agar elemen di area judul tetap teruji.
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <><header>{headerActions}</header><main>{children}</main></>,
}));

const pengukuran: Pengukuran = {
    id: '7b2a8af8-3d18-42bb-9b34-ff0bcce043a5', versi: 4, nomor_pengajuan: 1,
    versi_berlaku: null,
    jalur_pengajuan: 'pic', self_approval: false, reviu_terlambat: false, status: 'draft', target: 100,
    target_pk: 95, selisih_target_pk: '-15.000000000000',
    nilai: 80, status_perhitungan: 'terhitung', sumber_nilai: 'manual', catatan: '', alasan_tidak_dapat_dihitung: null,
    komponen: [], prasyarat: { siap: false, alasan: ['Narasi belum lengkap.'] },
    persyaratan_bukti: [
        { id: 'laporan', nama: 'Laporan capaian', wajib: true, semua_mode_wajib: false, izinkan_file: true, izinkan_tautan: true, izinkan_teks: false,
            format_diizinkan: 'pdf', ukuran_maks_kb: 2048,
            pemenuhan: { terpenuhi: false, mode_terpenuhi: [], mode_kurang: ['file'], mode_dikecualikan: [], alasan_pengecualian: null } },
        { id: 'dokumentasi', nama: 'Dokumentasi kegiatan', wajib: false, semua_mode_wajib: false, izinkan_file: true, izinkan_tautan: true, izinkan_teks: true,
            format_diizinkan: 'pdf,jpg', ukuran_maks_kb: 2048,
            pemenuhan: { terpenuhi: true, mode_terpenuhi: ['tautan'], mode_kurang: [], mode_dikecualikan: [], alasan_pengecualian: null } },
    ],
    can: { view: true, evidence: true, uploadEvidence: true, update: true, submit: true, verify: false, ratify: false, return: false },
    penugasan_indikator: {
        indikator_kinerja: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', definisi_operasional: null, tipe_perhitungan: 'manual', arah: 'naik_baik', presisi: 4, desimal_tampilan: 2 },
        unit_kerja: { id: 'ed27dabf-8d26-4e48-935b-07d913d8a49a', nama: 'Unit Uji' },
        pic: { id: 'ae1c7f4c-85b9-4db6-b89c-9951f91ab58e', nama: 'PIC Uji' },
    },
    periode_jadwal: { id: '0d7029ba-96e0-4343-986b-d1ca723c17e1', urutan: 1, nama_periode: 'Semester I', tahun: 2026, is_nilai_akhir: false, tanggal: { bulan_mulai: 1, hari_mulai: 1, bulan_selesai: 6, hari_selesai: 30 } },
    bukti_dukungs: [
        { id: 'bukti-tautan', jenis_berkas_id: 'dokumentasi', menggantikan_id: null, alasan_koreksi: null, mode: 'tautan', nama_asli: null, mime: null, ukuran_bytes: null,
            tautan: 'https://contoh.go.id/dokumentasi', isi_teks: null, download_url: 'https://contoh.go.id/dokumentasi' },
    ],
    bukti_count: 1, riwayats: [], snapshot: null,
};

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, data: JSON.stringify({ nilai: null, status_perhitungan: 'belum_diisi' }), headers: {} });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const sebelum = (a: Element, b: Element) => Boolean(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING);

describe('Tata letak Pengisian Pengukuran', () => {
    it('menaruh tautan kembali ke daftar di atas konten, bukan di area judul', () => {
        render(<PengukuranEdit pengukuran={pengukuran} />);
        const kembali = screen.getByRole('link', { name: 'Kembali ke daftar' });
        expect(kembali.getAttribute('href')).toBe('/pengukuran');
        expect(screen.getByRole('main').contains(kembali)).toBe(true);
        expect(screen.getByRole('banner').childElementCount).toBe(0);
    });

    it('menomori kartu isian berurutan: nilai realisasi, narasi, bukti dukung', () => {
        render(<PengukuranEdit pengukuran={pengukuran} />);
        const langkah = ['Nilai realisasi', 'Narasi', 'Bukti dukung'].map((nama) => screen.getByRole('heading', { name: nama }));
        langkah.forEach((judul, index) => expect(judul.textContent).toBe(`${index + 1}${['Nilai realisasi', 'Narasi', 'Bukti dukung'][index]}`));
        expect(sebelum(langkah[0], langkah[1]) && sebelum(langkah[1], langkah[2])).toBe(true);
    });

    it('mengumpulkan ringkasan nilai, kesiapan pengajuan, dan tombol di panel samping setelah isian', () => {
        render(<PengukuranEdit pengukuran={pengukuran} />);
        const panel = screen.getByRole('complementary', { name: 'Ringkasan pengukuran' });
        expect(within(panel).getByText('Hasil terakhir tersimpan').parentElement?.textContent).toContain('80,00 %');
        expect(within(panel).getByText('Target PK tahunan').parentElement?.textContent).toContain('95,00 %');
        expect(within(panel).getByText('Selisih terhadap target PK').parentElement?.textContent).toContain('-15,00 %');
        expect(within(panel).getByRole('heading', { name: 'Kesiapan pengajuan' })).toBeTruthy();
        expect(within(panel).getByText('Narasi belum lengkap.')).toBeTruthy();
        expect(within(panel).getByRole('button', { name: 'Simpan Draft' })).toBeTruthy();
        expect(within(panel).getByRole('button', { name: 'Ajukan ke Tim Perencanaan' })).toBeTruthy();
        // Urutan DOM menentukan urutan di HP: panel turun ke bawah kartu bukti dukung.
        expect(sebelum(screen.getByRole('heading', { name: 'Bukti dukung' }), panel)).toBe(true);
    });

    it('menampilkan pemberitahuan dalam satu bagian berlabel', () => {
        render(<PengukuranEdit pengukuran={{ ...pengukuran, status: 'disahkan', can: { ...pengukuran.can, update: false, submit: false } }} />);
        const pemberitahuan = screen.getByRole('region', { name: 'Pemberitahuan' });
        expect(within(pemberitahuan).getByText('Formulir hanya dapat dibaca sesuai status dan izin akses Anda.')).toBeTruthy();
    });

    it('menampilkan status persyaratan bukti sebagai badge dan mode tanpa istilah internal', () => {
        render(<PengukuranEdit pengukuran={pengukuran} />);
        const persyaratan = screen.getByRole('list', { name: 'Pemenuhan persyaratan bukti' });
        const laporan = within(persyaratan).getByText('Laporan capaian').closest('li') as HTMLElement;
        expect(within(laporan).getByText('Wajib')).toBeTruthy();
        expect(within(laporan).getByText('Belum terpenuhi')).toBeTruthy();
        expect(within(laporan).getByText(/Kurang: File/)).toBeTruthy();
        const dokumentasi = within(persyaratan).getByText('Dokumentasi kegiatan').closest('li') as HTMLElement;
        expect(within(dokumentasi).getByText('Opsional')).toBeTruthy();
        expect(within(dokumentasi).getByText('Terpenuhi')).toBeTruthy();
        const tersimpan = screen.getByRole('list', { name: 'Bukti dukung tersimpan' });
        expect(within(tersimpan).getByRole('link', { name: /Buka tautan/ }).getAttribute('href')).toBe('https://contoh.go.id/dokumentasi');
        expect(document.body.textContent).not.toMatch(/\((file|tautan|teks)\)/);
    });
});
