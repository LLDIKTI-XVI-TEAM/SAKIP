import { useState, type ReactNode } from 'react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Rekap from '@/Pages/Laporan/Rekap';
import type { BarisRekap, RekapProps } from '@/types/laporan';
import { getSidebarNavEntries } from '@/lib/navigation/sidebarNavigation';

const request = vi.hoisted(() => ({ get: vi.fn(), processing: false, errors: {} as Record<string, string> }));
const page = vi.hoisted(() => ({ pengaturan: {} as Record<string, string> }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, className }: { children: ReactNode; href: string; className?: string }) => <a href={href} className={className}>{children}</a>,
    usePage: () => ({ props: page }),
    useForm: (initial: RekapProps['filters']) => {
        const [data, setData] = useState(initial);
        return { data, setData: (key: keyof RekapProps['filters'] | RekapProps['filters'], value?: string) => setData(typeof key === 'string' ? { ...data, [key]: value ?? '' } : key), ...request };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children, title }: { children: ReactNode; title: string }) => <main><h1>{title}</h1>{children}</main> }));

beforeEach(() => { request.get.mockReset(); request.processing = false; request.errors = {}; page.pengaturan = {}; });
afterEach(cleanup);

function row(overrides: Partial<BarisRekap> = {}): BarisRekap {
    return {
        id: 'i1:p1', versi: { id: 'v1', nomor: 1, disahkan_pada: '2026-03-15T09:00:00+08:00' },
        periode: { id: 'p1', nama: 'Triwulan I', urutan: 1 },
        indikator: { id: 'i1', kode: 'IKU 1.1', nama: 'Indikator resmi yang diuji', satuan: 'poin', arah: 'naik_baik', tipe_perhitungan: 'manual', presisi: 2, desimal_tampilan: 2, definisi: 'Definisi beku' },
        sasaran: { id: 's1', kode: 'S1', deskripsi: 'Sasaran resmi' }, unit: { id: 'u1', nama: 'Unit Perencanaan' },
        pic: { id: 'a1', nama: 'PIC historis' }, pic_saat_ini: { id: 'a2', nama: 'PIC saat ini' },
        baseline: '10', target_pk: '70', target_periode: '20', tanpa_target_periode: false,
        nilai: '30', status_perhitungan: 'terhitung', status_laporan: 'disahkan', capaian_persen: '150.00',
        keterangan_capaian: null, status_capaian: 'Belum ditetapkan',
        selisih_target_pk: '-40', alasan_tidak_dapat_dihitung: null, komponen: [],
        narasi: { progres_kegiatan: 'Progres resmi', kendala_masalah: 'Kendala resmi', strategi_tindak_lanjut: 'Strategi resmi', tindak_lanjut_rekomendasi: 'Tindak lanjut resmi' },
        kegiatan: [], bukti: { status: 'lengkap', total: 1, terpenuhi: 1, histori_tidak_dapat_dipenuhi: false },
        rekomendasi: { id: 'r1', isi: 'Rekomendasi disetujui', disetujui_pada: '2026-03-16T09:00:00+08:00', cadangan: false }, rekomendasi_sebelumnya: null,
        can: { lihat_kegiatan: true }, ...overrides,
    };
}

function props(rows: BarisRekap[] = [row()]): RekapProps {
    return { filters: { jadwal_id: 'j1', periode_id: 'p1' }, jadwal: { id: 'j1', tahun: 2026, status: 'aktif', renstra: 'Renstra Uji' },
        pilihan_jadwal: [{ id: 'j1', label: '2026 · Renstra Uji' }, { id: 'j2', label: '2025 · Renstra Uji' }], pilihan_periode: [{ id: 'p1', nama: 'Triwulan I' }],
        baris: rows, pagination: { links: [], from: rows.length ? 1 : null, to: rows.length || null, total: rows.length } };
}

