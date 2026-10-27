import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import KegiatanRencanaAksi from '@/Pages/RencanaAksi/Kegiatan';
import type { KegiatanRencanaAksiItem, KegiatanTanpaKlaim, RencanaAksiKegiatan, RencanaAksiPeriode, RencanaAksiShow } from '@/Pages/RencanaAksi/types';

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

function kandidatTautan(ubah: Partial<KegiatanTanpaKlaim> & Pick<KegiatanTanpaKlaim, 'id' | 'periode_id'>): KegiatanTanpaKlaim {
    return { nama: 'Kandidat kegiatan', status: 'rencana', kegiatan_asal_id: null, periode_nama: 'Triwulan I', ...ubah };
}

/**
 * Payload kegiatan dengan kandidat penautan. `tautkan` bawaan true karena
 * kartu hanya digerbangi capability server; server tetap penentu kelayakan.
 */
function kegiatanTautan(kandidatPenautan: KegiatanTanpaKlaim[], tautkan = true): RencanaAksiKegiatan {
    return { ...tanpaDaftar, kegiatan_tanpa_klaim: kandidatPenautan, can: { tambah: false, kelola: false, tautkan } };
}

/** Cakupan kartu penautan; setiap baris punya kontrol arah dampak dan tombolnya sendiri. */
type Kartu = ReturnType<typeof within>;

const arahBaris = (kartu: Kartu, nama: string) => kartu.getByLabelText(`Arah dampak kegiatan ${nama}`) as HTMLSelectElement;
const tombolTautkan = (kartu: Kartu, nama: string) => kartu.getByRole('button', { name: `Tautkan kegiatan ${nama} ke rencana aksi ini` }) as HTMLButtonElement;

/** Arah dampak wajib dipilih per baris sebelum penautan dapat dikirim. */
function pilihArahBaris(kartu: Kartu, nama: string, arah: string): void {
    fireEvent.change(arahBaris(kartu, nama), { target: { value: arah } });
}

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

it('memberi anchor id hanya pada baris pertama tiap kegiatan walau klaim komponennya lebih dari satu', () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi({ komponen: [{ komponen_id: 'komp-1', kode: 'P', label: 'Pembilang', peran: 'pembilang', bobot: '1', urutan: 1 }] })}
            kegiatan={{
                daftar: [
                    // Dua klaim untuk kegiatan yang sama (komponen berbeda) hanya boleh punya satu anchor.
                    baris({ id: 'k1', kegiatan_id: 'keg-1', periode_id: P1, nama: 'Workshop' }),
                    baris({ id: 'k2', kegiatan_id: 'keg-1', periode_id: P1, nama: 'Workshop', komponen_id: 'komp-1' }),
                    baris({ id: 'k3', kegiatan_id: 'keg-2', periode_id: P2, nama: 'Rapat' }),
                ],
                total: TOTAL_KOSONG,
                can: { tambah: false, kelola: false },
            }}
        />,
    );

    const [pertama, kedua, ketiga] = screen.getAllByRole('listitem');
    // Anchor menempel di baris pertama kegiatan (klaim tanpa komponen) dan dapat ditemukan lewat getElementById.
    expect(pertama.id).toBe('kegiatan-keg-1');
    expect(document.getElementById('kegiatan-keg-1')).toBe(pertama);
    expect(within(pertama).getByText('Tanpa komponen tertentu')).toBeTruthy();

    // Baris klaim komponen kedua untuk kegiatan yang sama tidak menduplikasi id.
    expect(within(kedua).getByText('P — Pembilang')).toBeTruthy();
    expect(kedua.id).toBe('');
    expect(document.querySelectorAll('[id="kegiatan-keg-1"]')).toHaveLength(1);

    // Kegiatan lain tetap mendapat anchor sendiri.
    expect(ketiga.id).toBe('kegiatan-keg-2');
    expect(document.getElementById('kegiatan-keg-2')).toBe(ketiga);
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

// Penautan kegiatan lama tanpa klaim lewat endpoint klaim yang sudah ada.

