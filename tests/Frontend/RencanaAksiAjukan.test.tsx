import type { ReactNode } from 'react';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { http } from '@inertiajs/core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RencanaAksiShow from '@/Pages/RencanaAksi/Show';
import type { RencanaAksiShow as RencanaAksiPayload } from '@/Pages/RencanaAksi/types';

// Props halaman dapat diubah per test untuk menyimulasikan error bag `ajukan` setelah redirect-back.
const halaman = vi.hoisted(() => ({ props: { pengaturan: {}, errors: {} as Record<string, unknown> } }));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
        usePage: () => halaman,
    };
});
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const P1 = '33333333-3333-4333-8333-333333333333';
const P2 = '44444444-4444-4434-8434-444444444444';
const TOMBOL_AJUKAN = 'Ajukan ke Tim Perencanaan';

function buatPayload(ubah: Partial<RencanaAksiPayload> = {}): RencanaAksiPayload {
    const periode = [P1, P2].map((id, index) => ({
        id,
        nama: `Triwulan ${['I', 'II'][index]}`,
        urutan: index + 1,
        efektif: true,
        status: 'efektif' as const,
        nilai: [{ komponen_id: null, kode: null, label: null, nilai: '100.000000000000', keterangan: null }],
        skor: { nilai: '100.00', status_perhitungan: 'terhitung' },
        peringatan_turun: false,
        komponen_turun: [] as (string | null)[],
        batas_terkunci_pic: null,
        boleh_tanpa_target: false,
    }));
    return {
        id: ID,
        tahun: 2026,
        status_alur: 'draft',
        versi: 2,
        expected_versi: 2,
        expected_snapshot_id: '77777777-7777-4777-8777-777777777777',
        expected_snapshot_versi: 1,
        uraian: null,
        alasan_deviasi_pk: null,
        indikator: {
            id: 'indikator-uji',
            kode: 'I-UJI',
            nama: 'Indikator Uji',
            satuan: 'poin',
            arah: 'naik_baik',
            tipe_perhitungan: 'manual',
            presisi: 2,
            desimal_tampilan: 2,
            status: 'aktif',
            tahun_mulai_berlaku: 2025,
            dinilai_tahunan: false,
        },
        unit: { id: 'unit-uji', nama: 'Unit Uji' },
        jadwal: {
            id: 'jadwal-uji',
            tahun: 2026,
            status: 'aktif',
            penutupan: '2026-12-31',
        },
        penanggung_jawab: { id: 'pic-uji', nama: 'Budi' },
        tipe_perhitungan: 'manual',
        presisi: 2,
        target_pk: '100.000000000000',
        baseline: null,
        komponen: [],
        periode,
        koreksi: { aktif: false, periode_ids: [] },
        deviasi_pk: {
            dapat_dinilai: true,
            ada: false,
            alasan_diperlukan: false,
            alasan_terisi: false,
            skor_periode_terakhir: '100.00',
            target_pk: '100.00',
            periode_id: P2,
        },
        pengajuan: { siap: true, alasan: [] },
        versi_pengajuan: null,
        can: { view: true, update: true, submit: true },
        ...ubah,
    };
}

