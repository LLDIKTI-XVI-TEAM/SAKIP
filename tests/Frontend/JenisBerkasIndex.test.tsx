import type { ReactNode } from 'react';
import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import JenisBerkasIndex, { type JenisBerkasItem } from '@/Pages/JenisBerkas/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 'perencanaan', role: 'perencanaan' } }, errors: {}, pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));

const dialogMethods = ['showModal', 'close'] as const;
const originalDialogMethods = dialogMethods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    // jsdom belum menerapkan API dialog native yang dipakai AuditReasonModal.
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => dialogMethods.forEach((name, index) => {
    const descriptor = originalDialogMethods[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

const jb = (item: Partial<JenisBerkasItem> & Pick<JenisBerkasItem, 'id' | 'nama' | 'tahap'>): JenisBerkasItem => ({
    indikator_id: null,
    wajib: false,
    keterangan: null,
    izinkan_file: true,
    izinkan_tautan: false,
    izinkan_teks: false,
    semua_mode_wajib: false,
    urutan: 0,
    format_diizinkan: null,
    ukuran_maks_kb: null,
    aktif: true,
    indikator: null,
    created_at: '2026-09-01T00:00:00Z',
    updated_at: '2026-09-01T00:00:00Z',
    ...item,
});

const list = [
    jb({ id: 'j1', nama: 'Laporan Capaian Triwulan', tahap: 'pengukuran', wajib: true, format_diizinkan: 'pdf,docx', ukuran_maks_kb: 10240 }),
    jb({ id: 'j2', nama: 'Kerangka Acuan Kerja', tahap: 'rencana_aksi', izinkan_file: false, izinkan_teks: true, aktif: false }),
];
const indikators = [{ id: 'i1', kode: 'IK-01', nama: 'Persentase PT terakreditasi' }];
const canAll = { create: true, update: true, delete: true };
const batasUnggahan = { ukuran_maks_kb: 10240, format_diizinkan: 'pdf,docx,xlsx,jpg,jpeg,png', batas_atas_kb: 102400 };

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'put').mockImplementation(() => undefined);
    vi.spyOn(router, 'patch').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

it('menyaring persyaratan lewat pencarian dan tahap yang berlabel', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);

    expect(screen.getByRole('button', { name: /Tambah persyaratan/ })).toBeTruthy();
    await user.selectOptions(screen.getByLabelText('Tahap'), 'rencana_aksi');
    expect(screen.queryByText('Laporan Capaian Triwulan')).toBeNull();
    expect(screen.getByText('Kerangka Acuan Kerja')).toBeTruthy();

    await user.type(screen.getByLabelText('Cari persyaratan'), 'laporan');
    expect(screen.getByText('Tidak ada persyaratan yang cocok')).toBeTruthy();
});

it('merangkum ketentuan bukti dalam satu kolom', () => {
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);

    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Persyaratan', 'Tahap', 'Lingkup', 'Ketentuan bukti', 'Status', 'Aksi']);
    const laporan = screen.getByRole('row', { name: /Laporan Capaian Triwulan/ });
    expect(within(laporan).getByText('Wajib')).toBeTruthy();
    expect(within(laporan).getByText('File')).toBeTruthy();
    expect(within(laporan).getByText('pdf, docx · maks 10.240 KB')).toBeTruthy();
    // Baris tahap lama (Rencana Aksi) tanpa mode file tidak menampilkan batas teknis.
    const kak = screen.getByRole('row', { name: /Kerangka Acuan Kerja/ });
    expect(within(kak).getByText('Rencana Aksi')).toBeTruthy();
    expect(within(kak).getByText('Teks')).toBeTruthy();
    expect(within(kak).queryByText(/maks/)).toBeNull();
    expect(within(kak).getByText('Nonaktif')).toBeTruthy();
});

