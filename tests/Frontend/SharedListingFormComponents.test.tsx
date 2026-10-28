import { useState } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, it, vi } from 'vitest';
import { ActionMenu } from '@/Components/ActionMenu';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { tautanAman } from '@/lib/url';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Modal } from '@/Components/Modal';
import { Pagination } from '@/Components/Pagination';
import { Table, TableActionLink, TableBody, TableCellLabel, TableEmptyRow, TableRow, tableCellResponsive } from '@/Components/Table';
import type { LampiranDraft } from '@/types/perjanjian-kinerja';
import { router } from '@inertiajs/react';
import { Eye, SearchX } from 'lucide-react';

afterEach(() => cleanup());

const links = [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/regulasi?page=1', label: '1', active: true },
    { url: '/regulasi?page=2', label: '2', active: false },
    { url: '/regulasi?page=2', label: 'Next &raquo;', active: false },
];

it('pagination disembunyikan pada satu halaman dan menampilkan label teks tanpa entitas HTML', () => {
    const { container } = render(<Pagination links={links.slice(0, 2).concat(links[3])} from={1} to={5} total={5} ariaLabel="Paginasi uji" />);
    expect(container.innerHTML).toBe('');

    render(<Pagination links={links} from={1} to={10} total={15} ariaLabel="Paginasi uji" />);
    const nav = screen.getByRole('navigation', { name: 'Paginasi uji' });
    expect(nav.textContent).toContain('Menampilkan 1–10 dari 15 data');
    expect(screen.getByText('‹ Sebelumnya').getAttribute('aria-disabled')).toBe('true');
    expect(screen.getByRole('link', { name: '1' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('link', { name: 'Berikutnya ›' }).getAttribute('href')).toBe('/regulasi?page=2');
});

it('tabel: baris kosong mengisi selebar tabel dengan judul, keterangan, dan aksi opsional tanpa scroll horizontal di mobile', () => {
    render(
        <Table>
            <TableBody>
                <TableEmptyRow colSpan={3} icon={SearchX} title="Tidak ada izin yang cocok" description="Coba kata kunci lain." action={<button type="button">Ke halaman pertama</button>} />
            </TableBody>
        </Table>,
    );
    const cell = screen.getByRole('cell');
    expect(cell.getAttribute('colspan')).toBe('3');
    expect(cell.className).toContain('block');
    expect(cell.className).toContain('md:table-cell');
    expect(screen.getByRole('row').className).toContain('md:table-row');
    expect(cell.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
    expect(screen.getByText('Tidak ada izin yang cocok')).toBeTruthy();
    expect(screen.getByText('Coba kata kunci lain.')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Ke halaman pertama' })).toBeTruthy();
    cleanup();

    render(<Table><TableBody><TableEmptyRow colSpan={2} icon={SearchX} title="Belum ada data" /></TableBody></Table>);
    expect(screen.getByRole('cell').textContent).toBe('Belum ada data');
    expect(screen.queryByRole('button')).toBeNull();
});

it('tabel: label kolom hanya tampil di mobile dan sel responsif tanpa padding mobile', () => {
    render(<Table><TableBody><TableRow><td className={tableCellResponsive}><TableCellLabel>Status</TableCellLabel>Aktif</td></TableRow></TableBody></Table>);
    expect(screen.getByText('Status').className).toContain('md:hidden');
    expect(screen.getByRole('cell').className).toBe('min-w-0 p-0 md:px-4 md:py-3.5');
});

it('tabel: tautan aksi ikon memakai label aksesibel, tooltip, gaya aksi bersama, dan opsi kunjungan pemanggil', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const onStart = vi.fn();
    render(<TableActionLink href="/akses/izin-peran?role=r1" label="Lihat izin Pegawai" tooltip="Lihat izin" icon={Eye} onStart={onStart} />);
    const link = screen.getByRole('link', { name: 'Lihat izin Pegawai' });
    expect(link.getAttribute('href')).toBe('/akses/izin-peran?role=r1');
    expect(link.className).toContain('text-muted');
    expect(link.className).toContain('hover:text-primary');
    expect(link.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
    expect(screen.getByRole('tooltip').textContent).toBe('Lihat izin');

    await userEvent.setup().click(link);
    expect(visit).toHaveBeenCalledWith('/akses/izin-peran?role=r1', expect.objectContaining({ method: 'get' }));
    // Inertia membungkus callback; yang diuji: callback pemanggil ikut terpanggil saat kunjungan dimulai.
    visit.mock.calls[0][1]?.onStart?.({} as never);
    expect(onStart).toHaveBeenCalledOnce();
    visit.mockRestore();
});

function LampiranHarness({ blockedMessage, errors = { lampiran: 'Maksimal lima lampiran.' } }: { blockedMessage?: string; errors?: Record<string, string> }) {
    const [items, setItems] = useState<LampiranDraft[]>([]);

    return (
        <LampiranDraftFields
            items={items}
            errors={errors}
            onChange={(update) => setItems((previous) => update(previous))}
            title="Lampiran Dokumen"
            modeDescriptions={{ file: 'Deskripsi file', tautan: 'Deskripsi tautan', teks: 'Deskripsi teks' }}
            placeholders={{ tautan: 'https://...', teks: 'Catatan...' }}
            emptyText="Belum ada lampiran"
            blockedMessage={blockedMessage}
            renderExtraFields={(index) => <span>Field ekstra {index + 1}: {items[index]?.nama_asli ?? 'kosong'}</span>}
        />
    );
}

it('editor lampiran menambah, berganti mode, menampilkan field ekstra non-file, dan menghapus draf', async () => {
    const user = userEvent.setup();
    render(<LampiranHarness />);

    expect(screen.getByText('Maksimal lima lampiran.')).toBeTruthy();
    expect(screen.getByText('Belum ada lampiran')).toBeTruthy();

    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    expect(screen.getByLabelText<HTMLInputElement>('File lampiran 1').disabled).toBe(false);
    expect(screen.queryByText(/Field ekstra/)).toBeNull();

    await user.click(screen.getByRole('button', { name: 'Tautan' }));
    expect(screen.getByRole('button', { name: 'Tautan' }).getAttribute('aria-pressed')).toBe('true');
    expect(screen.getByText('Field ekstra 1: kosong')).toBeTruthy();
    await user.type(screen.getByLabelText('Tautan lampiran 1'), 'https://contoh.go.id');
    expect(screen.getByLabelText<HTMLInputElement>('Tautan lampiran 1').value).toBe('https://contoh.go.id');

    await user.click(screen.getByRole('button', { name: 'Hapus Lampiran' }));
    expect(screen.getByText('Belum ada lampiran')).toBeTruthy();
});

it('editor lampiran menghubungkan galat file dari server ke input file', async () => {
    const user = userEvent.setup();
    render(<LampiranHarness errors={{ 'lampiran.0.file': 'Ukuran file melebihi batas.' }} />);

    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    const input = screen.getByLabelText('File lampiran 1');
    const pesan = screen.getByRole('alert');
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(pesan.textContent).toBe('Ukuran file melebihi batas.');
    expect(input.getAttribute('aria-describedby')).toBe(pesan.id);
});

it('editor lampiran membatasi jumlah, hanya menampilkan mode yang diizinkan, dan merender field per lampiran di semua mode', async () => {
    const user = userEvent.setup();
    function Harness({ modes }: { modes: ('file' | 'tautan' | 'teks')[] }) {
        const [items, setItems] = useState<LampiranDraft[]>([]);
        return (
            <LampiranDraftFields items={items} errors={{}} onChange={(update) => setItems((previous) => update(previous))} title="Bukti baru"
                modeDescriptions={{ file: 'Deskripsi file', tautan: 'Deskripsi tautan', teks: 'Deskripsi teks' }} placeholders={{ tautan: 'https://...', teks: '' }}
                emptyText="Belum ada bukti baru" maxItems={1} modes={modes} renderItemFields={(index) => <span>Field lampiran {index + 1}</span>} />
        );
    }
    const { unmount } = render(<Harness modes={['tautan', 'teks']} />);

    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    expect(screen.queryByRole('button', { name: 'Tambah Lampiran' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'File' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Tautan' }).getAttribute('aria-pressed')).toBe('true');
    expect(screen.getByLabelText('Tautan lampiran 1')).toBeTruthy();
    expect(screen.getByText('Field lampiran 1')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Teks' }));
    expect(screen.getByText('Field lampiran 1')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Hapus Lampiran' }));
    expect(screen.getByRole('button', { name: 'Tambah Lampiran' })).toBeTruthy();
    unmount();

    render(<Harness modes={[]} />);
    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    expect(screen.getByText('Field lampiran 1')).toBeTruthy();
    expect(screen.queryByLabelText('File lampiran 1')).toBeNull();
});

it('editor lampiran menutup penambahan saat diblokir', () => {
    render(<LampiranHarness blockedMessage="Tidak memiliki izin unggah." />);

    expect(screen.getByText('Tidak memiliki izin unggah.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Tambah Lampiran' })).toBeNull();
});

it('menu aksi: tidak tampil tanpa item, navigasi keyboard, Escape mengembalikan fokus, dan memilih item menutup menu', async () => {
    const user = userEvent.setup();
    const { container } = render(<ActionMenu items={[]} trigger="⋯" triggerLabel="Aksi kosong" />);
    expect(container.innerHTML).toBe('');
    cleanup();

    const ubah = vi.fn();
    const arsip = vi.fn();
    render(
        <ActionMenu
            trigger="⋯"
            triggerLabel="Aksi IKU-01"
            items={[
                { key: 'ubah', label: 'Ubah', ariaLabel: 'Ubah indikator IKU-01', onSelect: ubah },
                { key: 'pj', label: 'Penanggung Jawab', href: '/pj' },
                { key: 'arsip', label: 'Arsipkan', danger: true, onSelect: arsip },
            ]}
        />,
    );
    const trigger = screen.getByRole('button', { name: 'Aksi IKU-01' });
    expect(trigger.getAttribute('aria-expanded')).toBe('false');

    await user.click(trigger);
    expect(trigger.getAttribute('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: 'Ubah indikator IKU-01' }));
    expect(screen.getByRole('separator')).toBeTruthy();
    await user.keyboard('{ArrowDown}');
    expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: 'Penanggung Jawab' }));
    await user.keyboard('{ArrowUp}{ArrowUp}');
    expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: 'Arsipkan' }));

    await user.keyboard('{Escape}');
    expect(screen.queryByRole('menu')).toBeNull();
    expect(document.activeElement).toBe(trigger);

    await user.click(trigger);
    await user.click(screen.getByRole('menuitem', { name: 'Arsipkan' }));
    expect(arsip).toHaveBeenCalledTimes(1);
    expect(ubah).not.toHaveBeenCalled();
    expect(screen.queryByRole('menu')).toBeNull();
});

it('menu aksi: modal yang dibuka dari item mengembalikan fokus ke pemicu saat ditutup', async () => {
    const user = userEvent.setup();
    function Harness() {
        const [open, setOpen] = useState(false);
        return (
            <>
                <ActionMenu trigger="⋯" triggerLabel="Aksi IKU-01" items={[{ key: 'pindah', label: 'Pindah Unit', onSelect: () => setOpen(true) }]} />
                <Modal isOpen={open} onClose={() => setOpen(false)} title="Pindah unit indikator">
                    <p>Isi modal</p>
                </Modal>
            </>
        );
    }
    render(<Harness />);
    const trigger = screen.getByRole('button', { name: 'Aksi IKU-01' });

    trigger.focus();
    await user.keyboard('{Enter}');
    expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: 'Pindah Unit' }));
    await user.keyboard('{Enter}');
    expect(screen.getByRole('dialog')).toBeTruthy();

    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(trigger);
});

it('tautan eksternal hanya http/https: skema berbahaya tidak menjadi link', () => {
    expect(tautanAman('https://jdih.example.go.id/dok')).toBe('https://jdih.example.go.id/dok');
    expect(tautanAman('http://contoh.go.id')).toBe('http://contoh.go.id/');
    expect(tautanAman('javascript:alert(1)')).toBeNull();
    expect(tautanAman('data:text/html,<b>x</b>')).toBeNull();
    expect(tautanAman('bukan url')).toBeNull();
    expect(tautanAman(null)).toBeNull();

    const base = { nama_asli: null, mime: null, ukuran_bytes: null, isi_teks: null, download_url: null } as const;
    const { rerender } = render(<ul><BerkasLampiranItem berkas={{ ...base, mode: 'tautan', tautan: 'javascript:alert(1)' }} /></ul>);
    expect(screen.queryByRole('link', { name: 'Buka tautan' })).toBeNull();
    expect(screen.getByText('javascript:alert(1)')).toBeTruthy();

    rerender(<ul><BerkasLampiranItem berkas={{ ...base, mode: 'tautan', tautan: 'https://contoh.go.id/pk' }} /></ul>);
    expect(screen.getByRole('link', { name: 'Buka tautan' }).getAttribute('href')).toBe('https://contoh.go.id/pk');
});

it('ukuran berkas memakai format angka Indonesia', () => {
    render(<ul><BerkasLampiranItem berkas={{ mode: 'file', nama_asli: 'naskah.pdf', mime: 'application/pdf', ukuran_bytes: 1536, tautan: null, isi_teks: null, download_url: null }} /></ul>);
    expect(screen.getByText('application/pdf · 1,5 KB')).toBeTruthy();
});
