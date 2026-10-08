import type { ReactNode } from 'react';
import { useState } from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Show from '@/Pages/PenanggungJawab/Show';
import type { AssignmentDetailProps } from '@/types/penanggung-jawab';
import type { Page, VisitOptions } from '@inertiajs/core';

const transport = vi.hoisted(() => ({ post: vi.fn(), reload: vi.fn(), get: vi.fn(), pengaturan: {} as Record<string, string> }));
vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()), Head: () => null,
    router: { reload: transport.reload, get: transport.get },
    usePage: () => ({ props: { pengaturan: transport.pengaturan } }),
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
beforeEach(() => { transport.pengaturan = {}; });
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

describe('Tampilan histori PJ mengikuti preferensi tanpa mengubah tanggal API', () => {
    function historyProps(createdAt: string | null): AssignmentDetailProps {
        const assignment = { id: 'assignment-a', tanggal_mulai_berlaku: '2026-02-01', pic: { id: 'user-a', nama: 'PJ QA', status: 'aktif' },
            ditetapkan_oleh: { id: 'actor-a', nama: 'Perencanaan QA' }, alasan: 'Penetapan awal QA', created_at: createdAt, state: 'Efektif' };
        return { ...props, effective: assignment, history: { ...props.history, data: [assignment] } };
    }

    it.each([
        ['Asia/Makassar', 'd F Y', '02 Februari 2026 00:30'],
        ['Asia/Jakarta', 'd/m/Y', '01/02/2026 23:30'],
        ['UTC', 'Y-m-d', '2026-02-01 16:30'],
    ])('memakai zona %s dan format %s untuk timestamp histori', (zone, format, expected) => {
        transport.pengaturan = { 'tampilan.zona_waktu': zone, 'tampilan.format_tanggal': format };
        render(<Show {...historyProps('2026-02-01T16:30:00Z')} />);
        expect(screen.getByText(`Dicatat ${expected}`)).toBeTruthy();
        expect(screen.getByText('Mulai berlaku 2026-02-01', { exact: false })).toBeTruthy();
        expect((screen.getByLabelText(/Tanggal mulai berlaku/) as HTMLInputElement).value).toBe(props.today);
        fireEvent.change(screen.getByLabelText(/PJ efektif pada tanggal/), { target: { value: '2026-02-01' } });
        fireEvent.click(screen.getByRole('button', { name: 'Tampilkan' }));
        expect(transport.get).toHaveBeenCalledWith('/perencanaan/indikator/indicator-a/penanggung-jawab', { tanggal_acuan: '2026-02-01' }, { preserveState: true, preserveScroll: true });
    });

    it.each([null, ''])('aman saat timestamp kosong: %s', (createdAt) => {
        render(<Show {...historyProps(createdAt)} />);
        expect(screen.getByText('Dicatat Tidak tersedia')).toBeTruthy();
    });

    it('menampilkan dua penugasan bertanggal sama sesuai urutan server', () => {
        const base = historyProps('2026-02-01T01:00:00Z');
        const [latest] = base.history.data;
        const replaced = { ...latest, id: 'assignment-b', pic: { id: 'user-b', nama: 'PJ Lama', status: 'aktif' }, state: 'Riwayat' };
        render(<Show {...base} history={{ ...base.history, data: [latest, replaced] }} />);
        const rows = screen.getAllByRole('listitem');
        expect(rows.map((row) => row.textContent)).toEqual([expect.stringMatching(/^PJ QAEfektifBerlaku 2026-02-01/), expect.stringMatching(/^PJ LamaRiwayatBerlaku 2026-02-01/)]);
    });
});