it('menyembunyikan kartu penautan tanpa capability, tanpa kandidat, atau tanpa periode yang dapat ditulis', () => {
    const kandidatP1 = kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Triwulan I' });
    const kartu = () => screen.queryByRole('region', { name: 'Kegiatan belum tertaut' });

    // Kontrol positif: kondisi awal (capability + kandidat pada periode terbuka) menampilkan kartu,
    // sehingga assertion penyembunyian di bawah benar-benar menguji gerbang, bukan kartu yang tak pernah ada.
    const { rerender } = render(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={kegiatanTautan([kandidatP1])} />);
    expect(kartu()).toBeTruthy();

    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={kegiatanTautan([kandidatP1], false)} />);
    expect(kartu()).toBeNull();

    // Tanpa kandidat penautan: kartu tidak dirender walau capability ada.
    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={kegiatanTautan([])} />);
    expect(kartu()).toBeNull();
    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={{ ...tanpaDaftar, can: { tambah: false, kelola: false, tautkan: true } }} />);
    expect(kartu()).toBeNull();

    // Payload lama tanpa `can.tautkan` dibaca sebagai tanpa izin.
    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={{ ...tanpaDaftar, kegiatan_tanpa_klaim: [kandidatP1] }} />);
    expect(kartu()).toBeNull();

    // Kandidat hanya dari periode di luar `periode_dapat_ditulis` tidak menampilkan kartu.
    rerender(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi({ periode_dapat_ditulis: [P1, P2] })}
            kegiatan={kegiatanTautan([kandidatTautan({ id: 'keg-3', periode_id: P3, nama: 'Kandidat Triwulan III', periode_nama: 'Triwulan III' })])}
        />,
    );
    expect(kartu()).toBeNull();

    // Seluruh periode terkunci bagi aktor: tidak ada tujuan penautan yang dapat ditulis.
    rerender(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi({ periode_terkunci: [P1, P2, P3], periode_dapat_ditulis: [] })}
            kegiatan={kegiatanTautan([kandidatP1])}
        />,
    );
    expect(kartu()).toBeNull();
});

it('hanya merender kandidat penautan dari periode yang dapat ditulis', () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi({ periode_dapat_ditulis: [P1, P2] })}
            kegiatan={kegiatanTautan([
                kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Triwulan I', periode_nama: 'Triwulan I' }),
                kandidatTautan({ id: 'keg-3', periode_id: P3, nama: 'Kandidat Triwulan III', periode_nama: 'Triwulan III' }),
                kandidatTautan({ id: 'keg-2', periode_id: P2, nama: 'Kandidat Triwulan II', periode_nama: 'Triwulan II' }),
            ])}
        />,
    );

    const kartu = within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));
    expect(kartu.getByText('Kandidat Triwulan I')).toBeTruthy();
    expect(kartu.getByText('Kandidat Triwulan II')).toBeTruthy();
    expect(kartu.queryByText('Kandidat Triwulan III')).toBeNull();
    expect(kartu.getAllByRole('listitem')).toHaveLength(2);
    expect(kartu.getAllByRole('button', { name: /^Tautkan kegiatan/ })).toHaveLength(2);
});

it('menampilkan nama, periode, status, dan pemilih arah dampak per kandidat penautan', () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi()}
            kegiatan={kegiatanTautan([
                kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Rapat koordinasi', periode_nama: 'Triwulan I', status: 'terlaksana' }),
                kandidatTautan({
                    id: 'keg-2', periode_id: P1, nama: 'Bimtek lanjutan', periode_nama: 'Triwulan I', status: 'ditunda', kegiatan_asal_id: 'keg-0',
                }),
            ])}
        />,
    );

    const kartu = within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));
    const [baru, lanjutan] = kartu.getAllByRole('listitem');
    expect(within(baru).getByText('Rapat koordinasi')).toBeTruthy();
    expect(within(baru).getByText('Triwulan I')).toBeTruthy();
    expect(within(baru).getByText('Terlaksana')).toBeTruthy();

    expect(within(lanjutan).getByText('Bimtek lanjutan')).toBeTruthy();
    expect(within(lanjutan).getByText('Triwulan I')).toBeTruthy();
    expect(within(lanjutan).getByText('Ditunda')).toBeTruthy();

    // Badge "Lanjutan" dihapus: `kegiatan_asal_id` tidak lagi dikonsumsi UI walau payload masih mengirimnya.
    expect(kartu.queryByText('Lanjutan')).toBeNull();

    // Setiap baris menawarkan pilihan arah dampak dengan placeholder, tanpa nilai bawaan tersembunyi.
    for (const [baris, nama] of [[baru, 'Rapat koordinasi'], [lanjutan, 'Bimtek lanjutan']] as const) {
        const arah = within(baris).getByLabelText<HTMLSelectElement>(`Arah dampak kegiatan ${nama}`);
        expect(arah.value).toBe('');
        expect(Array.from(arah.options, (opsi) => opsi.textContent)).toEqual(['Pilih arah dampak', 'Menambah', 'Mengurangi']);
    }
});

