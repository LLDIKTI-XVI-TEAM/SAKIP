import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { http } from '@inertiajs/core';
import { afterEach, expect, it, vi } from 'vitest';
import { DefinitionPreview } from '@/Pages/Indikator/Komponen/DefinitionPreview';
import { COMPONENTS, definition, indicator, REVISION } from './indikatorFixtures';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        usePage: () => ({ props: { pengaturan: {} } }),
    };
});

afterEach(() => { cleanup(); vi.restoreAllMocks(); });
function response(nilai: string | null, revision = REVISION) { return { status: 200, headers: {}, data: JSON.stringify({ indikator_id: 'ind-1', revision, nilai, status_perhitungan: nilai === null ? 'belum_diisi' : 'terhitung', sumber_nilai: 'komponen' }) }; }
it('simulasi mengirim decimal string, membedakan kosong/zero dan menampilkan hasil server', async () => {
    const request = vi.spyOn(http.getClient(), 'request').mockResolvedValue(response('0.00'));
    render(<DefinitionPreview editor={definition()} paused={false} />);
    fireEvent.change(screen.getByLabelText('N — Capaian aktual'), { target: { value: '0' } });
    fireEvent.change(screen.getByLabelText('T — Total target'), { target: { value: '1.123456789012' } });
    await screen.findByText('0,00 % · Terhitung');
    expect(request.mock.calls.at(-1)?.[0]).toMatchObject({ method: 'post', url: '/perencanaan/indikator/ind-1/komponen/preview' });
    const body = JSON.parse(String(request.mock.calls.at(-1)?.[0].data));
    expect(body).toEqual({ expected_updated_at: REVISION, values: { [COMPONENTS[0].id]: '0', [COMPONENTS[1].id]: '1.123456789012' } });
});
it('hasil server ditampilkan sesuai desimal tampilan yang berbeda dari presisi', async () => {
    vi.spyOn(http.getClient(), 'request').mockResolvedValue(response('1.2345'));
    render(<DefinitionPreview editor={definition({ indikator: indicator({ presisi: 4, desimal_tampilan: 2 }) })} paused={false} />);
    await screen.findByText('1,23 % · Terhitung');
    expect(screen.queryByText('1.2345 % · Terhitung')).toBeNull();
});
it('respons lama tidak menggantikan hasil input terbaru', async () => {
    let resolveOld: ((value: ReturnType<typeof response>) => void) | undefined;
    const request = vi.spyOn(http.getClient(), 'request').mockImplementationOnce(() => new Promise((resolve) => { resolveOld = resolve; })).mockResolvedValue(response('20.00'));
    render(<DefinitionPreview editor={definition()} paused={false} />);
    await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
    fireEvent.change(screen.getByLabelText('N — Capaian aktual'), { target: { value: '20' } });
    await screen.findByText('20,00 % · Terhitung');
    await act(async () => resolveOld?.(response('10.00')));
    expect(screen.queryByText('10,00 % · Terhitung')).toBeNull();
});
it('draft terbuka menghentikan simulasi dan menyembunyikan hasil sebelumnya', async () => {
    const request = vi.spyOn(http.getClient(), 'request').mockResolvedValue(response(null));
    const editor = definition(); const { rerender } = render(<DefinitionPreview editor={editor} paused={false} />);
    await screen.findByText('Belum ada nilai — Belum diisi');
    rerender(<DefinitionPreview editor={editor} paused />);
    expect(screen.queryByText('Belum ada nilai — Belum diisi')).toBeNull();
    expect((screen.getByLabelText('N — Capaian aktual') as HTMLInputElement).disabled).toBe(true);
    expect(request).toHaveBeenCalledTimes(1);
});
it('respons revisi lain tidak ditampilkan sebagai hasil', async () => {
    vi.spyOn(http.getClient(), 'request').mockResolvedValue(response('99', 'other'));
    render(<DefinitionPreview editor={definition()} paused={false} />);
    await screen.findByText(/Simulasi belum tersedia/); expect(screen.queryByText(/99 %/)).toBeNull();
});
it('409 menghentikan request baru sampai definisi dimuat ulang', async () => {
    const request = vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 409, headers: {}, data: '{}' });
    render(<DefinitionPreview editor={definition()} paused={false} />);
    await screen.findByText('Definisi telah berubah. Muat ulang data sebelum melanjutkan simulasi.');
    expect((screen.getByLabelText('N — Capaian aktual') as HTMLInputElement).disabled).toBe(true);
    fireEvent.change(screen.getByLabelText('N — Capaian aktual'), { target: { value: '10' } });
    await act(async () => { await new Promise((resolve) => window.setTimeout(resolve, 350)); });
    expect(request).toHaveBeenCalledTimes(1);
});
