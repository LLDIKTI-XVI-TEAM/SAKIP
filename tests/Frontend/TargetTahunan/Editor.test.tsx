import type { ActiveVisit, HttpRequestConfig, HttpResponse, Page, PendingVisit } from '@inertiajs/core';
import { createInertiaApp, router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { Editor } from '@/Pages/TargetTahunan/Editor';
import type { TargetEditorData } from '@/Pages/TargetTahunan/types';

const editor: TargetEditorData = {
    indikator_id: '11111111-1111-4111-8111-111111111111', tahun: 2026, baseline_year: 2025,
    indikator: { id: '11111111-1111-4111-8111-111111111111', kode: 'IKU 3', nama: 'Indikator pengujian', satuan: 'nilai', presisi: 2, desimal_tampilan: 1, status: 'aktif', tahun_mulai_berlaku: 2025 },
    renstra: { id: '22222222-2222-4222-8222-222222222222', nama: 'Renstra pengujian', status: 'aktif', tahun_mulai: 2025, tahun_selesai: 2029 },
    baseline: '74.2345', target_tahunan: '76.25', has_snapshot: true, expected_state: 'a'.repeat(64), can: { update: true }, read_only_reason: null,
};
const onClose = vi.fn(), onLoad = vi.fn(), onSaved = vi.fn();
const EditorHost = () => <Editor editor={editor} onClose={onClose} onLoad={onLoad} onSaved={onSaved} />;
const show = (changes: Partial<TargetEditorData> = {}) => render(<Editor editor={{ ...editor, ...changes }} onClose={onClose} onLoad={onLoad} onSaved={onSaved} />);
const submit = () => { fireEvent.click(screen.getByRole('button', { name: 'Simpan' })); return vi.mocked(router.put).mock.calls.at(-1)?.[2]; };
const page = (outcome: unknown): Page => ({ component: 'Perencanaan/SasaranIndikator/Index', props: { errors: {} }, url: '/perencanaan/sasaran-indikator', version: null, clearHistory: false, encryptHistory: false, rescuedProps: [], rememberedState: {}, flash: { target_tahunan: outcome } });
const payload = (index = -1) => {
    const data = vi.mocked(router.put).mock.calls.at(index)?.[1];
    if (!data || data instanceof FormData) throw new Error('Payload form harus berupa objek.');
    return data;
};
const receipt = (changes: Record<string, unknown> = {}) => ({ operation_id: payload().operation_id, indikator_id: editor.indikator_id, tahun: 2026, target_id: null, changed: false, ...changes });
const confirmation = () => screen.getByRole('dialog', { name: 'Buang perubahan?' });
const keepEditing = () => fireEvent.click(within(confirmation()).getByRole('button', { name: 'Tetap mengedit' }));
const discardChanges = () => fireEvent.click(within(confirmation()).getByRole('button', { name: 'Buang perubahan' }));
const shims = [
    [HTMLDialogElement.prototype, 'showModal', function (this: HTMLDialogElement) { this.open = true; }],
    [HTMLDialogElement.prototype, 'close', function (this: HTMLDialogElement) { this.open = false; }],
    [HTMLElement.prototype, 'scrollTo', () => undefined],
    [window.performance, 'getEntriesByType', () => []],
] as const;
const originals = shims.map(([target, name]) => Object.getOwnPropertyDescriptor(target, name));
beforeAll(() => shims.forEach(([target, name, value]) => Object.defineProperty(target, name, { configurable: true, value })));
afterAll(() => shims.forEach(([target, name], index) => {
    const descriptor = originals[index];
    if (descriptor) Object.defineProperty(target, name, descriptor);
    else Reflect.deleteProperty(target, name);
}));

beforeEach(() => { vi.spyOn(router, 'put').mockImplementation(() => undefined); vi.spyOn(window, 'confirm').mockReturnValue(false); vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined); });
afterEach(() => { router.cancelAll(); cleanup(); vi.restoreAllMocks(); vi.clearAllMocks(); });

it('menjaga angka historis tepat dan membedakan kosong dengan nol pada payload', () => {
    show({ baseline: null, target_tahunan: '0' });
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('');
    expect(screen.getByLabelText<HTMLInputElement>('Target 2026').value).toBe('0');
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '999999999999999999.123456789012' } });
    submit();
    expect(vi.mocked(router.put).mock.calls[0][1]).toMatchObject({ baseline: '999999999999999999.123456789012', target_tahunan: '0', expected_state: editor.expected_state });
});