it('menonaktifkan tombol tautkan sampai arah dampak barisnya dipilih', () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi()}
            kegiatan={kegiatanTautan([
                kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Satu' }),
                kandidatTautan({ id: 'keg-2', periode_id: P2, nama: 'Kandidat Dua', periode_nama: 'Triwulan II' }),
            ])}
        />,
    );

    const kartu = within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));

    // Tanpa pilihan, tombol tidak dapat diklik dan tidak mengirim permintaan apa pun.
    expect(arahBaris(kartu, 'Kandidat Satu').value).toBe('');
    expect(tombolTautkan(kartu, 'Kandidat Satu').disabled).toBe(true);
    expect(tombolTautkan(kartu, 'Kandidat Dua').disabled).toBe(true);
    fireEvent.click(tombolTautkan(kartu, 'Kandidat Satu'));
    expect(router.post).not.toHaveBeenCalled();

    // Pilihan hanya membuka tombol baris itu; baris lain tetap menunggu arahnya sendiri.
    pilihArahBaris(kartu, 'Kandidat Satu', 'mengurangi');
    expect(tombolTautkan(kartu, 'Kandidat Satu').disabled).toBe(false);
    expect(tombolTautkan(kartu, 'Kandidat Dua').disabled).toBe(true);

    // Mengembalikan pilihan ke placeholder menutup lagi tombol baris tersebut.
    pilihArahBaris(kartu, 'Kandidat Satu', '');
    expect(tombolTautkan(kartu, 'Kandidat Satu').disabled).toBe(true);
});

it('mengirim klaim penautan dengan arah dampak pilihan baris dan menahan tombol selama proses', async () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi()}
            kegiatan={kegiatanTautan([
                kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Satu' }),
                kandidatTautan({ id: 'keg-2', periode_id: P2, nama: 'Kandidat Dua', periode_nama: 'Triwulan II' }),
            ])}
        />,
    );

    const kartu = within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));

    // Kedua baris dipilih lebih dulu agar status tombol baris lain hanya ditentukan oleh proses berjalan.
    pilihArahBaris(kartu, 'Kandidat Satu', 'mengurangi');
    pilihArahBaris(kartu, 'Kandidat Dua', 'menambah');
    expect(tombolTautkan(kartu, 'Kandidat Dua').disabled).toBe(false);

    fireEvent.click(tombolTautkan(kartu, 'Kandidat Satu'));

    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe('/kegiatan/keg-1/klaim');
    // Hanya klaim yang dikirim: RA tujuan, catatan penanda, dan arah dampak pilihan baris itu.
    const payload = data as { rencana_aksi_id?: string; arah_dampak?: string; catatan?: string };
    expect(Object.keys(payload).sort()).toEqual(['arah_dampak', 'catatan', 'rencana_aksi_id']);
    expect(payload).toMatchObject({ rencana_aksi_id: RA_ID, arah_dampak: 'mengurangi', catatan: 'Penautan kegiatan tanpa klaim.' });
    expect(options).toMatchObject({ preserveScroll: true });

    // Selama permintaan berjalan tombol aktif ditahan dan status proses terbaca pembaca layar.
    expect(tombolTautkan(kartu, 'Kandidat Satu').disabled).toBe(true);
    expect(tombolTautkan(kartu, 'Kandidat Satu').textContent).toContain('Menautkan…');
    expect(kartu.getByRole('status').textContent).toBe('Menautkan kegiatan Kandidat Satu…');
    expect(kartu.getByText('Kandidat Satu').closest('li')?.getAttribute('aria-busy')).toBe('true');
    // Kandidat lain ikut ditahan agar dua penautan tidak berjalan bersamaan.
    expect(tombolTautkan(kartu, 'Kandidat Dua').disabled).toBe(true);

    // Klik berulang selama proses tidak mengirim permintaan kedua.
    fireEvent.click(tombolTautkan(kartu, 'Kandidat Satu'));
    expect(router.post).toHaveBeenCalledTimes(1);

    await act(async () => { vi.mocked(router.post).mock.calls[0][2]?.onFinish?.(undefined as never); });
    expect(tombolTautkan(kartu, 'Kandidat Satu').disabled).toBe(false);
    expect(tombolTautkan(kartu, 'Kandidat Satu').textContent).toContain('Tautkan');
    expect(kartu.getByRole('status').textContent).toBe('');
    expect(kartu.getByText('Kandidat Satu').closest('li')?.getAttribute('aria-busy')).toBe('false');

    // Baris lain membawa pilihan arahnya sendiri, bukan nilai baris pertama.
    fireEvent.click(tombolTautkan(kartu, 'Kandidat Dua'));
    const [urlKedua, dataKedua] = vi.mocked(router.post).mock.calls[1];
    expect(urlKedua).toBe('/kegiatan/keg-2/klaim');
    expect((dataKedua as { arah_dampak?: string }).arah_dampak).toBe('menambah');
});

