import type { ReactNode } from 'react';
import { useState } from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Show from '@/Pages/PenanggungJawab/Show';
import type { AssignmentDetailProps } from '@/types/penanggung-jawab';
import type { Page, VisitOptions } from '@inertiajs/core';

const transport = vi.hoisted(() => ({ post: vi.fn(), reload: vi.fn() }));
vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()), Head: () => null,
    router: { reload: transport.reload, get: vi.fn() },
    useForm: (initial: { user_id: string; tanggal_mulai_berlaku: string; alasan: string; expected_state: string }) => {
        const [data, setData] = useState(initial);
        return { data, processing: false, errors: {}, clearErrors: vi.fn(),
            setData: (key: keyof typeof initial | typeof initial, value?: string) => setData((current) => typeof key === 'string' ? { ...current, [key]: value } : key),
            post: (path: string, options: VisitOptions) => transport.post(path, data, options) };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock('@/Components/Access/GrantUserAutocomplete', () => ({ GrantUserAutocomplete: ({ value, onChange, disabled }: { value: string; onChange: (value: string) => void; disabled: boolean }) =>
    <select aria-label="Penanggung jawab" value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled}><option value="">Pilih</option><option value="user-b">Jordan QA</option></select> }));
vi.mock('@/Components/AuditReasonModal', () => ({ AuditReasonModal: ({ open, reason, onReasonChange, onConfirm, notice, submitDisabled }: { open: boolean; reason: string; onReasonChange: (value: string) => void; onConfirm: () => void; notice: ReactNode; submitDisabled: boolean }) =>
    open ? <div role="dialog">{notice}<label>Alasan perubahan<textarea value={reason} onChange={(event) => onReasonChange(event.target.value)} /></label><button onClick={onConfirm} disabled={submitDisabled}>Simpan pergantian</button></div> : null }));

function page(component: string, props: Record<string, unknown>): Page {
    return { component, props: { errors: {}, ...props }, url: '/penanggung-jawab', version: null, rescuedProps: [], flash: {}, rememberedState: {} };
}
const props: AssignmentDetailProps = {
    indicator: { id: 'indicator-a', kode: 'I-PJ', nama: 'Indikator QA', status: 'aktif' }, unit: { id: 'unit-a', nama: 'Unit QA', status: 'aktif' },
    renstra: null, effective: null, readiness: null, history: { data: [], prev_page_url: null, next_page_url: null },
    has_history: true, expected_state: 'old-token', tanggal_acuan: '2026-03-15', today: '2026-03-15', blocked_reason: null,
    can: { assign: true }, saved_assignment_id: null,
};
afterEach(() => { cleanup(); vi.clearAllMocks(); vi.unstubAllGlobals(); });
function draft() {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
    render(<Show {...props} />);
    fireEvent.change(screen.getByLabelText('Penanggung jawab'), { target: { value: 'user-b' } });
    fireEvent.change(screen.getByLabelText(/Tanggal mulai berlaku/), { target: { value: '2026-02-01' } });
    fireEvent.click(screen.getByRole('button', { name: 'Lanjutkan pergantian' }));
    fireEvent.change(screen.getByLabelText('Alasan perubahan'), { target: { value: 'Pergantian tanggung jawab' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan pergantian' }));
}
describe('Form PJ mempertahankan draft dan tidak mengulang mutasi otomatis', () => {
    it('memblokir kirim ulang setelah network error sampai pembacaan authoritative lalu review manual', async () => {
        draft();
        const callbacks = transport.post.mock.calls[0][2] as VisitOptions;
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pergantian' }));
        await import('@testing-library/react').then(({ act }) => act(() => { callbacks.onNetworkError?.(new Error('offline')); }));
        expect((screen.getByLabelText('Alasan perubahan') as HTMLTextAreaElement).value).toBe('Pergantian tanggung jawab');
        const beforeReload = transport.post.mock.calls.length;
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pergantian' }));
        expect(transport.post).toHaveBeenCalledTimes(beforeReload);
        fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
        const reload = transport.reload.mock.calls[0][0] as VisitOptions;
        await import('@testing-library/react').then(({ act }) => act(() => { reload.onSuccess?.(page('PenanggungJawab/Show', { expected_state: 'new-token' })); reload.onFinish?.({} as Parameters<NonNullable<VisitOptions['onFinish']>>[0]); }));
        expect(transport.post).toHaveBeenCalledTimes(beforeReload);
        fireEvent.click(screen.getByRole('button', { name: 'Simpan pergantian' }));
        expect(transport.post.mock.calls.at(-1)?.[1]).toEqual({ user_id: 'user-b', tanggal_mulai_berlaku: '2026-02-01', alasan: 'Pergantian tanggung jawab', expected_state: 'new-token' });
    });
    it('redirect tanpa marker sukses tidak mengosongkan draft atau menutup modal', async () => {
        draft();
        const callbacks = transport.post.mock.calls[0][2] as VisitOptions;
        await import('@testing-library/react').then(({ act }) => act(() => { callbacks.onSuccess?.(page('Auth/Pending', {})); }));
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect((screen.getByLabelText('Alasan perubahan') as HTMLTextAreaElement).value).toBe('Pergantian tanggung jawab');
        expect(screen.getByText(/Penyimpanan belum terkonfirmasi/)).toBeTruthy();
    });
    it('konfirmasi sukses authoritative mengosongkan draft dan menutup modal', async () => {
        draft();
        const callbacks = transport.post.mock.calls[0][2] as VisitOptions;
        await import('@testing-library/react').then(({ act }) => act(() => { callbacks.onSuccess?.(page('PenanggungJawab/Show', { saved_assignment_id: 'assignment-new', expected_state: 'new-token', today: '2026-03-15' })); }));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect((screen.getByLabelText('Penanggung jawab') as HTMLSelectElement).value).toBe('');
    });
});
