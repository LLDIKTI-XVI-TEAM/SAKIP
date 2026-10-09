import { useState } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, it, vi } from 'vitest';
import { ActionMenu } from '@/Components/ActionMenu';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { tautanAman } from '@/lib/url';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Pagination } from '@/Components/Pagination';
import type { LampiranDraft } from '@/types/perjanjian-kinerja';

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

function LampiranHarness({ fileDisabled = false, blockedMessage }: { fileDisabled?: boolean; blockedMessage?: string }) {
    const [items, setItems] = useState<LampiranDraft[]>([]);

    return (
        <LampiranDraftFields
            items={items}
            errors={{ lampiran: 'Maksimal lima lampiran.' }}
            onChange={(update) => setItems((previous) => update(previous))}
            title="Lampiran Dokumen"
            modeDescriptions={{ file: 'Deskripsi file', tautan: 'Deskripsi tautan', teks: 'Deskripsi teks' }}
            placeholders={{ tautan: 'https://...', teks: 'Catatan...' }}
            emptyText="Belum ada lampiran"
            fileDisabled={fileDisabled}
            blockedMessage={blockedMessage}
            renderExtraFields={(index) => <span>Field ekstra {index + 1}: {items[index]?.nama_asli ?? 'kosong'}</span>}
        />
    );
}

it('editor lampiran menambah, berganti mode, menampilkan field ekstra non-file, dan menghapus draf', async () => {
    const user = userEvent.setup();
    render(<LampiranHarness fileDisabled />);

    expect(screen.getByText('Maksimal lima lampiran.')).toBeTruthy();
    expect(screen.getByText('Belum ada lampiran')).toBeTruthy();

    await user.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
    expect(screen.getByLabelText<HTMLInputElement>('File lampiran 1').disabled).toBe(true);
    expect(screen.queryByText(/Field ekstra/)).toBeNull();

    await user.click(screen.getByRole('button', { name: 'Tautan' }));
    expect(screen.getByRole('button', { name: 'Tautan' }).getAttribute('aria-pressed')).toBe('true');
    expect(screen.getByText('Field ekstra 1: kosong')).toBeTruthy();
    await user.type(screen.getByLabelText('Tautan lampiran 1'), 'https://contoh.go.id');
    expect(screen.getByLabelText<HTMLInputElement>('Tautan lampiran 1').value).toBe('https://contoh.go.id');

    await user.click(screen.getByRole('button', { name: 'Hapus Lampiran' }));
    expect(screen.getByText('Belum ada lampiran')).toBeTruthy();
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
