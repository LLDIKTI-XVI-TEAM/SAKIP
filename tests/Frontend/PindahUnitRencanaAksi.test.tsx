import { router } from '@inertiajs/react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, expect, it, vi } from 'vitest';
import PindahUnit from '@/Pages/RencanaAksi/PindahUnit';

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
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

it('menjelaskan koreksi dan mengirim alasan serta versi', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<PindahUnit id="ra-1" versi={4} />);
    await user.click(screen.getByRole('button', { name: 'Pindahkan unit Rencana Aksi' }));
    expect(screen.getByText(/Setelah pemindahan, lakukan koreksi snapshot/)).toBeTruthy();
    await user.type(screen.getByRole('textbox'), 'Perubahan penanggung jawab');
    await user.click(screen.getByRole('button', { name: 'Pindahkan unit' }));
    expect(post).toHaveBeenCalledWith('/rencana-aksi/ra-1/pindah-unit', { expected_versi: 4, alasan: 'Perubahan penanggung jawab' }, expect.any(Object));
});
