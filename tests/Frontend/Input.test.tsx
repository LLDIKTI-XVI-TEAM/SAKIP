import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
import { Input } from '@/Components/Input';

afterEach(cleanup);

it('Input mempertahankan gaya label dan hubungan aksesibel ke error tanpa ID eksplisit', () => {
    render(<Input label="Nama" labelClassName="whitespace-nowrap" error="Nama wajib diisi" />);

    const input = screen.getByRole('textbox', { name: 'Nama' });
    const label = screen.getByText('Nama');
    const error = screen.getByRole('alert');

    expect(label.classList.contains('whitespace-nowrap')).toBe(true);
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(input.getAttribute('aria-describedby')).toBe(error.id);
    expect(input.hasAttribute('labelClassName')).toBe(false);
});

it('Input menggabungkan aria-describedby pemanggil dengan helper tanpa saling menimpa', () => {
    render(<Input id="x" label="Nilai" helperText="Bantuan" aria-describedby="luar" />);

    expect(screen.getByRole('textbox', { name: 'Nilai' }).getAttribute('aria-describedby')?.split(' ')).toEqual(expect.arrayContaining(['luar', 'x-help']));
});
