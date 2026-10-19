import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { SelisihTargetPk } from '@/Components/Pengukuran/SelisihTargetPk';

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
