import type { ReactNode } from 'react';
import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http, type HttpResponse } from '@inertiajs/core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { StatusPerhitunganBadge, statusPerhitunganBadgeMap } from '@/Components/Pengukuran/StatusPerhitunganBadge';
import CalculationPreview from '@/Pages/Pengukuran/CalculationPreview';
import PengukuranEdit from '@/Pages/Pengukuran/Edit';
import PengukuranIndex from '@/Pages/Pengukuran/Index';
import VerifikasiIndex from '@/Pages/Verifikasi/Index';
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
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, data: JSON.stringify({ nilai: null, status_perhitungan: 'belum_diisi' }), headers: {} });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe('StatusPerhitunganBadge', () => {
    it('memberi label berbeda untuk status tanpa nilai dan teks polos untuk terhitung', () => {
        render(<><StatusPerhitunganBadge status="belum_diisi" /><StatusPerhitunganBadge status="tidak_dapat_dihitung" /><StatusPerhitunganBadge status="belum_dapat_diukur" /></>);
        expect(screen.getByText('Belum Diisi')).toBeTruthy();
        expect(screen.getByText('Tidak Dapat Dihitung')).toBeTruthy();
        expect(screen.getByText('Belum Dapat Diukur')).toBeTruthy();
        cleanup();
        const { container } = render(<StatusPerhitunganBadge status="terhitung" />);
        expect(container.textContent).toBe('Terhitung');
        expect(container.querySelector('span')).toBeNull();
    });

    it('memakai varian badge yang ditetapkan', () => {
        expect(statusPerhitunganBadgeMap).toEqual({
            belum_diisi: { variant: 'muted', label: 'Belum Diisi' },
            tidak_dapat_dihitung: { variant: 'secondary', label: 'Tidak Dapat Dihitung' },
            belum_dapat_diukur: { variant: 'warning', label: 'Belum Dapat Diukur' },
        });
    });
});

const frozenTdd: Pengukuran = { ...measurement, status: 'diajukan', sumber_nilai: 'komponen', nilai: null, status_perhitungan: 'tidak_dapat_dihitung', alasan_tidak_dapat_dihitung: 'Populasi nol.',
    can: { ...measurement.can, update: false, submit: false, verify: true } };
const pagination = { current_page: 1, last_page: 1, total: 3, prev_page_url: null, next_page_url: null };
const tw1 = measurement.periode_jadwal;
const konteksBaris = {
    indikator_id: 'ind-1', jadwal_id: 'jad-1', jadwal_snapshot_id: 'snap-1', tahun: 2026, periode: tw1, pic: null,
    indikator: { kode: 'IKU-UJI', nama: 'Indikator Uji', satuan: '%', desimal_tampilan: 2 }, unit: { id: 'unit-1', nama: 'Unit Uji' },
};
const ringkasan = { id: measurement.id, status: 'draft' as const, nilai: 80, status_perhitungan: 'terhitung' as const, target: 100, bukti_count: 0, self_approval: false, reviu_terlambat: false };
const baris = (kode: string, row: Partial<BarisPengukuran>): BarisPengukuran => ({ ...konteksBaris, indikator_id: kode, indikator: { ...konteksBaris.indikator, kode }, ...row } as BarisPengukuran);
const indexProps = (pengukurans: BarisPengukuran[], extra: Record<string, unknown> = {}) => ({
    konteks: [{ jadwal_id: 'jad-1', tahun: 2026, periode: tw1 }], filters: { jadwal_id: null, periode_id: null }, can: { selectPeriod: false },
    pengukurans, pagination: { links: [], from: 1, to: pengukurans.length, total: pengukurans.length }, ...extra,
});

