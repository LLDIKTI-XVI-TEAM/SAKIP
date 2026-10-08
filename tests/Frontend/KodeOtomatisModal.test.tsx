import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, expect, it, vi } from 'vitest';
import { SasaranModal } from '@/Pages/Perencanaan/SasaranIndikator/SasaranModal';
import { IndikatorModal } from '@/Pages/Perencanaan/SasaranIndikator/IndikatorModal';
import { definition, indicator } from './indikatorFixtures';

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const sasaran = {
    id: 'sas-1',
    renstra_id: 'ren-1',
    kode: 'SS-07',
    deskripsi: 'Sasaran tersimpan',
    urutan: 7,
    updated_at: '2026-10-02T10:00:00.123456Z',
    indikator_kinerjas: [],
};

const can = { sasaran_create: true, sasaran_update: true, sasaran_delete: false, indikator_create: true, indikator_read: true, indikator_update: true, indikator_delete: false, komponen_read: true, komponen_create: true, komponen_update: true, komponen_delete: true };

const indikatorCommon = { isOpen: true, sasarans: [sasaran], units: [{ id: 'unit-a', nama: 'Unit A' }], regulasis: [], can };

it('tambah sasaran tidak mengirim kode/urutan dan hanya submit satu kali', async () => {
    const user = userEvent.setup();
    const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<SasaranModal isOpen onClose={vi.fn()} renstraId="ren-1" sasaran={null} />);

    expect(screen.queryByLabelText(/Kode Sasaran/)).toBeNull();
    expect(screen.queryByLabelText(/Urutan Tampil/)).toBeNull();
    expect(screen.getByText(/Kode sasaran dibuat otomatis oleh sistem/)).toBeTruthy();

    await user.type(screen.getByLabelText(/Deskripsi Sasaran Strategis/), 'Sasaran baru');
    await user.click(screen.getByRole('button', { name: 'Tambah Sasaran' }));

    expect(post).toHaveBeenCalledTimes(1);
    const payload = post.mock.calls[0]?.[1] as Record<string, unknown>;
    expect(payload).toMatchObject({ renstra_id: 'ren-1', deskripsi: 'Sasaran baru' });
    expect(payload).not.toHaveProperty('kode');
    expect(payload).not.toHaveProperty('urutan');
});

it('ubah sasaran menampilkan kode read-only tanpa mengirimnya', async () => {
    const user = userEvent.setup();
    const put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    render(<SasaranModal isOpen onClose={vi.fn()} renstraId="ren-1" sasaran={sasaran} />);

    const kode = screen.getByLabelText('Kode Sasaran') as HTMLInputElement;
    expect(kode.value).toBe('SS-07');
    expect(kode.disabled).toBe(true);

    await user.type(screen.getByLabelText(/Deskripsi Sasaran Strategis/), ' diperbarui');
    await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0]?.[1]).not.toHaveProperty('kode');
    expect(put.mock.calls[0]?.[1]).not.toHaveProperty('urutan');
});

it('tambah indikator menampilkan info kode otomatis tanpa input kode', async () => {
    const user = userEvent.setup();
    const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<IndikatorModal {...indikatorCommon} indikator={null} editor={null} onClose={vi.fn()} />);

    expect(screen.queryByLabelText(/Kode IKU/)).toBeNull();
    expect(screen.queryByLabelText('Kode Indikator')).toBeNull();
    expect(screen.getByText(/Kode dibuat otomatis oleh sistem secara berurutan/)).toBeTruthy();

    await user.type(screen.getByLabelText(/Nama Indikator Kinerja/), 'Indikator tanpa kode');
    await user.click(screen.getByRole('button', { name: 'Tambah Indikator' }));

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0]?.[1]).not.toHaveProperty('kode');
});

it('ubah indikator menampilkan kode read-only', () => {
    render(<IndikatorModal {...indikatorCommon} indikator={indicator()} editor={definition()} onClose={vi.fn()} />);

    const kode = screen.getByLabelText('Kode Indikator') as HTMLInputElement;
    expect(kode.value).toBe('IKU-01');
    expect(kode.disabled).toBe(true);
});
