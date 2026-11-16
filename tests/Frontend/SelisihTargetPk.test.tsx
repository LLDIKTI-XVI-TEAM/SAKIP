import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { SelisihTargetPk } from '@/Components/Pengukuran/SelisihTargetPk';
import { formatNilai } from '@/Pages/Pengukuran/formatNilai';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { pengaturan: {} } }),
}));

afterEach(cleanup);

it('menampilkan selisih negatif bertanda dalam satuan indikator dan warnanya mengikuti arah naik', () => {
    render(<SelisihTargetPk selisih="-3.570000000000" arah="naik_baik" satuan="poin" desimal={2} />);

    const nilai = screen.getByText('−3,57 poin');
    expect(screen.getByText('di bawah target')).toBeTruthy();
    expect(nilai.className).toContain('text-danger-dark');
    // Rata kanan hanya saat tabel status tampil sebagai tabel (xl); di kartu tetap rata kiri.
    expect(nilai.parentElement?.className).toContain('xl:items-end');
});

it('selisih negatif pada indikator arah turun berwarna baik', () => {
    render(<SelisihTargetPk selisih="-3.57" arah="turun_baik" satuan="%" desimal={2} />);

    expect(screen.getByText('−3,57 %').className).toContain('text-success-dark');
    expect(screen.getByText('di bawah target')).toBeTruthy();
});

it('selisih positif diberi tanda tambah dan teks di atas target', () => {
    render(<SelisihTargetPk selisih="10" arah="naik_baik" satuan="PT" desimal={0} />);

    expect(screen.getByText('+10 PT').className).toContain('text-success-dark');
    expect(screen.getByText('di atas target')).toBeTruthy();
});

it('selisih nol dan kosong', () => {
    const { rerender } = render(<SelisihTargetPk selisih="0.000000000000" arah="naik_baik" satuan="poin" desimal={2} />);
    expect(screen.getByText('0,00 poin')).toBeTruthy();
    expect(screen.getByText('sama dengan target')).toBeTruthy();

    rerender(<SelisihTargetPk selisih={null} arah="naik_baik" satuan="poin" desimal={2} />);
    expect(screen.getByText('—')).toBeTruthy();
    expect(screen.queryByText(/target/)).toBeNull();
});

it('selisih yang membulat ke nol tampil sebagai sama dengan target, bukan −0,00', () => {
    render(<SelisihTargetPk selisih="-0.004" arah="naik_baik" satuan="poin" desimal={2} />);

    expect(screen.getByText('0,00 poin').className).toContain('text-muted');
    expect(screen.getByText('sama dengan target')).toBeTruthy();
    expect(screen.queryByText(/−/)).toBeNull();
});

it('selisih pada batas pembulatan desimal dibulatkan seperti halaman Pengukuran, tanpa konversi Number', () => {
    const { rerender } = render(<SelisihTargetPk selisih="1.005" arah="naik_baik" satuan="poin" desimal={2} />);
    expect(screen.getByText(`+${formatNilai('1.005', 2)} poin`)).toBeTruthy();
    expect(screen.getByText('+1,01 poin')).toBeTruthy();

    rerender(<SelisihTargetPk selisih="-2.675" arah="naik_baik" satuan="poin" desimal={2} />);
    expect(screen.getByText('−2,68 poin')).toBeTruthy();
    expect(screen.getByText('di bawah target')).toBeTruthy();
});
