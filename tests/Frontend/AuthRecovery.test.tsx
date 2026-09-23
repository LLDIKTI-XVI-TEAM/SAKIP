import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import type { HttpExceptionResponse } from '@inertiajs/core';
import { classifyRecovery } from '@/lib/authRecovery';
import { AuthRecoveryFallback } from '@/Components/Auth/AuthRecoveryFallback';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';

const response = (status: number, data: HttpExceptionResponse['data'] = {}): HttpExceptionResponse => ({ status, data, headers: {} });
const marker = (method = 'POST', path = '/regulasi') => ({ recovery: { reason: 'authentication_required', rejected: { method, path, before_action: true } } });
const request = { effectiveMethod: 'post' as const, path: '/regulasi', mutation: true };

afterEach(cleanup);
const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;
afterAll(() => { HTMLDialogElement.prototype.showModal = originalShowModal; HTMLDialogElement.prototype.close = originalClose; });
beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});

it('hanya menyatakan rejected bila provenance, path dan method efektif cocok', () => {
    expect(classifyRecovery(response(401, marker()), request)?.outcome).toBe('rejected');
    expect(classifyRecovery(response(401, JSON.stringify(marker('PUT'))), { ...request, effectiveMethod: 'put' })?.outcome).toBe('rejected');
    for (const body of [marker('GET'), marker('POST', '/lain'), {}, '{bad', { recovery: { reason: 'csrf_mismatch', rejected: marker().recovery.rejected } }]) {
        expect(classifyRecovery(response(401, body), request)?.outcome).toBe('unknown');
    }
    expect(classifyRecovery(response(419), request)).toEqual({ reason: 'csrf_mismatch', outcome: 'unknown' });
    expect(classifyRecovery(response(401, marker()))?.outcome).toBe('unknown');
    expect(classifyRecovery(response(401), { ...request, mutation: false })?.outcome).toBe('not-a-mutation');
    for (const status of [403, 409, 422, 500]) expect(classifyRecovery(response(status), request)).toBeNull();
});

it('notice memberi peringatan draft dan CTA yang berbeda tanpa replay', () => {
    const { rerender } = render(<AuthRecoveryNotice recovery={{ reason: 'authentication_required', outcome: 'rejected' }} />);
    expect(screen.getByRole('alert').textContent).toContain('ditolak');
    expect(screen.getByText(/berkas perlu dipilih kembali/i)).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Masuk ulang' }).getAttribute('href')).toBe('/login?recovery=1');
    rerender(<AuthRecoveryNotice recovery={{ reason: 'csrf_mismatch', outcome: 'unknown' }} />);
    expect(screen.getByText(/belum dapat dipastikan/i)).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Muat ulang halaman' }).getAttribute('type')).toBe('button');
});

it('fallback membatalkan UI default, satu dialog, dismiss mengembalikan focus dan cleanup listener', async () => {
    const user = userEvent.setup();
    const { unmount } = render(<><main><h1>Halaman asal</h1></main><button>Asal fokus</button><AuthRecoveryFallback /></>);
    const trigger = screen.getByRole<HTMLButtonElement>('button', { name: 'Asal fokus' });
    trigger.focus();
    const fire = (status: number) => {
        const event = new CustomEvent('inertia:httpException', { cancelable: true, detail: { response: response(status) } });
        act(() => { document.dispatchEvent(event); });
        return event;
    };
    expect(fire(500).defaultPrevented).toBe(false);
    expect(fire(401).defaultPrevented).toBe(true);
    expect(document.activeElement?.textContent).toContain('Autentikasi diperlukan');
    expect(fire(419).defaultPrevented).toBe(true);
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    await user.click(screen.getByRole('button', { name: 'Tetap di halaman' }));
    expect(document.activeElement).toBe(trigger);
    fire(401);
    trigger.disabled = true;
    await user.click(screen.getByRole('button', { name: 'Tetap di halaman' }));
    expect(document.activeElement).toBe(screen.getByRole('heading', { name: 'Halaman asal' }));
    unmount();
    expect(fire(401).defaultPrevented).toBe(false);
});