it.each([
    { zona: 'UTC', format: 'Y-m-d', hasil: '2026-10-21' },
    { zona: 'Asia/Jayapura', format: 'd F Y', hasil: '22 Oktober 2026' },
])('tanggal pengesahan mengikuti preferensi format dan zona waktu $zona / $format', ({ zona, format, hasil }) => {
    page.pengaturan = { 'tampilan.zona_waktu': zona, 'tampilan.format_tanggal': format };
    const data = row({ versi: { id: 'v1', nomor: 1, disahkan_pada: '2026-10-21T16:30:00Z' } });

    render(<Rekap {...props([data])} />);

    expect(screen.getByText(`Versi pengajuan 1 · disahkan ${hasil}`)).toBeTruthy();
});

it('menampilkan rekap berkomponen dengan dua kegiatan, rekomendasi dan histori bukti sesuai Plan 8.3', () => {
    const data = row({
        komponen: [
            { komponen_id: 'c1', kode: 'A', label: 'Komponen A', peran: 'penjumlah', bobot: '1', target: '8', realisasi: '12' },
            { komponen_id: 'c2', kode: 'B', label: 'Komponen B', peran: 'penjumlah', bobot: '1', target: '12', realisasi: '18' },
        ],
        kegiatan: [
            { id: 'k1', nama: 'Pendampingan terlaksana', status: 'terlaksana', anggaran_tercatat: true, anggaran: '15000000', kegiatan_bersama: false, sumber_klaim: 'rencana_aksi' },
            { id: 'k2', nama: 'Koordinasi tidak terlaksana', status: 'tidak_terlaksana', kendala: 'Peserta belum tersedia', anggaran_tercatat: true, anggaran: '0', kegiatan_bersama: false, sumber_klaim: 'pengukuran' },
        ],
        bukti: { status: 'lengkap', total: 1, terpenuhi: 1, histori_tidak_dapat_dipenuhi: true },
    });
    data.indikator = { ...data.indikator, tipe_perhitungan: 'penjumlahan' };
    render(<Rekap {...props([data])} />);
    expect(screen.getByRole('table', { name: 'Matriks rekapitulasi indikator dan periode' })).toBeTruthy();
    expect(screen.getByText('20,00 poin')).toBeTruthy();
    expect(screen.getByText('70,00 poin')).toBeTruthy();
    expect(screen.getByText('30,00 poin')).toBeTruthy();
    expect(screen.getByText('150,00%')).toBeTruthy();
    expect(screen.getByText('−40,00 poin').className).toContain('text-danger-dark');
    expect(screen.getByText('di bawah target')).toBeTruthy();
    expect(screen.getByText('PIC historis')).toBeTruthy();
    expect(screen.getByText('PIC saat ini', { selector: 'dd' })).toBeTruthy();
    const rincianTrigger = screen.getByText('Analisis, kegiatan & rekomendasi');
    expect(rincianTrigger.closest('details')?.open).toBe(false);
    fireEvent.click(rincianTrigger);
    expect(rincianTrigger.closest('details')?.open).toBe(true);
    expect(screen.getByRole('heading', { level: 3, name: 'Analisis pengukuran' })).toBeTruthy();
    expect(screen.getByRole('heading', { level: 3, name: 'Kegiatan & anggaran' })).toBeTruthy();
    expect(screen.getByText('Progres resmi')).toBeTruthy();
    expect(screen.getByText('Rekomendasi disetujui')).toBeTruthy();
    const komponenTrigger = screen.getByText('Komponen (2)');
    fireEvent.click(komponenTrigger);
    expect(komponenTrigger.closest('details')?.open).toBe(true);
    const komponenA = within(screen.getByText('A · Komponen A').parentElement!);
    expect(komponenA.getByText('Target: 8,00')).toBeTruthy();
    expect(komponenA.getByText('Realisasi: 12,00')).toBeTruthy();
    const komponenB = within(screen.getByText('B · Komponen B').parentElement!);
    expect(komponenB.getByText('Target: 12,00')).toBeTruthy();
    expect(komponenB.getByText('Realisasi: 18,00')).toBeTruthy();
    expect(screen.getByText('Pendampingan terlaksana')).toBeTruthy();
    const tidakTerlaksana = within(screen.getByText('Koordinasi tidak terlaksana').closest('li')!);
    expect(tidakTerlaksana.getByText('tidak terlaksana')).toBeTruthy();
    const catatanTrigger = tidakTerlaksana.getByText('Catatan kegiatan');
    fireEvent.click(catatanTrigger);
    expect(catatanTrigger.closest('details')?.open).toBe(true);
    expect(tidakTerlaksana.getByText('Peserta belum tersedia')).toBeTruthy();
    expect(screen.getByText('Histori: tidak dapat dipenuhi')).toBeTruthy();
});

