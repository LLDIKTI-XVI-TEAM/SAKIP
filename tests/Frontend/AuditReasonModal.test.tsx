import { cleanup, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';
import { AuditReasonModal } from '@/Components/AuditReasonModal';

const methods = ['showModal', 'close'] as const;
const originals = methods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => methods.forEach((name, index) => {
    const descriptor = originals[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));
afterEach(cleanup);

const dasar = {
    title: 'Konfirmasi pergantian PJ',
    reason: '',
    confirmLabel: 'Simpan pergantian',
    onReasonChange: () => {},
    onClose: () => {},
    onConfirm: () => {},
};

describe('AuditReasonModal dengan kolom alasan disembunyikan', () => {
    it('menampilkan notice dan error walau kolom alasan tidak dirender', () => {
        render(<AuditReasonModal {...dasar} open hideReasonField
            notice={<p>Pemulihan sesi tersedia.</p>}
            error="Tanggal mulai berlaku tidak valid." />);

        expect(screen.getByText('Pemulihan sesi tersedia.')).toBeTruthy();
        expect(screen.getByRole('alert').textContent).toBe('Tanggal mulai berlaku tidak valid.');
        expect(screen.queryByRole('textbox')).toBeNull();
        expect(screen.queryByLabelText(/Alasan/)).toBeNull();
    });

    it('menampilkan textarea dan error pada tampilan normal', () => {
        render(<AuditReasonModal {...dasar} open error="Alasan wajib diisi." />);

        expect(screen.getByRole('textbox')).toBeTruthy();
        expect(screen.getByRole('alert').textContent).toBe('Alasan wajib diisi.');
    });
});