it('tidak membulatkan baseline ataupun target existing ketika presisi turun', () => {
    show({ indikator: { ...editor.indikator, presisi: 1 } });
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('74.2345');
    expect(screen.getByLabelText<HTMLInputElement>('Target 2026').value).toBe('76.25');
});

it('validasi mempertahankan draft dan versi serta mengikat error ke field', async () => {
    show();
    const field = screen.getByLabelText<HTMLInputElement>('Target 2026');
    fireEvent.change(field, { target: { value: '76,251' } });
    const options = submit();
    await act(async () => { options?.onError?.({ target_tahunan: 'Maksimal 2 desimal.' }); options?.onFinish?.({} as ActiveVisit); });
    expect(field.value).toBe('76,251');
    expect(field.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(field.getAttribute('aria-describedby') ?? '')?.textContent).toContain('Maksimal 2 desimal.');
    expect(onClose).not.toHaveBeenCalled();
    submit();
    expect(payload(1).expected_state).toBe(editor.expected_state);
    expect(payload(1).operation_id).not.toBe(payload(0).operation_id);
});

it.each([{ operation_id: 'other' }, { indikator_id: 'other' }, { tahun: 2027 }, { changed: 'false' }, { changed: true, target_id: '' }])('hasil silang/malformed %j tidak menutup atau mereset form', async (changes) => {
    show(); const options = submit();
    await act(async () => { options?.onSuccess?.(page(receipt(changes))); options?.onFinish?.({} as ActiveVisit); });
    expect(onClose).not.toHaveBeenCalled(); expect(onSaved).not.toHaveBeenCalled();
    expect(screen.getByText(/Hasil simpan belum dapat dipastikan/)).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('74.2345');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);
});

it('hasil perubahan yang cocok menutup modal dengan konfirmasi tersimpan', async () => {
    show(); const options = submit();
    await act(async () => { options?.onSuccess?.(page(receipt({ changed: true, target_id: '33333333-3333-4333-8333-333333333333' }))); });
    expect(onSaved).toHaveBeenCalledWith('Baseline dan target tersimpan.');
    expect(onClose).toHaveBeenCalledOnce();
});

it.each(['Batal', 'Muat data terbaru'])('hasil tidak cocok tetap melindungi draft saat %s setelah callback useForm selesai', async (action) => {
    show();
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75,1234' } });
    const options = submit();
    await act(async () => {
        await options?.onSuccess?.(page(receipt({ operation_id: 'other' })));
        options?.onFinish?.({} as ActiveVisit);
    });
    fireEvent.click(screen.getByRole('button', { name: action }));
    expect(confirmation().textContent).toContain('Perubahan yang belum disimpan akan hilang.');
    keepEditing();
    expect(window.confirm).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled(); expect(onLoad).not.toHaveBeenCalled();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75,1234');
    expect(router.put).toHaveBeenCalledOnce();
});

it('no-op setelah snapshot tanpa metadata dan target_id null tetap hasil sah', async () => {
    show({ baseline: null, target_tahunan: null });
    expect(screen.getByLabelText<HTMLInputElement>('Rujukan sumber').required).toBe(false);
    const options = submit();
    await act(async () => { options?.onSuccess?.(page(receipt())); });
    expect(onSaved).toHaveBeenCalledWith('Tidak ada perubahan.');
    expect(onClose).toHaveBeenCalledOnce();
});

it('pending menolak submit ganda, dismissal dan pergantian tahun', async () => {
    show(); const options = submit();
    await act(async () => { options?.onStart?.({} as PendingVisit); });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.click(screen.getByRole('dialog'));
    fireEvent.click(screen.getByRole('button', { name: 'Tutup dialog' }));
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } });
    const navigate = new CustomEvent('inertia:before', { cancelable: true, detail: { visit: { method: 'get', url: new URL('/dashboard', window.location.href), prefetch: false } } });
    document.dispatchEvent(navigate);
    expect(navigate.defaultPrevented).toBe(true);
    const prefetch = new CustomEvent('inertia:before', { cancelable: true, detail: { visit: { method: 'get', url: new URL('/dashboard', window.location.href), prefetch: true } } });
    document.dispatchEvent(prefetch);
    expect(prefetch.defaultPrevented).toBe(false);
    expect(router.put).toHaveBeenCalledOnce(); expect(onClose).not.toHaveBeenCalled(); expect(onLoad).not.toHaveBeenCalled();
    expect(screen.queryByRole('dialog', { name: 'Buang perubahan?' })).toBeNull();
});

