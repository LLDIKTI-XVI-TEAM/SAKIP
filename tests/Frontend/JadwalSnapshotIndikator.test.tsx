import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import SnapshotIndikator from '@/Pages/Jadwal/SnapshotIndikator';
import type { SnapshotIndikatorProps, SnapshotIndikatorRow } from '@/types/snapshot-indikator';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: {} }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const periode = [
    { id: 'periode-3', nama: 'Triwulan III', urutan: 3 },
    { id: 'periode-1', nama: 'Triwulan I', urutan: 1 },
    { id: 'periode-2', nama: 'Triwulan II', urutan: 2 },
];
const berbeda: SnapshotIndikatorRow = {
    indikator_id: 'indikator-a',
    kode: 'IKU-A',
    nama: 'Persentase lulusan bekerja',
    satuan: 'persen',
    desimal_tampilan: 2,
    unit: 'Bagian Umum',
    target: '94',
    snapshot_id: 'snapshot-a2',
    nomor_versi: 2,
    periode_mulai: { id: 'periode-2', nama: 'Triwulan II', urutan: 2 },
    selisih: {
        kolom: [
            { kolom: 'target', label: 'Target PK', lama: '94', baru: '94.05' },
            { kolom: 'arah', label: 'Arah penilaian', lama: 'naik_baik', baru: 'turun_baik' },
            { kolom: 'dinilai_tahunan', label: 'Dinilai tahunan', lama: false, baru: true },
        ],
        komponen: {
            lama: [{ komponen_id: 'k-1', kode: 'A', label: 'Lulusan', peran: 'penjumlah', bobot: '1', urutan: 1 }],
            baru: [{ komponen_id: 'k-1', kode: 'A', label: 'Lulusan', peran: 'penjumlah', bobot: '2', urutan: 1 }],
        },
    },
    riwayat: [
        { snapshot_id: 'snapshot-a2', nomor_versi: 2, periode_mulai: 'Triwulan II', alasan_koreksi: 'Salah ketik unit', rujukan_koreksi: 'Nota dinas 3/2026', diterbitkan_pada: '2026-05-02T01:00:00+00:00' },
        { snapshot_id: 'snapshot-a1', nomor_versi: 1, periode_mulai: 'Triwulan I', alasan_koreksi: null, rujukan_koreksi: null, diterbitkan_pada: null },
    ],
};
const sama: SnapshotIndikatorRow = {
    ...berbeda,
    indikator_id: 'indikator-b',
    kode: 'IKU-B',
    nama: 'Indeks kepuasan layanan',
    snapshot_id: 'snapshot-b1',
    nomor_versi: 1,
    periode_mulai: { id: 'periode-1', nama: 'Triwulan I', urutan: 1 },
    selisih: { kolom: [], komponen: null },
    riwayat: [{ snapshot_id: 'snapshot-b1', nomor_versi: 1, periode_mulai: 'Triwulan I', alasan_koreksi: null, rujukan_koreksi: null, diterbitkan_pada: null }],
};
const props: SnapshotIndikatorProps = {
    jadwal: { id: 'jadwal-1', tahun: 2026, status: 'aktif', renstra: 'Renstra 2025–2029' },
    periode,
    indikator: [berbeda, sama],
    can: { koreksi: true, baca_indikator: true },
};

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native; fokus trap tetap diverifikasi lewat browser.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

/** Opsi yang diteruskan useForm asli ke router; callback-nya mengisi state form seperti respons server. */
const opsiKirim = (index = 0) => vi.mocked(router.post).mock.calls[index][2] as {
    onSuccess: (page: unknown) => Promise<unknown>;
    onError: (errors: Record<string, string>) => void;
    onNetworkError: () => void;
};

const bukaKoreksi = async (user: ReturnType<typeof userEvent.setup>) => {
    await user.click(screen.getByRole('button', { name: 'Koreksi snapshot IKU-A' }));
    return screen.getByRole('dialog');
};