describe('Badge status perhitungan di halaman verifikasi', () => {
    it('Verifikasi Show menampilkan badge TDD dan alasan beku; BDD berlabel berbeda', () => {
        const { rerender } = render(<VerifikasiShow pengukuran={frozenTdd} />);
        expect(screen.getByText('Tidak Dapat Dihitung')).toBeTruthy();
        expect(screen.getByText('Alasan hasil tidak dapat dihitung')).toBeTruthy();
        expect(screen.getByText('Populasi nol.')).toBeTruthy();
        rerender(<VerifikasiShow pengukuran={{ ...frozenTdd, status_perhitungan: 'belum_dapat_diukur', alasan_tidak_dapat_dihitung: null }} />);
        expect(screen.getByText('Belum Dapat Diukur')).toBeTruthy();
        expect(screen.queryByText('Tidak Dapat Dihitung')).toBeNull();
    });

    it('Verifikasi Index memberi badge pada TDD dan BDD; terhitung tetap teks', () => {
        render(<VerifikasiIndex pagination={pagination} pengukurans={[
            frozenTdd,
            { ...frozenTdd, id: 'bdd', status_perhitungan: 'belum_dapat_diukur', alasan_tidak_dapat_dihitung: null },
            { ...frozenTdd, id: 'angka', nilai: 80, status_perhitungan: 'terhitung', alasan_tidak_dapat_dihitung: null },
        ]} />);
        expect(screen.getByText('Tidak Dapat Dihitung')).toBeTruthy();
        expect(screen.getByText('Belum Dapat Diukur')).toBeTruthy();
        expect(screen.getByText('Terhitung')).toBeTruthy();
        expect(screen.getAllByText('—')).toHaveLength(2);
    });

    it('hasil tersimpan TDD di Edit tampil sebagai badge', () => {
        render(<PengukuranEdit pengukuran={frozenTdd} />);
        expect(screen.getByText('Tidak Dapat Dihitung')).toBeTruthy();
    });

    it('Pengukuran Index memberi badge pada TDD dan BDD; terhitung tetap teks', () => {
        render(<PengukuranIndex {...indexProps([
            baris('IKU-TDD', { jenis: 'existing', pengukuran: { ...ringkasan, id: 'tdd', nilai: null, status_perhitungan: 'tidak_dapat_dihitung' }, can: { create: false } }),
            baris('IKU-BDD', { jenis: 'existing', pengukuran: { ...ringkasan, id: 'bdd', nilai: null, status_perhitungan: 'belum_dapat_diukur' }, can: { create: false } }),
            baris('IKU-ANGKA', { jenis: 'existing', pengukuran: ringkasan, can: { create: false } }),
        ])} />);
        // StatusBaris dirender dua kali per baris (kartu mobile dan sel desktop).
        expect(screen.getAllByText('Tidak Dapat Dihitung')).toHaveLength(2);
        expect(screen.getAllByText('Belum Dapat Diukur')).toHaveLength(2);
        expect(screen.getAllByText('Terhitung · 0 bukti')).toHaveLength(2);
    });
});

const alasan = () => screen.queryByRole<HTMLTextAreaElement>('textbox', { name: /Alasan bila hasil/ });
const pratinjau = (status: Pengukuran['status_perhitungan'], nilai: string | null = null) =>
    vi.mocked(http.getClient().request).mockResolvedValue({ status: 200, data: JSON.stringify({ nilai, status_perhitungan: status }), headers: {} });
const tertahan = () => vi.mocked(http.getClient().request).mockImplementation(() => new Promise(() => {}));
const rasio: Pengukuran = { ...measurement, sumber_nilai: 'komponen', nilai: 50, status_perhitungan: 'terhitung', komponen: [
    { komponen_id: 'n', kode: 'N', label: 'Pembilang', peran: 'pembilang', bobot: '1', nilai: '5' },
    { komponen_id: 't', kode: 'T', label: 'Penyebut', peran: 'penyebut', bobot: '1', nilai: '10' }] };
const tersimpanTdd: Pengukuran = { ...rasio, nilai: null, status_perhitungan: 'tidak_dapat_dihitung', alasan_tidak_dapat_dihitung: 'Populasi nol.',
    komponen: rasio.komponen.map((item) => item.komponen_id === 't' ? { ...item, nilai: '0' } : item) };