it('konflik menjaga draft sampai pengguna menyetujui muat data terbaru', async () => {
    show(); fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    const options = submit();
    await act(async () => { options?.onError?.({ expected_state: 'Data sudah berubah.' }); options?.onFinish?.({} as ActiveVisit); });
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' })); expect(onLoad).not.toHaveBeenCalled();
    keepEditing();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75');
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
    discardChanges(); expect(onLoad).toHaveBeenCalledWith(2026);
});

it('403 setelah redirect menjelaskan hasil simpan belum pasti, menjaga draft dan menunggu muat ulang eksplisit', async () => {
    show();
    fireEvent.change(screen.getByLabelText('Target 2026'), { target: { value: '101' } });
    const options = submit();
    await act(async () => {
        options?.onHttpException?.({ status: 403, data: {}, headers: {} });
        options?.onFinish?.({} as ActiveVisit);
    });
    expect(screen.getByRole('alert').textContent).toContain('Akses ditolak.');
    expect(screen.getByRole('alert').textContent).toContain('Hasil simpan belum dapat dipastikan. Periksa data setelah akses pulih.');
    expect(screen.getByLabelText<HTMLInputElement>('Target 2026').value).toBe('101');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);
    fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
    expect(router.put).toHaveBeenCalledOnce();
    expect(onClose).not.toHaveBeenCalled(); expect(onLoad).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
    expect(onLoad).not.toHaveBeenCalled();
    keepEditing();
    fireEvent.click(screen.getByRole('button', { name: 'Muat data terbaru' }));
    discardChanges();
    expect(onLoad).toHaveBeenCalledWith(2026);
    expect(router.put).toHaveBeenCalledOnce();
});

it.each(['network', '419', '500'] as const)('%s mempertahankan input tanpa replay', async (failure) => {
    show(); const options = submit();
    await act(async () => {
        if (failure === 'network') options?.onNetworkError?.(new Error('offline'));
        else options?.onHttpException?.({ status: Number(failure), data: {}, headers: {} });
        options?.onFinish?.({} as ActiveVisit);
    });
    expect(screen.getByLabelText<HTMLInputElement>('Target 2026').value).toBe('76.25');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan' }).disabled).toBe(true);
    expect(router.put).toHaveBeenCalledOnce(); expect(onClose).not.toHaveBeenCalled();
    if (failure !== '419') expect(screen.getByText(/Hasil simpan belum dapat dipastikan/)).toBeTruthy();
});

it('dirty tahun membutuhkan persetujuan; hanya memuat pasangan baru setelah disetujui', () => {
    show(); fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } }); expect(onLoad).not.toHaveBeenCalled();
    keepEditing();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75');
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } });
    discardChanges(); expect(onLoad).toHaveBeenCalledWith(2027);
});

it.each(['Batal', 'Tutup dialog', 'Escape', 'backdrop'])('%s memakai konfirmasi aplikasi dan melanjutkan penutupan sekali', (action) => {
    show(); fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    const requestClose = () => {
        if (action === 'Escape') fireEvent.keyDown(document, { key: 'Escape' });
        else if (action === 'backdrop') fireEvent.click(screen.getByRole('dialog', { name: 'Baseline & target tahunan' }));
        else fireEvent.click(screen.getByRole('button', { name: action }));
    };
    requestClose(); keepEditing();
    expect(onClose).not.toHaveBeenCalled();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75');
    requestClose();
    const discard = within(confirmation()).getByRole('button', { name: 'Buang perubahan' });
    fireEvent.click(discard); fireEvent.click(discard);
    expect(onClose).toHaveBeenCalledOnce();
    expect(router.put).not.toHaveBeenCalled();
    expect(window.confirm).not.toHaveBeenCalled();
});

