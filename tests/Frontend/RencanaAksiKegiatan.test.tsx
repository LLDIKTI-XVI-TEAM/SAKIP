import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import KegiatanRencanaAksi from '@/Pages/RencanaAksi/Kegiatan';
import type { KegiatanRencanaAksiItem, RencanaAksiKegiatan, RencanaAksiPeriode, RencanaAksiShow } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rencana-aksi/ra-1/kegiatan', props: { auth: {}, pengaturan: {}, flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => { callback(); return 0; });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const RA_ID = 'ra-1';
const P1 = 'tw1';
const P2 = 'tw2';
const P3 = 'tw3';

function periode(id: string, nama: string, urutan: number): RencanaAksiPeriode {
    return {
        id, nama, urutan, efektif: true, status: 'efektif', nilai: [], skor: { nilai: null, status_perhitungan: 'belum_diisi' },
        peringatan_turun: false, komponen_turun: [], tanggal: null, batas_terkunci_pic: null, boleh_tanpa_target: false,
    };
}

function buatRencanaAksi(ubah: Partial<RencanaAksiShow> = {}): RencanaAksiShow {
    return {
        id: RA_ID,
        tahun: 2026,
        status_alur: 'draft',
        versi: 1,
        expected_versi: 1,
        expected_snapshot_id: '55555555-5555-4555-8555-555555555555',
        expected_snapshot_versi: 1,
        uraian: null,
        alasan_deviasi_pk: null,
        indikator: {
            id: 'indikator-uji', kode: 'I-UJI', nama: 'Indikator Uji', satuan: 'poin', arah: 'naik_baik', tipe_perhitungan: 'manual',
            presisi: 2, desimal_tampilan: 2, status: 'aktif', tahun_mulai_berlaku: 2025, dinilai_tahunan: false,
        },
        unit: { id: 'unit-uji', nama: 'Unit Uji' },
        jadwal: { id: 'jadwal-uji', tahun: 2026, status: 'aktif', penutupan: '2026-12-31' },
        penanggung_jawab: { id: 'pic-uji', nama: 'PIC Uji' },
        tipe_perhitungan: 'manual',
        presisi: 2,
        target_pk: '100',
        baseline: null,
        komponen: [],
        periode: [periode(P1, 'Triwulan I', 1), periode(P2, 'Triwulan II', 2), periode(P3, 'Triwulan III', 3)],
        koreksi: { aktif: false, periode_ids: [] },
        // Bawaan: seluruh periode efektif terbuka; test yang mengunci periode menimpanya lewat `ubah`.
        periode_dapat_ditulis: [P1, P2, P3],
        deviasi_pk: { dapat_dinilai: false, ada: false, alasan_diperlukan: false, alasan_terisi: false, skor_periode_terakhir: null, target_pk: null, periode_id: null },
        pengajuan: { siap: true, alasan: [] },
        versi_pengajuan: null,
        can: { view: true, update: false, submit: false },
        ...ubah,
    };
}

function baris(ubah: Partial<KegiatanRencanaAksiItem> & Pick<KegiatanRencanaAksiItem, 'id' | 'kegiatan_id' | 'periode_id'>): KegiatanRencanaAksiItem {
    return {
        nama: 'Kegiatan uji', status: 'rencana', komponen_id: null, arah_dampak: 'menambah', catatan: null, anggaran: '0',
        sumber_klaim: 'rencana_aksi', kegiatan_bersama: false,
        ...ubah,
    };
}

const TOTAL_KOSONG: RencanaAksiKegiatan['total'] = { total: '0', belum_diisi: 0, per_periode: {} };
const tanpaDaftar: RencanaAksiKegiatan = { daftar: [], total: TOTAL_KOSONG, can: { tambah: false, kelola: false } };

it('menampilkan state tanpa data, bukan galat, ketika aktor tidak berhak membaca kegiatan', () => {
    render(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={null} />);

    // Header RA tetap tampil sehingga halaman dapat dinavigasi.
    expect(screen.getByText('I-UJI · Indikator Uji')).toBeTruthy();
    expect(screen.getByText('Kegiatan tidak dapat ditampilkan')).toBeTruthy();
    expect(screen.getByText(/tidak memiliki izin membaca kegiatan/)).toBeTruthy();
    expect(screen.queryByText('Kegiatan per Periode')).toBeNull();
    expect(screen.queryByRole('button', { name: /Tambah kegiatan/ })).toBeNull();
});

it('menawarkan tambah hanya pada periode terbuka bila server memberi capability', () => {
    const { rerender } = render(
        <KegiatanRencanaAksi rencanaAksi={buatRencanaAksi({ periode_terkunci: [P1], periode_dapat_ditulis: [P2, P3] })} kegiatan={{ ...tanpaDaftar, can: { tambah: true, kelola: true } }} />,
    );

    expect(screen.queryByRole('button', { name: /Tambah kegiatan Triwulan I$/ })).toBeNull();
    expect(screen.getByRole('button', { name: /Tambah kegiatan Triwulan II$/ })).toBeTruthy();
    // Periode terkunci tetap terbaca sebagai baris baca-saja. Lookahead negatif memastikan
    // hanya "Triwulan I" yang cocok, bukan "Triwulan II"/"Triwulan III" (awalan sama).
    expect(within(screen.getByRole('region', { name: /^Triwulan I(?!I)/ })).getByText('Terkunci')).toBeTruthy();

    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={{ ...tanpaDaftar, can: { tambah: false, kelola: false } }} />);
    expect(screen.queryByRole('button', { name: /Tambah kegiatan/ })).toBeNull();
});