it('kolom kosong mengikuti jumlah kolom bagi pengguna baca saja', () => {
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={[]} indikators={indikators} can={{ create: false, update: false, delete: false }} />);

    const cell = screen.getByText('Belum ada persyaratan').closest('td');
    expect(cell?.colSpan).toBe(screen.getAllByRole('columnheader').length);
});

it('menyimpan persyaratan baru langsung tanpa alasan audit', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);

    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));
    await user.type(screen.getByLabelText(/nama persyaratan/i), 'Surat Tugas');
    await user.click(screen.getByRole('button', { name: /simpan persyaratan/i }));

    expect(router.post).toHaveBeenCalledWith(
        '/jenis-berkas',
        expect.objectContaining({ nama: 'Surat Tugas', tahap: 'pengukuran', izinkan_file: true }),
        expect.anything(),
    );
});

it.each(['', 'OK', 'Penyesuaian format laporan'])('menyimpan perubahan dengan alasan opsional: %j', async (alasan) => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);

    await user.click(screen.getByRole('button', { name: 'Edit Laporan Capaian Triwulan' }));
    await waitFor(() => expect(document.activeElement).toBe(screen.getByLabelText(/nama persyaratan/i)));
    await user.click(screen.getByRole('button', { name: /lanjut ke konfirmasi/i }));
    expect(router.put).not.toHaveBeenCalled();
    const reason = screen.getByLabelText(/alasan perubahan/i);
    expect(reason.getAttribute('aria-required')).toBe('false');
    expect(screen.queryByText(/Perubahan katalog persyaratan bukti dukung bersifat sensitif/)).toBeNull();
    expect(screen.queryByText(/Alasan, pengguna, waktu/)).toBeNull();
    if (alasan) await user.type(reason, alasan);
    await user.click(screen.getByRole('button', { name: /simpan perubahan/i }));

    expect(router.put).toHaveBeenCalledWith(
        '/jenis-berkas/j1',
        expect.objectContaining({ nama: 'Laporan Capaian Triwulan', alasan }),
        expect.anything(),
    );
});

it.each(['Batal', 'Escape'] as const)('menangguhkan form selama alasan audit terbuka dan mempertahankan draft setelah %s', async (dismiss) => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: 'Edit Laporan Capaian Triwulan' }));
    await user.clear(screen.getByLabelText(/format file/i));
    await user.type(screen.getByLabelText(/format file/i), 'pdf,xlsx');
    await user.click(screen.getByRole('button', { name: /lanjut ke konfirmasi/i }));

    expect(screen.queryByRole('dialog', { name: 'Edit persyaratan' })).toBeNull();
    const audit = screen.getByRole('dialog', { name: 'Simpan perubahan persyaratan?' });
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(document.activeElement).toBe(within(audit).getByLabelText(/alasan perubahan/i));
    await user.tab();
    expect(document.activeElement).toBe(within(audit).getByRole('button', { name: 'Batal' }));
    await user.tab();
    expect(document.activeElement).toBe(within(audit).getByRole('button', { name: 'Simpan perubahan' }));

    if (dismiss === 'Batal') {
        await user.tab({ shift: true });
        await user.keyboard('{Enter}');
    } else {
        // Native Escape memicu cancel; jsdom belum menerapkan perilaku dialog tersebut.
        fireEvent(audit, new Event('cancel', { cancelable: true }));
    }
    expect(router.put).not.toHaveBeenCalled();
    expect(screen.getByRole('dialog', { name: 'Edit persyaratan' })).toBeTruthy();
    expect((screen.getByLabelText(/format file/i) as HTMLInputElement).value).toBe('pdf,xlsx');
    expect((screen.getByLabelText(/nama persyaratan/i) as HTMLInputElement).value).toBe('Laporan Capaian Triwulan');
});