it('menampilkan galat arah dampak pada field barisnya tanpa alert umum', async () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi()}
            kegiatan={kegiatanTautan([
                kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Satu' }),
                kandidatTautan({ id: 'keg-2', periode_id: P2, nama: 'Kandidat Dua', periode_nama: 'Triwulan II' }),
            ])}
        />,
    );

    const kartu = within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));
    pilihArahBaris(kartu, 'Kandidat Satu', 'menambah');
    fireEvent.click(tombolTautkan(kartu, 'Kandidat Satu'));

    await act(async () => {
        vi.mocked(router.post).mock.calls[0][2]?.onError?.({ arah_dampak: 'Arah dampak tidak sesuai jenjang kegiatan.' });
        vi.mocked(router.post).mock.calls[0][2]?.onFinish?.(undefined as never);
    });

    const arahSatu = arahBaris(kartu, 'Kandidat Satu');
    const deskripsi = document.getElementById(arahSatu.getAttribute('aria-describedby') ?? '');
    expect(arahSatu.getAttribute('aria-invalid')).toBe('true');
    expect(deskripsi?.getAttribute('role')).toBe('alert');
    expect(deskripsi?.textContent).toBe('Arah dampak tidak sesuai jenjang kegiatan.');
    // Hanya galat field yang tampil; alert umum tidak menduplikasi pesan yang sama.
    expect(kartu.getAllByRole('alert').map((alert) => alert.textContent)).toEqual(['Arah dampak tidak sesuai jenjang kegiatan.']);
    // Galat milik baris ini saja, bukan seluruh kartu.
    expect(arahBaris(kartu, 'Kandidat Dua').getAttribute('aria-invalid')).toBeNull();

    // Memilih arah baru membersihkan galat lama yang merujuk pilihan sebelumnya.
    pilihArahBaris(kartu, 'Kandidat Satu', 'mengurangi');
    expect(arahBaris(kartu, 'Kandidat Satu').getAttribute('aria-invalid')).toBeNull();
    expect(kartu.queryByText('Arah dampak tidak sesuai jenjang kegiatan.')).toBeNull();
});

