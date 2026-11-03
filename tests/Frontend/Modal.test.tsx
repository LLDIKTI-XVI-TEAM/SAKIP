import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Modal } from '@/Components/Modal';

afterEach(cleanup);

describe('Modal Accessibility', () => {
    it('fokus awal dan Tab mengabaikan field efektif disabled melalui fieldset', () => {
        vi.useFakeTimers();
        const layout = vi.spyOn(HTMLElement.prototype, 'offsetParent', 'get').mockReturnValue(document.body);
        try {
            render(<Modal isOpen title="Recovery kegiatan" onClose={vi.fn()} showCloseButton={false}>
                <a href="/kegiatan">Muat ulang</a>
                <fieldset disabled><input aria-label="Draft" /></fieldset>
            </Modal>);
            act(() => vi.runOnlyPendingTimers());
            const reload = screen.getByRole('link', { name: 'Muat ulang' });
            expect(document.activeElement).toBe(reload);
            for (const shiftKey of [false, true]) {
                const event = new KeyboardEvent('keydown', { key: 'Tab', shiftKey, bubbles: true, cancelable: true });
                window.dispatchEvent(event);
                expect(event.defaultPrevented).toBe(true);
                expect(document.activeElement).toBe(reload);
            }
        } finally {
            layout.mockRestore();
            cleanup();
            vi.useRealTimers();
        }
    });
    it('Tab menjangkau ringkasan details sehingga isi yang terlipat dapat dibuka dengan keyboard', () => {
        vi.useFakeTimers();
        const layout = vi.spyOn(HTMLElement.prototype, 'offsetParent', 'get').mockReturnValue(document.body);
        try {
            render(<Modal isOpen title="Histori revisi" onClose={vi.fn()}>
                <details><summary>Revisi ke-1</summary><p>Rincian</p></details>
            </Modal>);
            act(() => vi.runOnlyPendingTimers());
            const tutup = screen.getByRole('button', { name: 'Tutup dialog' });
            const ringkasan = screen.getByText('Revisi ke-1');
            expect(document.activeElement).toBe(tutup);

            const mundur = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true });
            window.dispatchEvent(mundur);
            expect(document.activeElement).toBe(ringkasan);
            const maju = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
            window.dispatchEvent(maju);
            expect([maju.defaultPrevented, document.activeElement]).toEqual([true, tutup]);
        } finally {
            layout.mockRestore();
            cleanup();
            vi.useRealTimers();
        }
    });
    it.each([false, true])('fokus awal tidak mengambil alih field yang sudah dipilih: %s', (alreadyFocused) => {
        vi.useFakeTimers();
        try {
            render(
                <Modal isOpen={true} onClose={vi.fn()} title="Form regulasi">
                    <select aria-label="Jenis">
                        <option>Keputusan</option>
                    </select>
                    <input aria-label="Nomor" />
                </Modal>
            );
            const number = screen.getByRole('textbox', { name: 'Nomor' });
            if (alreadyFocused) number.focus();

            act(() => vi.runOnlyPendingTimers());

            expect(document.activeElement).toBe(
                alreadyFocused ? number : screen.getByRole('combobox', { name: 'Jenis' })
            );
        } finally {
            cleanup();
            vi.useRealTimers();
        }
    });

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

    it('menerapkan kelas overflow-y-auto dan menyembunyikan scrollbar visual ketika hideScrollbar bernilai true', () => {
        const onClose = vi.fn();
        render(
            <Modal
                isOpen={true}
                onClose={onClose}
                title="Modal Tanpa Scrollbar Visual"
                hideScrollbar={true}
                bodyClassName="p-4"
            >
                <div data-testid="modal-content">Isi Modal</div>
            </Modal>
        );

        const content = screen.getByTestId('modal-content');
        const contentContainer = content.parentElement;
        expect(contentContainer).not.toBeNull();
        expect(contentContainer?.className).toContain('overflow-y-auto');
        expect(contentContainer?.className).toContain('no-scrollbar');
        expect(contentContainer?.className).toContain('p-4');
        expect(contentContainer?.className).not.toContain('overflow-hidden');
    });

    it('merender dialog ke document.body hanya ketika portal aktif', () => {
        const portalView = render(
            <Modal isOpen portal onClose={vi.fn()} title="Dialog Portal">
                <p>Isi portal</p>
            </Modal>
        );
        const dialogPortal = screen.getByRole('dialog', { name: 'Dialog Portal' });
        expect(dialogPortal.parentElement).toBe(document.body);
        expect(portalView.container.contains(dialogPortal)).toBe(false);
        portalView.unmount();

        const inlineView = render(
            <div data-testid="wadah">
                <Modal isOpen onClose={vi.fn()} title="Dialog Inline">
                    <p>Isi inline</p>
                </Modal>
            </div>
        );
        const dialogInline = screen.getByRole('dialog', { name: 'Dialog Inline' });
        expect(dialogInline.parentElement).not.toBe(document.body);
        expect(inlineView.container.contains(dialogInline)).toBe(true);
        inlineView.unmount();
    });

    it('portal mempertahankan scroll lock, Escape, klik overlay, dan pengembalian fokus', () => {
        vi.useFakeTimers();
        const pemicu = document.createElement('button');
        pemicu.textContent = 'Buka dialog';
        document.body.appendChild(pemicu);
        pemicu.focus();
        const onClose = vi.fn();
        try {
            const view = render(
                <Modal isOpen portal onClose={onClose} title="Dialog Portal">
                    <button type="button">Aksi</button>
                </Modal>
            );
            expect(document.body.style.overflow).toBe('hidden');

            act(() => vi.runOnlyPendingTimers());
            expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Tutup dialog' }));

            const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
            window.dispatchEvent(escape);
            expect(onClose).toHaveBeenCalledTimes(1);

            // Klik pada overlay (bukan panel) tetap menutup dialog yang sudah dipindah ke portal.
            fireEvent.click(screen.getByRole('dialog', { name: 'Dialog Portal' }));
            expect(onClose).toHaveBeenCalledTimes(2);

            view.unmount();
            expect(document.body.style.overflow).toBe('unset');
            expect(document.activeElement).toBe(pemicu);
        } finally {
            pemicu.remove();
            cleanup();
            vi.useRealTimers();
        }
    });
});