it('Enter pada tombol simpan mengirim persyaratan dengan format dan ukuran yang dipilih', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));
    await user.type(screen.getByLabelText(/format file/i), 'pdf,xlsx');
    await user.type(screen.getByLabelText(/Ukuran maksimum/i), '2048');
    await user.type(screen.getByLabelText(/nama persyaratan/i), 'Rincian Kegiatan');
    // user-event hanya mencari submit di subtree form; footer Modal memakai atribut form native.
    screen.getByRole('button', { name: 'Simpan persyaratan' }).focus();
    await user.keyboard('{Enter}');
    expect(router.post).toHaveBeenCalledWith('/jenis-berkas', expect.objectContaining({
        nama: 'Rincian Kegiatan', format_diizinkan: 'pdf,xlsx', ukuran_maks_kb: 2048,
    }), expect.anything());
});

it('Perencanaan mengubah format dan mengosongkan batas lewat form persyaratan', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: 'Edit Laporan Capaian Triwulan' }));
    await waitFor(() => expect(document.activeElement).toBe(screen.getByLabelText(/nama persyaratan/i)));
    const format = screen.getByLabelText(/format file/i) as HTMLInputElement;
    const ukuran = screen.getByLabelText(/Ukuran maksimum/i) as HTMLInputElement;
    expect(format.disabled).toBe(false);
    expect(ukuran.disabled).toBe(false);
    expect(ukuran.max).toBe('102400');
    expect(screen.getByText(/bawaan 10.240 KB/)).toBeTruthy();
    await user.clear(format);
    await user.clear(ukuran);
    await user.click(screen.getByRole('button', { name: /lanjut ke konfirmasi/i }));
    await user.type(screen.getByLabelText(/alasan perubahan/i), 'Format mengikuti bawaan');
    await user.click(screen.getByRole('button', { name: /simpan perubahan/i }));
    expect(router.put).toHaveBeenCalledWith('/jenis-berkas/j1', expect.objectContaining({
        alasan: 'Format mengikuti bawaan', expected_updated_at: '2026-09-01T00:00:00Z', format_diizinkan: null, ukuran_maks_kb: null,
    }), expect.anything());
});

it('pengguna baca saja tidak mendapat aksi ubah batas teknis', () => {
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={{ create: false, update: false, delete: false }} />);
    expect(screen.queryByRole('button', { name: /Ubah batas teknis|Edit |Tambah persyaratan/ })).toBeNull();
});

it('menampilkan batas efektif dari server tanpa peringatan saklar unggahan', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={{ ukuran_maks_kb: 2048, format_diizinkan: 'pdf,xlsx', batas_atas_kb: 51200 }} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));
    await user.click(screen.getByLabelText(/bukti wajib/i));
    expect(screen.getByText(/bawaan: pdf, xlsx/)).toBeTruthy();
    expect(screen.getByText(/bawaan 2.048 KB.*maksimal 51.200 KB/)).toBeTruthy();
    expect(screen.getByLabelText(/Ukuran maksimum/i).getAttribute('max')).toBe('51200');
    expect(screen.queryByText(/Unggahan file sedang dinonaktifkan/)).toBeNull();
});

it('mempertahankan draft dan menampilkan galat field ketika perubahan ditolak server', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: 'Edit Laporan Capaian Triwulan' }));
    await user.clear(screen.getByLabelText(/format file/i));
    await user.type(screen.getByLabelText(/format file/i), 'pdf,xlsx');
    await user.click(screen.getByRole('button', { name: /lanjut ke konfirmasi/i }));
    await user.type(screen.getByLabelText(/alasan perubahan/i), 'Format disesuaikan');
    await user.click(screen.getByRole('button', { name: /simpan perubahan/i }));
    await act(async () => { vi.mocked(router.put).mock.calls[0][2]?.onError?.({ format_diizinkan: 'Format tidak valid.' }); });
    expect(screen.getByRole('dialog', { name: 'Edit persyaratan' })).toBeTruthy();
    expect((screen.getByLabelText(/format file/i) as HTMLInputElement).value).toBe('pdf,xlsx');
    expect(screen.getByText('Format tidak valid.')).toBeTruthy();
    expect(screen.getByLabelText(/format file/i).getAttribute('aria-invalid')).toBe('true');
});

