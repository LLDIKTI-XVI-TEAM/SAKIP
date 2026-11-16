import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PengingatKontekstual, type PengingatHalaman } from '@/Components/PengingatKontekstual';
import type { BannerItem } from '@/Components/Banner';

const pribadi: BannerItem = {
    id: 'pribadi-jadwal-periode', signature: 'periode|2027-01-15|warning|2', tingkat: 'warning',
    judul: 'Batas pengisian Triwulan IV: 3 hari lagi (15 Januari 2027)',
    keterangan: '2 dari 4 indikator Anda belum diajukan.',
    href: '/pengukuran?fokus=kewajiban&jadwal_id=jadwal&periode_id=periode', label: 'Lihat kewajiban Anda',
};

const props = (item: BannerItem = pribadi): PengingatHalaman => ({ pribadi: [item], pk: [] });

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('Pengingat kontekstual', () => {
    it('merender teks dan tautan server dalam bagian personal, agregat, dan PK yang terpisah', () => {
        render(<PengingatKontekstual pengingat={{
            pribadi: [pribadi],
            ringkasan: [{ ...pribadi, id: 'ringkasan', tingkat: 'info', judul: 'Ringkasan Triwulan IV', keterangan: '5 dari 8 indikator belum diajukan.', href: null }],
            pk: [{ ...pribadi, id: 'pk', tingkat: 'danger', judul: 'Perjanjian Kinerja 2027 belum lengkap', keterangan: 'Target tahunan belum diisi.', href: '/perjanjian-kinerja', label: 'Lihat Perjanjian Kinerja' }],
        }} />);

        const personal = screen.getByRole('region', { name: 'Kewajiban Anda' });
        expect(within(personal).getByRole('status', { name: pribadi.judul })).toBeTruthy();
        expect(within(within(personal).getByRole('status', { name: pribadi.judul }))
            .getByRole('heading', { name: 'Kewajiban Anda', level: 2 })).toBeTruthy();
        expect(within(personal).getByText(pribadi.keterangan)).toBeTruthy();
        expect(within(personal).getByRole('link', { name: 'Lihat kewajiban Anda' }).getAttribute('href')).toBe(pribadi.href);
        expect(within(screen.getByRole('region', { name: 'Ringkasan Pengajuan' })).getByText('5 dari 8 indikator belum diajukan.')).toBeTruthy();
        expect(within(screen.getByRole('region', { name: 'Perjanjian Kinerja' })).getByRole('alert', { name: 'Perjanjian Kinerja 2027 belum lengkap' })).toBeTruthy();
        expect(screen.getAllByRole('link')).toHaveLength(2);
    });

    it('tidak membuat bagian kosong saat prop absen atau tidak ada pengingat', () => {
        const { container, rerender } = render(<PengingatKontekstual />);
        expect(container.textContent).toBe('');
        rerender(<PengingatKontekstual pengingat={{ pribadi: [], pk: [], ringkasan: [] }} />);
        expect(screen.queryByRole('region')).toBeNull();
    });

    it('tutup bekerja dengan keyboard tanpa penyimpanan atau request mutasi', async () => {
        const user = userEvent.setup();
        const storage = vi.spyOn(Storage.prototype, 'setItem');
        const post = vi.spyOn(router, 'post');
        const put = vi.spyOn(router, 'put');
        const remove = vi.spyOn(router, 'delete');
        render(<PengingatKontekstual pengingat={props({ ...pribadi, href: null })} />);

        await user.tab();
        expect(document.activeElement).toBe(screen.getByRole('button', { name: `Tutup pengingat: ${pribadi.judul}` }));
        await user.keyboard('{Enter}');

        expect(screen.queryByRole('region', { name: 'Kewajiban Anda' })).toBeNull();
        expect(storage).not.toHaveBeenCalled();
        expect(post).not.toHaveBeenCalled();
        expect(put).not.toHaveBeenCalled();
        expect(remove).not.toHaveBeenCalled();
    });

    it('mempertahankan penutupan signature yang sama dan memunculkan pesan saat signature berubah', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<PengingatKontekstual pengingat={props()} />);
        await user.click(screen.getByRole('button', { name: `Tutup pengingat: ${pribadi.judul}` }));
        rerender(<PengingatKontekstual pengingat={props({ ...pribadi })} />);
        expect(screen.queryByText(pribadi.judul)).toBeNull();

        const berubah = { ...pribadi, signature: 'periode|2027-01-15|danger|1', tingkat: 'danger' as const,
            judul: 'Batas pengisian Triwulan IV: besok (15 Januari 2027)', keterangan: '1 dari 4 indikator Anda belum diajukan.' };
        rerender(<PengingatKontekstual pengingat={props(berubah)} />);
        expect(screen.getByRole('alert', { name: berubah.judul })).toBeTruthy();
        expect(screen.getByText(berubah.keterangan)).toBeTruthy();
    });

    it('menutup satu pesan tanpa menghilangkan pengingat lain', async () => {
        const user = userEvent.setup();
        const lain = { ...pribadi, id: 'pribadi-lain', judul: 'Kewajiban Triwulan III belum diajukan' };
        render(<PengingatKontekstual pengingat={{ pribadi: [pribadi, lain], pk: [] }} />);
        await user.click(screen.getByRole('button', { name: `Tutup pengingat: ${pribadi.judul}` }));
        expect(screen.queryByText(pribadi.judul)).toBeNull();
        expect(screen.getByRole('status', { name: lain.judul })).toBeTruthy();
    });

    it('menampilkan kembali pesan setelah navigasi Inertia dan setelah komponen dimuat ulang', async () => {
        const user = userEvent.setup();
        const { unmount } = render(<PengingatKontekstual pengingat={props()} />);
        await user.click(screen.getByRole('button', { name: `Tutup pengingat: ${pribadi.judul}` }));
        act(() => document.dispatchEvent(new CustomEvent('inertia:navigate')));
        expect(screen.getByText(pribadi.judul)).toBeTruthy();
        await user.click(screen.getByRole('button', { name: `Tutup pengingat: ${pribadi.judul}` }));
        unmount();
        render(<PengingatKontekstual pengingat={props()} />);
        expect(screen.getByText(pribadi.judul)).toBeTruthy();
    });

    it('kunjungan sukses dengan replace menampilkan signature sama walau tidak ada event navigate', async () => {
        const user = userEvent.setup();
        const { rerender } = render(<PengingatKontekstual pengingat={props()} />);
        await user.click(screen.getByRole('button', { name: `Tutup pengingat: ${pribadi.judul}` }));
        rerender(<PengingatKontekstual pengingat={props({ ...pribadi })} />);
        expect(screen.queryByText(pribadi.judul)).toBeNull();

        act(() => document.dispatchEvent(new CustomEvent('inertia:success')));

        expect(screen.getByRole('status', { name: pribadi.judul })).toBeTruthy();
    });

    it('melepas kedua listener lifecycle saat komponen dilepas', () => {
        const register = vi.spyOn(document, 'addEventListener');
        const unregister = vi.spyOn(document, 'removeEventListener');
        const { unmount } = render(<PengingatKontekstual pengingat={props()} />);
        const listeners = register.mock.calls.filter(([type]) => type === 'inertia:navigate' || type === 'inertia:success');
        expect(listeners).toHaveLength(2);

        unmount();

        for (const [type, listener] of listeners) {
            expect(unregister).toHaveBeenCalledWith(type, listener);
        }
    });

    it('perubahan waktu klien tidak menghitung ulang teks atau tingkat dari server', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2040-01-01T12:00:00Z'));
        const { rerender } = render(<PengingatKontekstual pengingat={props()} />);
        vi.setSystemTime(new Date('2000-01-01T12:00:00Z'));
        rerender(<PengingatKontekstual pengingat={props({ ...pribadi })} />);
        expect(screen.getByRole('status', { name: pribadi.judul })).toBeTruthy();
        expect(screen.getByText(pribadi.keterangan)).toBeTruthy();
    });
});
