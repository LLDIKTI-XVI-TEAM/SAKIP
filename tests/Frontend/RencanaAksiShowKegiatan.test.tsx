import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import RencanaAksiShow from '@/Pages/RencanaAksi/Show';
import type { KegiatanRencanaAksiItem, RencanaAksiShow as RencanaAksiPayload } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/rencana-aksi/ra-1', props: { auth: {}, pengaturan: {}, flash: {}, errors: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const RA_ID = 'ra-1';
const P1 = 'tw1';

function buatPayload(ubah: Partial<RencanaAksiPayload> = {}): RencanaAksiPayload {
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
        // Halaman rincian hanya-baca untuk ringkasan kegiatan; test yang membuka form target menimpanya lewat `ubah`.
        can: { view: true, update: false, submit: false },
        ...ubah,
    };
}

function baris(id: string, kegiatanId: string, ubah: Partial<KegiatanRencanaAksiItem> = {}): KegiatanRencanaAksiItem {
    return {
        id, kegiatan_id: kegiatanId, periode_id: P1, nama: 'Kegiatan uji', status: 'rencana', komponen_id: null,
        arah_dampak: 'menambah', catatan: null, anggaran: '0', sumber_klaim: 'rencana_aksi', kegiatan_bersama: false,
        ...ubah,
    };
}

it('menampilkan tautan ke halaman kegiatan tanpa kartu ringkasan maupun form tambah di halaman rincian', () => {
    render(
        <RencanaAksiShow
            rencanaAksi={buatPayload()}
            kegiatan={{
                daftar: [baris('k1', 'keg-1'), baris('k3', 'keg-2')],
                total: { total: '2500000', belum_diisi: 1, per_periode: { [P1]: { total: '2500000', belum_diisi: 1 } } },
                can: { tambah: true, kelola: true },
            }}
        />,
    );

    expect(screen.getByRole('link', { name: 'Buka halaman kegiatan' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/kegiatan');
    // Keputusan PM 28 Oktober 2026: kartu ringkasan (jumlah, total anggaran) dihapus; tombol pindah ke kanan atas.
    expect(screen.queryByRole('heading', { name: 'Kegiatan' })).toBeNull();
    expect(screen.queryByText('Jumlah kegiatan')).toBeNull();

    // Tambah/ubah/klaim kegiatan hanya di halaman kegiatan RA (Q74 §74.1).
    expect(screen.queryByRole('button', { name: /Tambah kegiatan/ })).toBeNull();
    expect(screen.queryByText('Kegiatan per Periode')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Simpan kegiatan' })).toBeNull();
    expect(screen.queryByLabelText(/^Nama kegiatan/)).toBeNull();
});

it('menahan tautan halaman kegiatan selama form target kotor dan menjelaskan alasannya', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    render(
        <RencanaAksiShow
            rencanaAksi={buatPayload({ can: { view: true, update: true, submit: false } })}
            kegiatan={{
                daftar: [baris('k1', 'keg-1')],
                total: { total: '0', belum_diisi: 0, per_periode: { [P1]: { total: '0', belum_diisi: 0 } } },
                can: { tambah: false, kelola: true },
            }}
        />,
    );

    const tautan = screen.getByRole('link', { name: 'Buka halaman kegiatan' });
    const petunjuk = 'Simpan atau buang perubahan target sebelum membuka halaman kegiatan.';
    // Form bersih: navigasi ke halaman kegiatan tetap terbuka.
    expect(tautan.getAttribute('aria-disabled')).not.toBe('true');
    expect(screen.queryByText(petunjuk)).toBeNull();

    // Form target kotor: uraian dikirim bersama target, jadi perubahannya menahan navigasi.
    fireEvent.change(screen.getByRole('textbox', { name: 'Uraian rencana aksi' }), { target: { value: 'Perubahan belum disimpan' } });

    expect(tautan.getAttribute('aria-disabled')).toBe('true');
    expect(screen.getByText(petunjuk)).toBeTruthy();

    // Link Inertia tetap memanggil router.visit; navigasi dibatalkan oleh gate `onBefore`, bukan oleh absennya visit.
    fireEvent.click(tautan);
    const panggilan = visit.mock.calls.at(-1);
    expect(panggilan?.[0]).toBe('/rencana-aksi/ra-1/kegiatan');
    expect(panggilan?.[1]?.onBefore?.({} as never)).toBe(false);

    // Draf dikembalikan: gate membuka navigasi lagi dan petunjuk hilang.
    fireEvent.change(screen.getByRole('textbox', { name: 'Uraian rencana aksi' }), { target: { value: '' } });
    expect(tautan.getAttribute('aria-disabled')).not.toBe('true');
    expect(screen.queryByText(petunjuk)).toBeNull();
    fireEvent.click(tautan);
    expect(visit.mock.calls.at(-1)?.[1]?.onBefore?.({} as never)).toBe(true);
});

it('tidak menampilkan tautan halaman kegiatan bila aktor tidak berhak membacanya', () => {
    render(<RencanaAksiShow rencanaAksi={buatPayload()} kegiatan={null} />);

    expect(screen.queryByRole('link', { name: 'Buka halaman kegiatan' })).toBeNull();
});
