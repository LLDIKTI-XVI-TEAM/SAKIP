import React from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BackButton } from '@/Components/BackButton';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, className, onClick }: any) => (
        <a href={href} className={className} onClick={onClick}>
            {children}
        </a>
    ),
}));

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('BackButton Component', () => {
    it('merender teks default "Kembali" dan menggunakan tag button saat href tidak diberikan', () => {
        const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});

        render(<BackButton />);

        const button = screen.getByRole('button', { name: /kembali/i });
        expect(button).toBeTruthy();

        fireEvent.click(button);
        expect(historyBackSpy).toHaveBeenCalledOnce();
    });

    it('merender sebagai Link saat href diberikan dan menampilkan children custom', () => {
        render(
            <BackButton href="/pengukuran">
                Kembali ke daftar pengukuran
            </BackButton>
        );

        const link = screen.getByRole('link', { name: /kembali ke daftar pengukuran/i });
        expect(link).toBeTruthy();
        expect(link.getAttribute('href')).toBe('/pengukuran');
    });

    it('mendukung props label dan varian subtle', () => {
        render(
            <BackButton href="/verifikasi" label="Kembali ke antrean" variant="subtle" size="sm" />
        );

        const link = screen.getByRole('link', { name: /kembali ke antrean/i });
        expect(link).toBeTruthy();
        expect(link.className).toContain('text-muted');
        expect(link.className).toContain('text-xs');
    });

    it('memanggil onClick custom saat disediakan', () => {
        const handleClick = vi.fn();
        render(<BackButton onClick={handleClick}>Kembali</BackButton>);

        const button = screen.getByRole('button', { name: /kembali/i });
        fireEvent.click(button);
        expect(handleClick).toHaveBeenCalledOnce();
    });
});