it('menyembunyikan tambah ketika seluruh periode efektif terkunci bagi aktor', () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi({ periode_terkunci: [P1, P2, P3], periode_dapat_ditulis: [] })}
            kegiatan={{ ...tanpaDaftar, can: { tambah: true, kelola: true } }}
        />,
    );

    expect(screen.queryByRole('button', { name: /Tambah kegiatan/ })).toBeNull();
    for (const nama of ['Triwulan I', 'Triwulan II', 'Triwulan III']) {
        // Lookahead negatif mencegah "Triwulan I" ikut mencocokkan "Triwulan II"/"Triwulan III".
        expect(within(screen.getByRole('region', { name: new RegExp(`^${nama}(?!I)`) })).getByText('Terkunci')).toBeTruthy();
    }
});

it('menampilkan total anggaran dan menandai kegiatan yang anggarannya belum diisi', () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi()}
            kegiatan={{
                daftar: [
                    baris({ id: 'k1', kegiatan_id: 'keg-1', periode_id: P1, nama: 'Workshop', anggaran: '1500000' }),
                    baris({ id: 'k2', kegiatan_id: 'keg-2', periode_id: P1, nama: 'Rapat', anggaran: null }),
                ],
                total: { total: '1500000', belum_diisi: 1, per_periode: { [P1]: { total: '1500000', belum_diisi: 1 } } },
                can: { tambah: false, kelola: false },
            }}
        />,
    );

    expect(screen.getByText(/Total anggaran rencana aksi:/).textContent).toMatch(/Rp\s1\.500\.000 \(1 kegiatan belum diisi\)/);
    expect(screen.getByText(/^Total Rp\s?1\.500\.000 \(1 kegiatan belum diisi\)$/)).toBeTruthy();
    // Baris tanpa anggaran tetap terlihat sebagai "belum diisi", bukan Rp0.
    expect(screen.getByText('Anggaran: Belum diisi')).toBeTruthy();
});

it('menampilkan galat validasi server pada modal tambah tanpa membuang draf', async () => {
    render(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={{ ...tanpaDaftar, can: { tambah: true, kelola: false } }} />);

    fireEvent.click(screen.getByRole('button', { name: /Tambah kegiatan Triwulan I$/ }));
    const dialog = within(screen.getByRole('dialog'));
    fireEvent.change(dialog.getByLabelText(/Nama kegiatan/), { target: { value: 'Draf yang harus dipertahankan' } });
    fireEvent.submit(dialog.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);

    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onError?.({ nama: 'Nama kegiatan wajib diisi.' }); });

    expect(dialog.getByText('Nama kegiatan wajib diisi.')).toBeTruthy();
    expect(dialog.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Draf yang harus dipertahankan');
    expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/rencana-aksi/ra-1/kegiatan');
});

it('mengirim anggaran kosong sebagai null, membatasi nama 500 karakter, dan menutup modal sesudah sukses', async () => {
    render(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={{ ...tanpaDaftar, can: { tambah: true, kelola: false } }} />);

    fireEvent.click(screen.getByRole('button', { name: /Tambah kegiatan Triwulan I$/ }));
    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.getByLabelText<HTMLInputElement>(/Nama kegiatan/).maxLength).toBe(500);
    fireEvent.change(dialog.getByLabelText(/Nama kegiatan/), { target: { value: 'Rencana baru' } });
    fireEvent.submit(dialog.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);

    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/rencana-aksi/ra-1/kegiatan');
    expect(data).toMatchObject({ periode_id: P1, nama: 'Rencana baru', sasaran_peserta: null, anggaran: null });
    expect(data).not.toHaveProperty('status');

    await act(async () => { await options?.onSuccess?.({ props: { errors: {} } } as never); });
    expect(screen.queryByRole('dialog')).toBeNull();
});

it('memberi tahu penolakan izin tambah kegiatan tanpa menutup modal', async () => {
    render(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={{ ...tanpaDaftar, can: { tambah: true, kelola: false } }} />);

    fireEvent.click(screen.getByRole('button', { name: /Tambah kegiatan Triwulan I$/ }));
    const dialog = within(screen.getByRole('dialog'));
    fireEvent.change(dialog.getByLabelText(/Nama kegiatan/), { target: { value: 'Kegiatan baru' } });
    fireEvent.submit(dialog.getByRole('button', { name: 'Simpan kegiatan' }).closest('form')!);

    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onHttpException?.({ status: 403, data: {} } as never); });

    expect(dialog.getByRole('alert').textContent).toContain('Izin menambah kegiatan ditolak.');
    expect(dialog.getByLabelText<HTMLInputElement>(/Nama kegiatan/).value).toBe('Kegiatan baru');
});