it('fokus, Escape dan keyboard dialog bertingkat tidak menutup editor atau mengirim form', async () => {
    show();
    // Tunggu fokus awal Modal sebelum menguji fokus konfirmasi yang berada di atasnya.
    await waitFor(() => expect(document.activeElement).toBe(screen.getByLabelText('Tahun')));
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    const cancel = screen.getByRole('button', { name: 'Batal' });
    cancel.focus(); fireEvent.click(cancel);
    const dialog = confirmation();
    expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: 'Tetap mengedit' }));
    const backgroundKeyboard = vi.fn();
    window.addEventListener('keydown', backgroundKeyboard);
    try {
        fireEvent.keyDown(dialog, { key: 'Tab' });
        fireEvent.keyDown(dialog, { key: 'Escape' });
        expect(backgroundKeyboard).not.toHaveBeenCalled();
        fireEvent(dialog, new Event('cancel', { cancelable: true, bubbles: false }));
        expect(screen.queryByRole('dialog', { name: 'Buang perubahan?' })).toBeNull();
        expect(document.activeElement).toBe(cancel);
        expect(onClose).not.toHaveBeenCalled();
        expect(router.put).not.toHaveBeenCalled();
    } finally { window.removeEventListener('keydown', backgroundKeyboard); }
});

it('konfirmasi tertunda mengunci aksi lain dan tidak menyimpan draft tanpa sengaja', () => {
    show(); fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } });
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2028' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan' }));
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    discardChanges();
    expect(onLoad).toHaveBeenCalledExactlyOnceWith(2027);
    expect(onClose).not.toHaveBeenCalled();
    expect(router.put).not.toHaveBeenCalled();
});

it('beforeunload tetap melindungi draft ketika halaman ditutup atau di-refresh', () => {
    show();
    const clean = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(clean); expect(clean.defaultPrevented).toBe(false);
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    const dirty = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(dirty); expect(dirty.defaultPrevented).toBe(true);
    expect(screen.queryByRole('dialog', { name: 'Buang perubahan?' })).toBeNull();
});

it.each(['get', 'post'] as const)('visit %s baru ditahan sebelum request, dibatalkan tanpa request, lalu dilanjutkan sekali dengan opsi dan callback', async (method) => {
    const initialPage: Page = { ...page(null), component: 'EditorHost', url: '/editor', flash: {} };
    const request = vi.fn(async (config: HttpRequestConfig): Promise<HttpResponse> => ({ status: 200, data: JSON.stringify({ ...initialPage, url: config.url }), headers: { 'x-inertia': 'true' } }));
    await act(async () => {
        await createInertiaApp({ page: initialPage, progress: false, resolve: () => EditorHost, http: { request }, setup({ App, props }) { render(<App {...props} />); } });
    });
    fireEvent.change(screen.getByLabelText('Baseline 2025'), { target: { value: '75' } });
    const callbacks = { onStart: vi.fn(), onSuccess: vi.fn(), onFinish: vi.fn() };
    const visit = () => router.visit(method === 'post' ? '/logout' : '/dashboard', { method, data: { source: 'editor' }, headers: { 'X-Confirmation-Test': 'preserved' }, preserveState: true, preserveScroll: true, ...callbacks });
    act(visit);
    expect(request).not.toHaveBeenCalled(); keepEditing();
    expect(request).not.toHaveBeenCalled();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75');
    act(visit);
    const discard = within(confirmation()).getByRole('button', { name: 'Buang perubahan' });
    fireEvent.click(discard); fireEvent.click(discard);
    await waitFor(() => expect(callbacks.onFinish).toHaveBeenCalledOnce());
    expect(request).toHaveBeenCalledOnce();
    expect(request.mock.calls[0][0]).toMatchObject({ method, headers: { 'X-Confirmation-Test': 'preserved' } });
    expect(request.mock.calls[0][0].url).toContain(method === 'post' ? '/logout' : '/dashboard');
    if (method === 'post') expect(request.mock.calls[0][0].data).toEqual({ source: 'editor' });
    else expect(request.mock.calls[0][0].url).toContain('source=editor');
    expect(callbacks.onStart).toHaveBeenCalledOnce(); expect(callbacks.onSuccess).toHaveBeenCalledOnce();
    expect(screen.getByLabelText<HTMLInputElement>('Baseline 2025').value).toBe('75');
    expect(window.confirm).not.toHaveBeenCalled();
});

it('izin baca saja tetap melihat nilai, tahun dan alasan tanpa tombol simpan', () => {
    show({ can: { update: false }, read_only_reason: 'Anda tidak memiliki izin mengubah target.' });
    expect(screen.getByLabelText<HTMLInputElement>('Target 2026').readOnly).toBe(true);
    expect(screen.queryByRole('button', { name: 'Simpan' })).toBeNull();
    expect(screen.getByText('Anda tidak memiliki izin mengubah target.')).toBeTruthy();
    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2027' } }); expect(onLoad).toHaveBeenCalledWith(2027);
});