it('persyaratan perencanaan kegiatan menampilkan penanda beranggaran serta mengunggah dan menghapus contoh beralasan', async () => {
    const user = userEvent.setup();
    const remove = vi.spyOn(router, 'delete').mockImplementation(() => undefined);
    const rab = jb({ id: 'j3', nama: 'RAB', tahap: 'kegiatan_perencanaan', wajib: true, hanya_kegiatan_beranggaran: true,
        contoh_berkas: [{ id: 'c1', nama_asli: 'contoh-rab.xlsx', ukuran_bytes: 2048 }] });
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={[...list, rab]} indikators={indikators} can={canAll} />);

    expect(screen.getAllByText('Perencanaan Kegiatan').length).toBeGreaterThan(0);
    expect(screen.getByText(/hanya kegiatan beranggaran/)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'contoh-rab.xlsx' }).getAttribute('href')).toBe('/jenis-berkas/j3/contoh/c1');

    await user.click(screen.getByRole('button', { name: 'Hapus contoh contoh-rab.xlsx' }));
    await user.type(screen.getByRole('textbox', { name: /alasan/i }), 'Contoh usang');
    await user.click(screen.getByRole('button', { name: 'Hapus contoh' }));
    expect(remove).toHaveBeenCalledWith('/jenis-berkas/j3/contoh/c1', expect.objectContaining({ data: { alasan: 'Contoh usang' } }));

    const file = new File(['isi'], 'rab-baru.xlsx');
    await user.upload(screen.getByLabelText(/Unggah contoh/), file);
    expect(router.post).toHaveBeenCalledWith('/jenis-berkas/j3/contoh', { file }, expect.objectContaining({ forceFormData: true }));
});

it('penanda hanya kegiatan beranggaran hanya ditawarkan pada tahap perencanaan kegiatan', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));

    expect(screen.queryByLabelText('Hanya kegiatan beranggaran')).toBeNull();
    await user.selectOptions(document.getElementById('jb-tahap') as HTMLSelectElement, 'kegiatan_perencanaan');
    await user.click(screen.getByLabelText('Hanya kegiatan beranggaran'));
    await user.type(screen.getByLabelText(/Nama persyaratan/), 'RAB');
    await user.click(screen.getByRole('button', { name: 'Simpan persyaratan' }));
    expect(router.post).toHaveBeenCalledWith('/jenis-berkas', expect.objectContaining({ tahap: 'kegiatan_perencanaan', hanya_kegiatan_beranggaran: true }), expect.anything());
});

it('menawarkan tahap Rencana Aksi di urutan pertama dan mengirimkannya sebagai rencana_aksi', async () => {
    const user = userEvent.setup();
    render(<JenisBerkasIndex batasUnggahan={batasUnggahan} jenisBerkasList={list} indikators={indikators} can={canAll} />);
    await user.click(screen.getByRole('button', { name: /Tambah persyaratan/ }));

    const tahap = document.getElementById('jb-tahap') as HTMLSelectElement;
    expect(Array.from(tahap.options).map((option) => option.value)).toEqual(['rencana_aksi', 'pengukuran', 'kegiatan_perencanaan']);
    await user.selectOptions(tahap, 'rencana_aksi');
    expect(screen.queryByLabelText('Hanya kegiatan beranggaran')).toBeNull();
    await user.type(screen.getByLabelText(/Nama persyaratan/), 'Kerangka Acuan Kerja');
    await user.click(screen.getByRole('button', { name: 'Simpan persyaratan' }));
    expect(router.post).toHaveBeenCalledWith('/jenis-berkas', expect.objectContaining({ nama: 'Kerangka Acuan Kerja', tahap: 'rencana_aksi' }), expect.anything());
});
