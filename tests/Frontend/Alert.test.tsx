import React from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { Alert } from '@/Components/Alert';

describe('Alert Component', () => {
    afterEach(() => {
        cleanup();
    });
    it('renders with default info variant', () => {
        render(<Alert title="Info Title">Informasi penting</Alert>);
        expect(screen.getByRole('status')).toBeTruthy();
        expect(screen.getByText('Info Title')).toBeTruthy();
        expect(screen.getByText('Informasi penting')).toBeTruthy();
    });

    it('renders warning variant with alert role', () => {
        render(<Alert variant="warning" title="Peringatan">Tindakan ini memerlukan perhatian.</Alert>);
        expect(screen.getByRole('alert')).toBeTruthy();
        expect(screen.getByText('Peringatan')).toBeTruthy();
    });

    it('renders danger variant with alert role and error content', () => {
        render(<Alert variant="danger">Terjadi kesalahan validasi.</Alert>);
        expect(screen.getByRole('alert')).toBeTruthy();
        expect(screen.getByText('Terjadi kesalahan validasi.')).toBeTruthy();
    });

    it('renders success variant', () => {
        render(<Alert variant="success">Berhasil disimpan.</Alert>);
        expect(screen.getByRole('status')).toBeTruthy();
        expect(screen.getByText('Berhasil disimpan.')).toBeTruthy();
    });

    it('forwards ref properly', () => {
        const ref = React.createRef<HTMLDivElement>();
        render(<Alert ref={ref} tabIndex={-1}>Focusable Alert</Alert>);
        expect(ref.current).not.toBeNull();
        ref.current?.focus();
        expect(document.activeElement).toBe(ref.current);
    });
});
