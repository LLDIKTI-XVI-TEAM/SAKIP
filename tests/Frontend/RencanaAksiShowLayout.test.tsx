import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, expect, it, vi } from 'vitest';
import RencanaAksiShow from '@/Pages/RencanaAksi/Show';
import type { RencanaAksiBukti, RencanaAksiKegiatan, RencanaAksiShow as RencanaAksiPayload } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rencana-aksi/ra-1', props: { auth: {}, pengaturan: {}, flash: {}, errors: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

// jsdom belum mengimplementasikan <dialog>; modal bukti dirender bersama halaman.
const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));

beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});

afterAll(() => {
    dialogMethods.forEach((name, index) => {
        const descriptor = originalDialogMethods[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
});

const P1 = 'tw1';

function buatPayload(ubah: Partial<RencanaAksiPayload> = {}): RencanaAksiPayload {
    return {
        id: 'ra-1',
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
        periode: [{
            id: P1, nama: 'Triwulan I', urutan: 1, efektif: true, status: 'efektif',
            nilai: [{ komponen_id: null, kode: null, label: null, nilai: '100', keterangan: null }],
            skor: { nilai: '100.00', status_perhitungan: 'terhitung' }, peringatan_turun: false, komponen_turun: [],
            tanggal: null, batas_terkunci_pic: null, boleh_tanpa_target: false,
        }],
        koreksi: { aktif: false, periode_ids: [] },
        periode_dapat_ditulis: [P1],
        deviasi_pk: { dapat_dinilai: true, ada: false, alasan_diperlukan: false, alasan_terisi: false, skor_periode_terakhir: '100.00', target_pk: '100', periode_id: P1 },
        pengajuan: { siap: true, alasan: [] },
        versi_pengajuan: null,
        can: { view: true, update: true, submit: true },
        ...ubah,
    };
}

const kegiatan: RencanaAksiKegiatan = {
    daftar: [],
    total: { total: '0', belum_diisi: 0, per_periode: {} },
    can: { tambah: false, kelola: false },
};

const bukti: RencanaAksiBukti = {
    dikecualikan: false,
    persyaratan: [],
    ringkasan: { lengkap: true, total_wajib: 0, terpenuhi_wajib: 0 },
    daftar: [],
    unggahan: { ukuran_maks_kb: 1024, format_diizinkan: 'pdf' },
    can: { upload: false, delete: false },
};

const BEKU = {
    status_alur: 'disahkan',
    versi_pengajuan: { nomor: 1, diajukan_oleh: 'PIC Uji', diajukan_at: '2026-04-01T08:00:00+08:00' },
    can: { view: true, update: false, submit: false },
} satisfies Partial<RencanaAksiPayload>;

const judulKartu = (): string[] => screen.getAllByRole('heading', { level: 3 }).map((judul) => judul.textContent ?? '');

it('menaruh tautan kembali ke daftar lalu tombol halaman kegiatan di atas isi halaman', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={kegiatan} bukti={bukti} />);

    const tautan = screen.getByRole('link', { name: 'Kembali ke daftar' });
    expect(tautan.getAttribute('href')).toBe('/rencana-aksi');
    const kegiatanRa = screen.getByRole('link', { name: 'Buka halaman kegiatan' });
    const identitas = screen.getByRole('heading', { name: 'I-UJI · Indikator Uji' });
    expect(tautan.compareDocumentPosition(kegiatanRa) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(kegiatanRa.compareDocumentPosition(identitas) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    // Ringkasan kegiatan tidak lagi ditampilkan di halaman detail; angkanya ada di halaman kegiatan RA.
    expect(screen.queryByText('Jumlah kegiatan')).toBeNull();
    expect(screen.queryByText('Total anggaran')).toBeNull();
});

it('menampilkan enam data identitas dengan arah sebagai data tersendiri', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={kegiatan} bukti={bukti} />);

    const daftar = screen.getByText('Unit penanggung jawab').closest('dl')!;
    const pasangan = Array.from(daftar.children).map((item) => [
        item.querySelector('dt')?.textContent,
        Array.from(item.querySelectorAll('dd')).map((isi) => isi.textContent),
    ]);
    expect(pasangan).toEqual([
        ['Unit penanggung jawab', ['Unit Uji']],
        ['Penanggung jawab', ['PIC Uji']],
        ['Penutupan jadwal', [expect.stringContaining('2026')]],
        ['Cara hitung', ['Manual']],
        ['Arah', ['Makin tinggi makin baik']],
        ['Target PK tahunan', [expect.stringMatching(/^100(,00)? poin$/)]],
    ]);
});

it('menyusun bagian bernomor mengikuti alur kerja saat rencana aksi dapat disunting', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={kegiatan} bukti={bukti} />);

    expect(judulKartu()).toEqual([
        'I-UJI · Indikator Uji',
        '1Target per periode',
        '2Uraian dan alasan deviasi',
        '3Bukti dukung',
        '4Kesiapan pengajuan',
    ]);
    // Nomor langkah hanya penanda visual; nama heading untuk pembaca layar tetap judulnya.
    expect(screen.getByRole('heading', { name: 'Kesiapan pengajuan' })).toBeTruthy();
});