it('menampilkan alasan penyebut nol dan histori bukti tanpa mengubahnya menjadi nol', () => {
    render(<Rekap {...props([row({ nilai: null, status_perhitungan: 'tidak_dapat_dihitung', status_laporan: 'tidak_dapat_dihitung', capaian_persen: null, selisih_target_pk: null,
        alasan_tidak_dapat_dihitung: 'Penyebut nol resmi', bukti: { status: 'lengkap', total: 1, terpenuhi: 1, histori_tidak_dapat_dipenuhi: true } })])} />);
    expect(screen.getAllByText('Tidak dapat dihitung').length).toBeGreaterThan(0);
    expect(screen.getByText('Penyebut nol resmi')).toBeTruthy();
    expect(screen.getByText('Histori: tidak dapat dipenuhi')).toBeTruthy();
    expect(screen.queryByText('0,00%')).toBeNull();
});

it('mempertahankan penanda server belum dapat diukur saat periode juga tanpa target', () => {
    render(<Rekap {...props([row({ nilai: null, target_periode: null, tanpa_target_periode: true, status_laporan: 'belum_dapat_diukur', status_perhitungan: 'belum_dapat_diukur', capaian_persen: null, selisih_target_pk: null })])} />);
    expect(screen.getAllByText('Belum dapat diukur').length).toBeGreaterThan(0);
    expect(screen.getByText('Tanpa target periode')).toBeTruthy();
    expect(screen.queryByText('0,00%')).toBeNull();
});

it('membedakan key anggaran hilang, nilai null, dan nol faktual', () => {
    render(<Rekap {...props([row({ kegiatan: [
        { id: 'k1', nama: 'Kegiatan lama', anggaran_tercatat: false, anggaran: null, kegiatan_bersama: null, sumber_klaim: null },
        { id: 'k2', nama: 'Kegiatan belum diisi', anggaran_tercatat: true, anggaran: null, kegiatan_bersama: false, sumber_klaim: 'pengukuran' },
        { id: 'k3', nama: 'Kegiatan tanpa anggaran', anggaran_tercatat: true, anggaran: '0', kegiatan_bersama: true, sumber_klaim: 'rencana_aksi' },
    ] })])} />);
    expect(within(screen.getByText('Kegiatan lama').closest('li')!).getAllByText('Tidak tercatat pada versi ini').length).toBeGreaterThan(0);
    expect(within(screen.getByText('Kegiatan belum diisi').closest('li')!).getByText('Belum diisi')).toBeTruthy();
    expect(screen.getByText('Rp0')).toBeTruthy();
    expect(screen.getByText('Kegiatan bersama')).toBeTruthy();
});

it('menutup detail kegiatan menurut capability dan membedakan baris belum disahkan', () => {
    render(<Rekap {...props([row({ can: { lihat_kegiatan: false }, kegiatan: [{ id: 'k1', nama: 'CANARY-KEGIATAN', anggaran: '5000000', anggaran_tercatat: true, kegiatan_bersama: false, sumber_klaim: null }] })])} />);
    expect(screen.getByText('Detail kegiatan dibatasi sesuai akses unit.')).toBeTruthy();
    expect(screen.queryByText('CANARY-KEGIATAN')).toBeNull();
    cleanup();
    render(<Rekap {...props([row({ versi: null, status_laporan: 'belum_disahkan', nilai: null, capaian_persen: null, selisih_target_pk: null, narasi: null, kegiatan: null, bukti: null })])} />);
    expect(within(screen.getByText('Capaian terhadap target periode').parentElement!).getByText('Belum disahkan')).toBeTruthy();
    expect(within(screen.getByText('Selisih terhadap target PK').parentElement!).getByText('Belum disahkan')).toBeTruthy();
    expect(screen.getAllByText('Belum disahkan').length).toBeGreaterThan(1);
    expect(screen.queryByText('0,00%')).toBeNull();
});