describe('Field alasan penyebut nol', () => {
    it('pratinjau terhitung mengungguli status tersimpan TDD', async () => {
        pratinjau('terhitung', '75.00');
        render(<PengukuranEdit pengukuran={tersimpanTdd} />);
        await screen.findByText('75,00 %');
        expect(alasan()).toBeNull();
    });

    it('tampil saat pratinjau TDD; teks tetap terkirim setelah pratinjau kembali terhitung', async () => {
        const user = userEvent.setup();
        pratinjau('tidak_dapat_dihitung');
        render(<PengukuranEdit pengukuran={rasio} />);
        await user.type(await screen.findByRole('textbox', { name: /Alasan bila hasil/ }), 'Populasi nol.');
        pratinjau('terhitung', '75.00');
        const penyebut = screen.getByRole('spinbutton', { name: /Penyebut/ });
        await user.clear(penyebut);
        await user.type(penyebut, '8');
        await waitFor(() => expect(alasan()).toBeNull());
        await user.click(screen.getByRole('button', { name: 'Simpan Draft' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ alasan_tidak_dapat_dihitung: 'Populasi nol.' });
    });

    it('tampil untuk status tersimpan TDD sebelum pratinjau selesai', () => {
        tertahan();
        render(<PengukuranEdit pengukuran={tersimpanTdd} />);
        expect(alasan()?.value).toBe('Populasi nol.');
        expect(screen.getByText('Wajib diisi sebelum pengajuan.')).toBeTruthy();
    });

    it('tersembunyi setelah Belum dapat diukur dicentang', async () => {
        tertahan();
        render(<PengukuranEdit pengukuran={tersimpanTdd} />);
        await userEvent.setup().click(screen.getByRole('switch', { name: 'Belum dapat diukur' }));
        expect(alasan()).toBeNull();
    });

    it('pratinjau lama diabaikan setelah props ID sama menjadi baca-saja', async () => {
        pratinjau('terhitung', '75.00');
        const { rerender } = render(<PengukuranEdit pengukuran={tersimpanTdd} />);
        await screen.findByText('75,00 %');
        expect(alasan()).toBeNull();
        // Simpan yang ditolak memuat ulang props tanpa remount (useForm POST mempertahankan state).
        rerender(<PengukuranEdit pengukuran={{ ...tersimpanTdd, status: 'diajukan', can: { ...measurement.can, update: false, submit: false } }} />);
        expect(alasan()?.disabled).toBe(true);
    });

    it('centang Belum dapat diukur lokal diabaikan setelah props ID sama menjadi baca-saja', async () => {
        tertahan();
        const { rerender } = render(<PengukuranEdit pengukuran={tersimpanTdd} />);
        await userEvent.setup().click(screen.getByRole('switch', { name: 'Belum dapat diukur' }));
        expect(alasan()).toBeNull();
        rerender(<PengukuranEdit pengukuran={{ ...tersimpanTdd, status: 'diajukan', can: { ...measurement.can, update: false, submit: false } }} />);
        expect(alasan()?.disabled).toBe(true);
    });

    it('mode baca-saja menampilkan alasan beku tanpa pratinjau', () => {
        render(<PengukuranEdit pengukuran={{ ...tersimpanTdd, status: 'diajukan', can: { ...measurement.can, update: false, submit: false } }} />);
        expect(alasan()?.disabled).toBe(true);
        expect(alasan()?.value).toBe('Populasi nol.');
        expect(screen.queryByText('Pratinjau · belum disimpan')).toBeNull();
    });

    it('pratinjau gagal tidak mempertahankan hasil sukses untuk input lama', async () => {
        const user = userEvent.setup();
        pratinjau('terhitung', '75.00');
        const { rerender } = render(<PengukuranEdit pengukuran={rasio} />);
        await screen.findByText('75,00 %');
        vi.mocked(http.getClient().request).mockRejectedValue(new Error('Koneksi putus'));
        const penyebut = screen.getByRole('spinbutton', { name: /Penyebut/ });
        await user.clear(penyebut);
        await user.type(penyebut, '0');
        await screen.findByText(/Pratinjau belum tersedia/);
        // Simpan Draft memuat ulang props tanpa remount; server kini menyimpan TDD tanpa alasan.
        rerender(<PengukuranEdit pengukuran={{ ...tersimpanTdd, versi: rasio.versi + 1, alasan_tidak_dapat_dihitung: null }} />);
        expect(alasan()).not.toBeNull();
    });

    it('pratinjau TDD lama tidak memunculkan field setelah Belum dapat diukur dilepas', async () => {
        const user = userEvent.setup();
        pratinjau('tidak_dapat_dihitung');
        render(<PengukuranEdit pengukuran={rasio} />);
        await screen.findByRole('textbox', { name: /Alasan bila hasil/ });
        tertahan();
        await user.click(screen.getByRole('switch', { name: 'Belum dapat diukur' }));
        await user.click(screen.getByRole('switch', { name: 'Belum dapat diukur' }));
        expect(alasan()).toBeNull();
    });

    it('CalculationPreview melaporkan hasil terbaru dan mengosongkannya saat gagal', async () => {
        const onResult = vi.fn();
        const request = vi.mocked(http.getClient().request);
        let finishOld: ((response: HttpResponse) => void) | undefined;
        request.mockImplementationOnce(() => new Promise((resolve) => { finishOld = resolve; }));
        request.mockResolvedValueOnce({ status: 200, data: JSON.stringify({ nilai: null, status_perhitungan: 'tidak_dapat_dihitung' }), headers: {} });
        const props = { id: 'uji', satuan: '%', desimalTampilan: 2, paused: false, onRecovery: vi.fn(), onResult };
        const { rerender } = render(<CalculationPreview {...props} komponen={[{ komponen_id: 'n', nilai: '5' }]} />);
        await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
        rerender(<CalculationPreview {...props} komponen={[{ komponen_id: 'n', nilai: '0' }]} />);
        await screen.findByText('Tidak Dapat Dihitung');
        await act(async () => { finishOld?.({ status: 200, data: JSON.stringify({ nilai: '50.00', status_perhitungan: 'terhitung' }), headers: {} }); });
        request.mockRejectedValueOnce(new Error('Koneksi putus'));
        rerender(<CalculationPreview {...props} komponen={[{ komponen_id: 'n', nilai: '' }]} />);
        await screen.findByText(/Pratinjau belum tersedia/);
        expect(onResult.mock.calls).toEqual([['tidak_dapat_dihitung'], [null]]);
    });
});