it('menomori langkah tetap berurutan bila kartu bukti tidak tersedia', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={kegiatan} bukti={null} />);

    expect(judulKartu().slice(1)).toEqual(['1Target per periode', '2Uraian dan alasan deviasi', '3Kesiapan pengajuan']);
});

it('tidak menomori bagian saat rencana aksi beku atau hanya dapat dibaca', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload(BEKU)} kegiatan={kegiatan} bukti={bukti} />);
    expect(judulKartu().slice(1)).toEqual(['Target per periode', 'Uraian dan alasan deviasi', 'Bukti dukung']);
    cleanup();

    // Status draft tetapi tanpa izin ubah: bukan langkah kerja bagi pembaca.
    render(<RencanaAksiShow rencanaAksi={buatPayload({ can: { view: true, update: false, submit: false } })} kegiatan={kegiatan} bukti={bukti} />);
    expect(judulKartu().slice(1)).toEqual(['Target per periode', 'Uraian dan alasan deviasi', 'Bukti dukung', 'Kesiapan pengajuan']);
});

it('mengumpulkan pemberitahuan status dalam satu wilayah', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload(BEKU)} kegiatan={kegiatan} bukti={bukti} />);

    const wilayah = within(screen.getByRole('region', { name: 'Pemberitahuan' }));
    expect(wilayah.getByText(/Diajukan sebagai versi 1 oleh PIC Uji/)).toBeTruthy();
    expect(wilayah.getByText('Formulir hanya dapat dibaca sesuai status dan izin akses Anda.')).toBeTruthy();
    cleanup();

    render(<RencanaAksiShow rencanaAksi={buatPayload({ status_alur: 'dikembalikan', koreksi: { aktif: true, periode_ids: [P1] } })} kegiatan={kegiatan} bukti={bukti} />);
    expect(within(screen.getByRole('region', { name: 'Pemberitahuan' })).getByText(/Sesi koreksi aktif/)).toBeTruthy();
    cleanup();

    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={kegiatan} bukti={bukti} />);
    expect(screen.queryByRole('region', { name: 'Pemberitahuan' })).toBeNull();
});

it('menaruh tombol Simpan Target di kaki kartu uraian', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={kegiatan} bukti={bukti} />);

    const kartuUraian = within(screen.getByRole('region', { name: 'Uraian dan alasan deviasi' }));
    expect(kartuUraian.getByRole('button', { name: 'Simpan Target' })).toBeTruthy();
    expect(within(screen.getByRole('region', { name: 'Target per periode' })).queryByRole('button', { name: 'Simpan Target' })).toBeNull();
});
