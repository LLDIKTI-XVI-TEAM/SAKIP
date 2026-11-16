import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Select } from '@/Components/Select';

afterEach(cleanup);

describe('Select', () => {
    it('menghubungkan label ke select saat id dan name tidak diberikan', () => {
        render(
            <Select label="Status">
                <option value="aktif">Aktif</option>
            </Select>,
        );

        const select = screen.getByRole<HTMLSelectElement>('combobox', { name: 'Status' });

        expect(select.id).not.toBe('');
        expect(screen.getByText('Status').getAttribute('for')).toBe(select.id);
    });

    it('merender overlay HoverScrollText saat opsi terpilih dan prop hoverScroll aktif', () => {
        render(
            <Select
                label="Unit"
                hoverScroll
                value="unit-1"
                onChange={() => {}}
                options={[
                    { value: '', label: 'Pilih unit' },
                    { value: 'unit-1', label: 'Bagian Kelembagaan dan Sistem Informasi' },
                ]}
            />,
        );

        const select = screen.getByRole<HTMLSelectElement>('combobox', { name: 'Unit' });
        expect(select.classList.contains('text-transparent')).toBe(true);

        const hoverText = screen.getByTitle('Bagian Kelembagaan dan Sistem Informasi');
        expect(hoverText).toBeTruthy();
        expect(hoverText.textContent).toContain('Bagian Kelembagaan dan Sistem Informasi');
    });

    it('menyerahkan klik ke select native tanpa memanggil API picker dari overlay', () => {
        render(<Select label="Unit" hoverScroll value="a" onChange={() => {}} options={[{ value: 'a', label: 'Unit A' }]} />);
        const select = screen.getByRole<HTMLSelectElement>('combobox', { name: 'Unit' });
        const picker = vi.fn(() => { throw new Error('Picker tidak tersedia'); });
        Object.defineProperty(select, 'showPicker', { value: picker });
        fireEvent.click(screen.getByTitle('Unit A'));
        expect(picker).not.toHaveBeenCalled();
    });
});