it('menampilkan versi berlaku, status data master, dan riwayat versi', () => {
    render(<SnapshotIndikator {...props} />);

    expect(screen.getByText('Persentase lulusan bekerja')).toBeTruthy();
    expect(screen.getByText('Berbeda')).toBeTruthy();
    expect(screen.getByText('Sama')).toBeTruthy();
    expect(screen.getByText('Mulai Triwulan II')).toBeTruthy();
    expect(screen.getAllByText('94,00 persen')).toHaveLength(2);
    expect(screen.getByText('Riwayat versi (2)')).toBeTruthy();
    expect(screen.getByText('Alasan: Salah ketik unit')).toBeTruthy();
    expect(screen.getByText('Rujukan: Nota dinas 3/2026')).toBeTruthy();
    expect(screen.getAllByText('Waktu terbit tidak tercatat')).toHaveLength(2);
    // Koreksi hanya ditawarkan untuk indikator yang berbeda dengan data master.
    expect(screen.getByRole('button', { name: 'Koreksi snapshot IKU-A' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Koreksi snapshot IKU-B' })).toBeNull();
    expect(screen.getByRole('link', { name: /Kembali ke jadwal/ }).getAttribute('href')).toBe('/jadwal/jadwal-1');
});

it('tanpa izin koreksi tidak menampilkan tombol koreksi', () => {
    render(<SnapshotIndikator {...props} can={{ koreksi: false, baca_indikator: true }} />);

    expect(screen.queryByRole('button', { name: /Koreksi snapshot/ })).toBeNull();
    expect(screen.getByText('Berbeda')).toBeTruthy();
});

it('tanpa izin baca indikator menjelaskan isi yang tidak ditampilkan', () => {
    render(<SnapshotIndikator {...props} indikator={[]} can={{ koreksi: false, baca_indikator: false }} />);

    expect(screen.getByText('Isi snapshot tidak ditampilkan')).toBeTruthy();
    expect(screen.getByText('Akun Anda tidak memiliki izin membaca indikator.')).toBeTruthy();
});

it('modal menampilkan selisih berlabel dan hanya periode mulai yang tidak mundur', async () => {
    const user = userEvent.setup();
    render(<SnapshotIndikator {...props} />);
    const dialog = await bukaKoreksi(user);

    expect(within(dialog).getByText('Koreksi snapshot IKU-A')).toBeTruthy();
    expect(within(dialog).getByText('Target PK')).toBeTruthy();
    expect(within(dialog).getByText('94,05')).toBeTruthy();
    expect(within(dialog).getByText('Makin rendah makin baik')).toBeTruthy();
    expect(within(dialog).getByText('Ya')).toBeTruthy();
    expect(within(dialog).getByText('Komponen data master')).toBeTruthy();
    expect(within(dialog).getByText(/bobot 2/)).toBeTruthy();
    const select = within(dialog).getByLabelText<HTMLSelectElement>(/Periode mulai berlaku/);
    expect(select.value).toBe('periode-2');
    expect(within(select).getAllByRole<HTMLOptionElement>('option').map((option) => option.value)).toEqual(['periode-2', 'periode-3']);
    expect(within(dialog).queryByText(/dilepas saat draf disimpan berikutnya/)).toBeNull();

    await user.selectOptions(select, 'periode-3');
    expect(within(dialog).getByText('Target draf Rencana Aksi untuk periode sebelum Triwulan III dilepas saat draf disimpan berikutnya.')).toBeTruthy();
    expect(within(dialog).getByText(/Periode sebelum Triwulan III tetap memakai versi 2\./)).toBeTruthy();
});

it('kalimat periode sebelumnya tidak tampil bila periode mulai adalah periode pertama jadwal', async () => {
    const user = userEvent.setup();
    const mulaiPertama: SnapshotIndikatorRow = { ...berbeda, periode_mulai: { id: 'periode-1', nama: 'Triwulan I', urutan: 1 } };
    render(<SnapshotIndikator {...props} indikator={[mulaiPertama, sama]} />);
    const dialog = await bukaKoreksi(user);

    expect(within(dialog).queryByText(/Periode sebelum/)).toBeNull();
    expect(within(dialog).getByText(/Pengukuran baru sejak Triwulan I memakai versi koreksi/)).toBeTruthy();
});

it('props terbaru tanpa selisih menonaktifkan pengiriman dan menjelaskan alasannya', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<SnapshotIndikator {...props} />);
    const dialog = await bukaKoreksi(user);
    await user.type(within(dialog).getByLabelText(/Alasan perubahan/), 'Alasan resmi.');
    await user.type(within(dialog).getByLabelText(/Rujukan resmi koreksi/), 'Rujukan resmi');
    const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Terbitkan versi koreksi' });
    expect(konfirmasi.disabled).toBe(false);

    // Pengguna lain sudah menerbitkan versi 3 yang sama dengan master.
    rerender(<SnapshotIndikator {...props} indikator={[{ ...berbeda, snapshot_id: 'snapshot-a3', nomor_versi: 3, selisih: { kolom: [], komponen: null } }, sama]} />);
    expect(within(screen.getByRole('dialog')).getByText('Data master sudah sama dengan versi 3; koreksi tidak diperlukan.')).toBeTruthy();
    expect(konfirmasi.disabled).toBe(true);
});

it('mengirim periode, alasan, rujukan, dan token versi setelah kedua isian wajib terisi', async () => {
    const user = userEvent.setup();
    render(<SnapshotIndikator {...props} />);
    const dialog = await bukaKoreksi(user);
    const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Terbitkan versi koreksi' });

    expect(konfirmasi.disabled).toBe(true);
    await user.type(within(dialog).getByLabelText(/Alasan perubahan/), 'Target PK salah ketik.');
    expect(konfirmasi.disabled).toBe(true);
    await user.type(within(dialog).getByLabelText(/Rujukan resmi koreksi/), 'Nota dinas 7/2026');
    expect(konfirmasi.disabled).toBe(false);
    await user.click(konfirmasi);

    expect(vi.mocked(router.post).mock.calls[0][0]).toBe('/jadwal/jadwal-1/snapshot/snapshot-a2/koreksi');
    expect(vi.mocked(router.post).mock.calls[0][1]).toStrictEqual({
        periode_mulai_id: 'periode-2',
        alasan_koreksi: 'Target PK salah ketik.',
        rujukan_koreksi: 'Nota dinas 7/2026',
        expected_snapshot_id: 'snapshot-a2',
        expected_snapshot_versi: 2,
    });
});

it('penolakan usang mempertahankan isian lalu mengirim ulang dengan versi terbaru dari props', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<SnapshotIndikator {...props} />);
    let dialog = await bukaKoreksi(user);
    await user.type(within(dialog).getByLabelText(/Alasan perubahan/), 'Alasan resmi.');
    await user.type(within(dialog).getByLabelText(/Rujukan resmi koreksi/), 'Rujukan resmi');
    await user.click(within(dialog).getByRole('button', { name: 'Terbitkan versi koreksi' }));

    act(() => opsiKirim(0).onError({ expected_snapshot_id: 'Snapshot sudah dikoreksi atau berubah. Muat ulang halaman snapshot sebelum mengoreksi.' }));
    // Redirect penolakan membawa props terbaru: pengguna lain sudah menerbitkan versi 3 dengan selisih berbeda.
    const versiTiga: SnapshotIndikatorRow = { ...berbeda, snapshot_id: 'snapshot-a3', nomor_versi: 3,
        selisih: { kolom: [{ kolom: 'target', label: 'Target PK', lama: '94.05', baru: '95' }], komponen: null } };
    rerender(<SnapshotIndikator {...props} indikator={[versiTiga, sama]} />);
    dialog = screen.getByRole('dialog');
    expect(within(dialog).getByRole('alert').textContent).toContain('Snapshot sudah dikoreksi atau berubah.');
    expect(within(dialog).getByText('95,00')).toBeTruthy();
    expect(within(dialog).getByLabelText<HTMLTextAreaElement>(/Rujukan resmi koreksi/).value).toBe('Rujukan resmi');

    await user.click(within(dialog).getByRole('button', { name: 'Terbitkan versi koreksi' }));
    expect(vi.mocked(router.post).mock.calls[1][0]).toBe('/jadwal/jadwal-1/snapshot/snapshot-a3/koreksi');
    expect(vi.mocked(router.post).mock.calls[1][1]).toMatchObject({ expected_snapshot_id: 'snapshot-a3', expected_snapshot_versi: 3, rujukan_koreksi: 'Rujukan resmi' });
    await act(async () => {
        await opsiKirim(1).onSuccess({ component: 'Jadwal/SnapshotIndikator', props: { errors: {} }, url: '/jadwal/jadwal-1/snapshot', version: null });
    });
    expect(screen.queryByRole('dialog')).toBeNull();
});

it('hasil yang tidak terkonfirmasi menutup modal dan meminta memuat ulang', async () => {
    const user = userEvent.setup();
    render(<SnapshotIndikator {...props} />);
    const dialog = await bukaKoreksi(user);
    await user.type(within(dialog).getByLabelText(/Alasan perubahan/), 'Alasan resmi.');
    await user.type(within(dialog).getByLabelText(/Rujukan resmi koreksi/), 'Rujukan resmi');
    await user.click(within(dialog).getByRole('button', { name: 'Terbitkan versi koreksi' }));

    act(() => opsiKirim(0).onNetworkError());
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByRole('link', { name: 'Periksa snapshot terbaru' }).getAttribute('href')).toBe('/jadwal/jadwal-1/snapshot');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Koreksi snapshot IKU-A' }).disabled).toBe(true);
});