it('menampilkan galat 422, penolakan 403, dan gangguan koneksi penautan tanpa menelannya', async () => {
    render(
        <KegiatanRencanaAksi
            rencanaAksi={buatRencanaAksi()}
            kegiatan={kegiatanTautan([
                kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Satu' }),
                kandidatTautan({ id: 'keg-2', periode_id: P2, nama: 'Kandidat Dua', periode_nama: 'Triwulan II' }),
            ])}
        />,
    );

    const kartu = within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));
    // Arah dipilih sekali per baris; nilai itu dipakai ulang oleh setiap klik berikutnya.
    pilihArahBaris(kartu, 'Kandidat Satu', 'menambah');
    pilihArahBaris(kartu, 'Kandidat Dua', 'mengurangi');
    const klik = (nama: string) => fireEvent.click(tombolTautkan(kartu, nama));
    const opsi = (indeks: number) => vi.mocked(router.post).mock.calls[indeks][2];

    // 422 terikat ke `rencana_aksi_id` (mis. RA tujuan sudah tidak dapat diklaim).
    klik('Kandidat Satu');
    await act(async () => {
        opsi(0)?.onError?.({ rencana_aksi_id: 'Rencana aksi tujuan tidak dapat diklaim.' });
        opsi(0)?.onFinish?.(undefined as never);
    });
    expect(kartu.getByRole('alert').textContent).toBe('Rencana aksi tujuan tidak dapat diklaim.');

    // 422 terikat ke `jendela` (periode tujuan sudah ditutup).
    klik('Kandidat Satu');
    await act(async () => {
        opsi(1)?.onError?.({ jendela: 'Jendela penulisan periode sudah ditutup.' });
        opsi(1)?.onFinish?.(undefined as never);
    });
    expect(kartu.getByRole('alert').textContent).toBe('Jendela penulisan periode sudah ditutup.');

    // 403 tidak ditelan menjadi sukses; pesan izin tetap terlihat.
    klik('Kandidat Dua');
    await act(async () => {
        opsi(2)?.onHttpException?.({ status: 403, data: {}, headers: {} } as never);
        opsi(2)?.onFinish?.(undefined as never);
    });
    expect(kartu.getByRole('alert').textContent).toBe('Izin menautkan kegiatan ditolak. Muat ulang halaman untuk melihat akses terbaru.');

    // Koneksi terputus: hasil belum pasti, jadi pengguna diminta memuat ulang.
    klik('Kandidat Dua');
    await act(async () => {
        opsi(3)?.onNetworkError?.({} as never);
        opsi(3)?.onFinish?.(undefined as never);
    });
    expect(kartu.getByRole('alert').textContent).toBe('Koneksi terputus. Muat ulang halaman untuk memastikan kegiatan sudah tertaut.');
    expect(router.post).toHaveBeenCalledTimes(4);
    expect(kartu.getAllByRole('button', { name: /^Tautkan kegiatan/ }).every((tombol) => !(tombol as HTMLButtonElement).disabled)).toBe(true);
});

it('mengandalkan muat ulang props setelah penautan sukses tanpa menyisakan state klien', () => {
    // Meniru Inertia: sukses lalu selesai. Daftar kandidat baru datang dari props hasil reload server.
    vi.mocked(router.post).mockImplementation((_url, _data, options) => {
        options?.onSuccess?.({ props: { errors: {} } } as never);
        options?.onFinish?.(undefined as never);
    });

    const kandidatSatu = kandidatTautan({ id: 'keg-1', periode_id: P1, nama: 'Kandidat Satu' });
    const kandidatDua = kandidatTautan({ id: 'keg-2', periode_id: P2, nama: 'Kandidat Dua', periode_nama: 'Triwulan II' });
    const { rerender } = render(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={kegiatanTautan([kandidatSatu, kandidatDua])} />);

    const kartu = () => within(screen.getByRole('region', { name: 'Kegiatan belum tertaut' }));
    const tombol = (nama: string) => tombolTautkan(kartu(), nama);

    pilihArahBaris(kartu(), 'Kandidat Satu', 'mengurangi');
    pilihArahBaris(kartu(), 'Kandidat Dua', 'menambah');
    fireEvent.click(tombol('Kandidat Satu'));

    // Sukses tidak menyisakan galat maupun status proses; daftar menunggu props baru.
    expect(screen.queryByRole('alert')).toBeNull();
    expect(tombol('Kandidat Satu').disabled).toBe(false);
    expect(tombol('Kandidat Satu').textContent).toContain('Tautkan');
    expect(kartu().getByRole('status').textContent).toBe('');
    expect(kartu().getByText('Kandidat Satu')).toBeTruthy();
    expect(router.post).toHaveBeenCalledTimes(1);

    // Reload props: kandidat yang sudah tertaut hilang dari server, bukan dari state klien.
    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={kegiatanTautan([kandidatDua])} />);
    expect(kartu().queryByText('Kandidat Satu')).toBeNull();
    expect(kartu().getByText('Kandidat Dua')).toBeTruthy();
    expect(screen.queryByRole('alert')).toBeNull();
    expect(router.post).toHaveBeenCalledTimes(1);

    // Kandidat yang tersisa masih dapat ditautkan, lalu kartu hilang saat server tak lagi mengirim kandidat.
    fireEvent.click(tombol('Kandidat Dua'));
    expect(router.post).toHaveBeenCalledTimes(2);
    rerender(<KegiatanRencanaAksi rencanaAksi={buatRencanaAksi()} kegiatan={kegiatanTautan([])} />);
    expect(screen.queryByRole('region', { name: 'Kegiatan belum tertaut' })).toBeNull();
    expect(screen.queryByRole('alert')).toBeNull();
});