beforeEach(() => {
    halaman.props.errors = {};
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({
        status: 200,
        data: JSON.stringify({ periode: [], deviasi_pk: { dapat_dinilai: false, ada: false, alasan_diperlukan: false, alasan_terisi: false, skor_periode_terakhir: null, target_pk: null, periode_id: null } }),
        headers: {},
    });
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('pengajuan rencana aksi di halaman Show', () => {
    it('tidak menampilkan tombol ajukan tanpa can.submit, tetapi kartu kesiapan tetap tampil', () => {
        render(<RencanaAksiShow rencanaAksi={buatPayload({
            can: { view: true, update: true, submit: false },
            pengajuan: { siap: false, alasan: ['Target periode Triwulan II belum lengkap.'] },
        })} />);

        expect(screen.queryByRole('button', { name: TOMBOL_AJUKAN })).toBeNull();
        expect(screen.getByText('Pengajuan belum dapat dilakukan')).toBeTruthy();
        expect(screen.getByText('Target periode Triwulan II belum lengkap.')).toBeTruthy();
    });

    it('isi lengkap tanpa can.submit tidak mengklaim siap diajukan', () => {
        render(<RencanaAksiShow rencanaAksi={buatPayload({ can: { view: true, update: true, submit: false } })} />);

        expect(screen.getByText('Isi sudah lengkap. Pengajuan belum tersedia untuk akun Anda saat ini.')).toBeTruthy();
        expect(screen.queryByText('Semua syarat pengajuan terpenuhi.')).toBeNull();
        expect(screen.queryByRole('button', { name: TOMBOL_AJUKAN })).toBeNull();
    });

    it('menampilkan alasan dan menonaktifkan tombol saat belum siap', () => {
        render(<RencanaAksiShow rencanaAksi={buatPayload({ pengajuan: { siap: false, alasan: ['Target periode Triwulan II belum lengkap.'] } })} />);

        expect(screen.getByText('Target periode Triwulan II belum lengkap.')).toBeTruthy();
        expect(screen.queryByText('Simpan draf untuk memperbarui hasil.')).toBeNull();
        expect(screen.getByRole<HTMLButtonElement>('button', { name: TOMBOL_AJUKAN }).disabled).toBe(true);
    });

    it('membuka modal lalu mengirim expected_versi ke route ajukan', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        expect(screen.getByText('Semua syarat pengajuan terpenuhi.')).toBeTruthy();
        await user.click(screen.getByRole('button', { name: TOMBOL_AJUKAN }));
        const dialog = screen.getByRole('dialog', { name: 'Ajukan Rencana Aksi' });
        expect(dialog.textContent).toContain('tidak dapat diubah sampai dikembalikan oleh Tim Perencanaan');
        await user.click(screen.getByRole('button', { name: 'Ajukan' }));

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, data] = vi.mocked(router.post).mock.calls[0];
        expect(url).toBe(`/rencana-aksi/${ID}/ajukan`);
        expect(data).toEqual({ expected_versi: 2 });
    });

    it('menonaktifkan tombol saat draf kotor', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        await user.type(screen.getByRole('textbox', { name: 'Uraian rencana aksi' }), 'Perubahan belum disimpan');

        expect(screen.getByRole<HTMLButtonElement>('button', { name: TOMBOL_AJUKAN }).disabled).toBe(true);
        expect(screen.getByText('Simpan atau buang perubahan sebelum mengajukan.')).toBeTruthy();
    });

    it('menampilkan banner versi saat status diajukan', () => {
        render(<RencanaAksiShow rencanaAksi={buatPayload({
            status_alur: 'diajukan',
            versi: 3,
            expected_versi: 3,
            versi_pengajuan: { nomor: 1, diajukan_oleh: 'Budi', diajukan_at: '2026-10-10T02:00:00+00:00' },
            can: { view: true, update: false, submit: false },
        })} />);

        expect(screen.getByText(/Diajukan sebagai versi 1 oleh Budi/)).toBeTruthy();
        expect(screen.getByText(/Menunggu verifikasi Tim Perencanaan\./)).toBeTruthy();
        expect(screen.queryByText('Kesiapan pengajuan')).toBeNull();
        expect(screen.queryByRole('button', { name: TOMBOL_AJUKAN })).toBeNull();
    });

    it('menampilkan galat server walau can.submit false', () => {
        halaman.props.errors = { ajukan: { jendela: 'Periode terkunci bagi PIC setelah batas pengisian lewat: Triwulan I (batas 2026-03-31).' } };
        render(<RencanaAksiShow rencanaAksi={buatPayload({ can: { view: true, update: false, submit: false } })} />);

        const galat = screen.getByText('Periode terkunci bagi PIC setelah batas pengisian lewat: Triwulan I (batas 2026-03-31).');
        expect(galat.getAttribute('role')).toBe('alert');
    });

    it('menampilkan galat gerbang lain di bag ajukan, termasuk indikator arsip', () => {
        halaman.props.errors = { ajukan: { indikator_id: "Indikator 'I-UJI' telah diarsipkan sehingga tidak dapat dibuatkan rencana aksi baru." } };
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        expect(screen.getByRole('alert').textContent).toContain('telah diarsipkan');
    });

    it('menampilkan blok data berubah dari props.errors.ajukan setelah remount', async () => {
        const user = userEvent.setup();
        halaman.props.errors = { ajukan: { expected_snapshot_id: 'Konteks indikator berubah (snapshot koreksi baru terbit). Muat ulang dan simpan target sebelum mengajukan.' } };
        render(<RencanaAksiShow rencanaAksi={buatPayload({ can: { view: true, update: true, submit: false } })} />);

        expect(screen.getByText('Data telah berubah di server')).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Muat ulang data terbaru' }));
        expect(router.get).toHaveBeenCalledWith(`/rencana-aksi/${ID}`, {}, { preserveScroll: false, preserveState: false });
    });

    it('mengabaikan galat simpan target di level atas', () => {
        halaman.props.errors = { expected_versi: 'Data telah berubah. Muat ulang sebelum mengulangi penyimpanan.' };
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        expect(screen.queryByText('Data telah berubah di server')).toBeNull();
    });

    it('403 tidak diklaim sebagai penolakan karena redirect setelah pengajuan sukses juga dapat berakhir 403', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        await user.click(screen.getByRole('button', { name: TOMBOL_AJUKAN }));
        await user.click(screen.getByRole('button', { name: 'Ajukan' }));
        const options = vi.mocked(router.post).mock.calls[0][2];
        await act(async () => { options?.onHttpException?.({ status: 403, data: '', headers: {} }); });

        const galat = screen.getByRole('alert').textContent;
        expect(galat).toContain('hasil pengajuan belum dapat dipastikan');
        expect(galat).not.toContain('Izin pengajuan ditolak');
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('mengirim dengan errorBag ajukan', async () => {
        const user = userEvent.setup();
        render(<RencanaAksiShow rencanaAksi={buatPayload()} />);

        await user.click(screen.getByRole('button', { name: TOMBOL_AJUKAN }));
        await user.click(screen.getByRole('button', { name: 'Ajukan' }));

        expect(vi.mocked(router.post).mock.calls[0][2]).toMatchObject({ errorBag: 'ajukan', preserveScroll: true });
    });
});
