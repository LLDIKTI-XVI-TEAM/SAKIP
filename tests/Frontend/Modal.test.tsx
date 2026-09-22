import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Modal } from '@/Components/Modal';

afterEach(cleanup);

describe('Modal Accessibility', () => {
    it('menghubungkan aria-labelledby dan aria-describedby ke ID judul dan deskripsi dialog', () => {
        const onClose = vi.fn();
        render(
            <Modal
                isOpen={true}
                onClose={onClose}
                title="Judul Modal Aksesibel"
                description="Deskripsi petunjuk penggunaan dialog"
            >
                <p>Isi Konten Dialog</p>
            </Modal>
        );

        const dialog = screen.getByRole('dialog');
        expect(dialog).not.toBeNull();

        const labelledBy = dialog.getAttribute('aria-labelledby');
        const describedBy = dialog.getAttribute('aria-describedby');

        expect(labelledBy).toBeTruthy();
        expect(describedBy).toBeTruthy();

        const titleEl = document.getElementById(labelledBy!);
        const descEl = document.getElementById(describedBy!);

        expect(titleEl).not.toBeNull();
        expect(descEl).not.toBeNull();
        expect(titleEl?.textContent).toContain('Judul Modal Aksesibel');
        expect(descEl?.textContent).toContain('Deskripsi petunjuk penggunaan dialog');
    });

    it('menggunakan aria-label ketika title tidak berupa teks node visual', () => {
        const onClose = vi.fn();
        render(
            <Modal
                isOpen={true}
                onClose={onClose}
                ariaLabel="Nama Dialog Mandiri"
            >
                <p>Isi Dialog</p>
            </Modal>
        );

        const dialog = screen.getByRole('dialog', { name: 'Nama Dialog Mandiri' });
        expect(dialog).not.toBeNull();
        expect(dialog.getAttribute('aria-modal')).toBe('true');
    });
});
