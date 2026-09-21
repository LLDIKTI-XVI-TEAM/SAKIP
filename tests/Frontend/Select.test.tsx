import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
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
});
