import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';

afterEach(cleanup);

/** Varian dibuktikan lewat kelas badge kanonik, bukan lewat snapshot payload. */
function harapkanVarian(teks: string, kelas: string[]) {
    const badge = screen.getByText(teks);
    for (const k of kelas) expect(badge.classList.contains(k), `${teks} harus memuat ${k}`).toBe(true);
    return badge;
}

describe('Badge status capaian per kode kontrak server', () => {
    it.each([
        { kode: 'tercapai', label: 'Tercapai', kelas: ['bg-success/10', 'text-success-dark'] },
        { kode: 'belum_tercapai', label: 'Belum Tercapai', kelas: ['bg-danger/10', 'text-danger'] },
        { kode: 'belum_ditetapkan', label: 'Belum Ditetapkan', kelas: ['bg-soft', 'text-muted'] },
        { kode: 'tanpa_target_periode', label: 'Tanpa Target Periode', kelas: ['bg-info/10', 'text-info-dark'] },
        { kode: 'belum_dapat_diukur', label: 'Belum Dapat Diukur', kelas: ['bg-warning/10', 'text-warning-dark'] },
        { kode: 'tidak_dapat_dihitung', label: 'Tidak Dapat Dihitung', kelas: ['bg-secondary/15', 'text-ink'] },
    ])('kode $kode memakai label dan varian $label', ({ kode, label, kelas }) => {
        render(<StatusCapaianBadge kode={kode} />);

        const badge = harapkanVarian(label, kelas);
        expect(badge.textContent).toBe(label);
        expect(badge.getAttribute('aria-hidden')).toBeNull();
    });

    it('kode kontrak mengalahkan label naratif yang dikirim bersama', () => {
        render(<StatusCapaianBadge kode="tercapai" label="Belum ditetapkan" />);

        harapkanVarian('Tercapai', ['bg-success/10']);
        expect(screen.queryByText('Belum ditetapkan')).toBeNull();
    });

    it('ukuran kecil memakai tipografi badge sm', () => {
        render(<StatusCapaianBadge kode="belum_ditetapkan" size="sm" />);

        expect(screen.getByText('Belum Ditetapkan').classList.contains('text-[11px]')).toBe(true);
    });
});

describe('Badge status capaian dari label naratif rekap', () => {
    it.each([
        { label: 'Tercapai', kelas: ['bg-success/10', 'text-success-dark'] },
        { label: 'Belum tercapai', kelas: ['bg-danger/10', 'text-danger'] },
        { label: 'Belum ditetapkan', kelas: ['bg-soft', 'text-muted'] },
        { label: 'Tidak dinilai', kelas: ['bg-info/10', 'text-info-dark'] },
        { label: 'Tidak dapat dihitung', kelas: ['bg-secondary/15', 'text-ink'] },
    ])('label "$label" dipertahankan apa adanya dengan varian yang sesuai', ({ label, kelas }) => {
        render(<StatusCapaianBadge label={label} />);

        const badge = harapkanVarian(label, kelas);
        expect(badge.textContent).toBe(label);
    });

    it('label tidak peka huruf besar dan spasi pinggir tanpa mengubah teks tampil', () => {
        const { container } = render(<StatusCapaianBadge label="  BELUM TERCAPAI " />);

        const badge = container.querySelector('span');
        expect(badge?.classList.contains('bg-danger/10')).toBe(true);
        expect(badge?.textContent).toBe('  BELUM TERCAPAI ');
    });

    it('label tak dikenal tetap tampil dengan varian muted', () => {
        render(<StatusCapaianBadge label="Sedang ditinjau pimpinan" />);

        const badge = harapkanVarian('Sedang ditinjau pimpinan', ['bg-soft', 'text-muted']);
        expect(badge.classList.contains('bg-success/10')).toBe(false);
        expect(badge.classList.contains('bg-danger/10')).toBe(false);
    });

    it('kode tak dikenal tanpa label memakai kode sebagai teks muted', () => {
        render(<StatusCapaianBadge kode="status_lama" />);

        harapkanVarian('status_lama', ['bg-soft', 'text-muted']);
    });

    it('tanpa kode dan label tidak merender apa pun', () => {
        const { container } = render(<StatusCapaianBadge kode={null} label={null} />);

        expect(container.textContent).toBe('');
        expect(container.querySelector('span')).toBeNull();
    });
});