it('mengirim filter GET tanpa page lama, mereset periode saat jadwal berganti, dan menampilkan kegagalan jaringan', () => {
    render(<Rekap {...props()} />);
    fireEvent.change(screen.getByLabelText('Tahun / jadwal'), { target: { value: 'j2' } });
    expect((screen.getByLabelText('Periode') as HTMLSelectElement).value).toBe('');
    expect((screen.getByLabelText('Periode') as HTMLSelectElement).disabled).toBe(true);
    fireEvent.submit(screen.getByRole('form', { name: 'Filter rekapitulasi' }));
    expect(request.get).toHaveBeenCalledWith('/laporan/rekap', expect.objectContaining({ preserveState: false }));
    act(() => { request.get.mock.calls[0][1].onNetworkError(); });
    expect(screen.getByRole('alert').textContent).toContain('Periksa koneksi');
});

it('menampilkan kondisi kosong dan pending serta membatasi menu pada capability laporan', () => {
    request.processing = true;
    render(<Rekap {...props([])} />);
    expect(screen.getByRole('status').textContent).toContain('Belum ada indikator');
    expect((screen.getByRole('button', { name: 'Terapkan' }) as HTMLButtonElement).disabled).toBe(true);
    const allowed = getSidebarNavEntries({ laporan_read: true });
    const group = allowed.find((entry) => entry.type === 'group' && entry.group.id === 'pelaksanaan-evaluasi');
    expect(group?.type === 'group' && group.group.children.some((item) => item.href === '/laporan/rekap')).toBe(true);
    expect(getSidebarNavEntries({ laporan_read: false }).some((entry) => entry.type === 'group' && entry.group.children.some((item) => item.href === '/laporan/rekap'))).toBe(false);
});

it('tidak menampilkan jadwal lain sebagai pilihan aktif ketika tahun yang diminta belum memiliki jadwal', () => {
    render(<Rekap {...props([])} filters={{ jadwal_id: '', periode_id: '' }} jadwal={null} />);
    expect((screen.getByLabelText('Tahun / jadwal') as HTMLSelectElement).value).toBe('');
    expect(screen.getByRole('status').textContent).toContain('Belum ada jadwal');
    expect((screen.getByRole('button', { name: 'Terapkan' }) as HTMLButtonElement).disabled).toBe(true);
    fireEvent.change(screen.getByLabelText('Tahun / jadwal'), { target: { value: 'j1' } });
    expect((screen.getByRole('button', { name: 'Terapkan' }) as HTMLButtonElement).disabled).toBe(false);
});

it.each([
    { arah: 'naik_baik' as const, selisih: '40', teks: '+40,00 poin', warna: 'text-success-dark', posisi: 'di atas target' },
    { arah: 'turun_baik' as const, selisih: '-40', teks: '−40,00 poin', warna: 'text-success-dark', posisi: 'di bawah target' },
    { arah: 'turun_baik' as const, selisih: '40', teks: '+40,00 poin', warna: 'text-danger-dark', posisi: 'di atas target' },
    { arah: 'turun_baik' as const, selisih: '-0.001', teks: '0,00 poin', warna: 'text-muted', posisi: 'sama dengan target' },
])('menampilkan selisih PK bertanda dan makna aksesibel untuk $arah / $selisih', ({ arah, selisih, teks, warna, posisi }) => {
    const data = row({ selisih_target_pk: selisih });
    data.indikator = { ...data.indikator, arah };
    render(<Rekap {...props([data])} />);
    expect(screen.getByText(teks).className).toContain(warna);
    expect(screen.getByText(posisi)).toBeTruthy();
});
