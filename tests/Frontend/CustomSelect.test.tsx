import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CustomSelect } from '@/Components/CustomSelect';

afterEach(cleanup);

describe('CustomSelect', () => {
    const defaultOptions = [
        { value: '1', label: 'Renstra LLDIKTI 16 (2020-2024)' },
        { value: '2', label: 'Renstra LLDIKTI 16 (2025-2029)' },
        { value: '3', label: 'Renstra LLDIKTI 16 (2030-2034)', disabled: true },
    ];

    it('merender label dan placeholder dengan benar', () => {
        render(
            <CustomSelect
                label="Periode Renstra Induk"
                placeholder="-- Pilih Rencana Strategis --"
                options={defaultOptions}
                value=""
            />
        );

        expect(screen.getByText('Periode Renstra Induk')).toBeDefined();
        expect(screen.getByText('-- Pilih Rencana Strategis --')).toBeDefined();
    });

    it('menampilkan opsi terpilih dengan benar', () => {
        render(
            <CustomSelect
                label="Periode Renstra Induk"
                options={defaultOptions}
                value="2"
            />
        );

        expect(screen.getByText('Renstra LLDIKTI 16 (2025-2029)')).toBeDefined();
    });

    it('dapat membuka dropdown listbox dan memilih opsi', () => {
        const handleChange = vi.fn();
        render(
            <CustomSelect
                label="Periode Renstra Induk"
                options={defaultOptions}
                value=""
                onChange={handleChange}
            />
        );

        const trigger = screen.getByRole('button', { name: /Periode Renstra Induk/i });
        fireEvent.click(trigger);

        // Listbox should be visible
        const listbox = screen.getByRole('listbox');
        expect(listbox).toBeDefined();

        // Click on the first option
        const option1 = screen.getByRole('option', { name: /2020-2024/i });
        fireEvent.click(option1);

        expect(handleChange).toHaveBeenCalledWith('1');
    });

    it('menutup dropdown saat tombol Escape ditekan', () => {
        render(
            <CustomSelect
                label="Periode Renstra Induk"
                options={defaultOptions}
                value=""
            />
        );

        const trigger = screen.getByRole('button', { name: /Periode Renstra Induk/i });
        fireEvent.click(trigger);
        expect(screen.getByRole('listbox')).toBeDefined();

        fireEvent.keyDown(document, { key: 'Escape' });
        expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('tidak dapat dibuka jika disabled', () => {
        render(
            <CustomSelect
                label="Periode Renstra Induk"
                options={defaultOptions}
                value=""
                disabled
            />
        );

        const trigger = screen.getByRole('button', { name: /Periode Renstra Induk/i });
        expect(trigger.hasAttribute('disabled')).toBe(true);

        fireEvent.click(trigger);
        expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('menampilkan pesan error jika diberikan', () => {
        render(
            <CustomSelect
                label="Periode Renstra Induk"
                options={defaultOptions}
                value=""
                error="Renstra wajib dipilih."
            />
        );

        expect(screen.getByText('Renstra wajib dipilih.')).toBeDefined();
    });
});
