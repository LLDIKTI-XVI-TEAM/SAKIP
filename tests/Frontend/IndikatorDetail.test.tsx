import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { afterEach, expect, it, vi } from 'vitest';
import Show from '@/Pages/Perencanaan/Indikator/Show';
import type { IndikatorKinerjaItem, SasaranIndikatorCapabilities } from '@/types/sasaran-indikator';
import { definition, indicator } from './indikatorFixtures';

vi.mock('@inertiajs/react', async (original) => ({ ...(await original<typeof import('@inertiajs/react')>()), Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const can: SasaranIndikatorCapabilities = {
    sasaran_create: true, sasaran_update: true, sasaran_delete: true, penanggung_jawab_update: true,
    indikator_create: true, indikator_read: true, indikator_update: true, indikator_delete: true,
    regulasi_read: true, komponen_read: true, komponen_create: true, komponen_update: true, komponen_delete: true,
};

function renderShow(indikator: IndikatorKinerjaItem, capabilities: SasaranIndikatorCapabilities = can) {
    return render(
        <Show
            indikator={indikator}
            sasaran={{ id: 'sas-1', kode: 'SS-01', deskripsi: 'Tata kelola akuntabel' }}
            renstra={{ id: 'ren-1', kode: 'REN-2025', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029, is_aktif: true }}
            jumlahKomponenAktif={2}
            sasarans={[{ id: 'sas-1', renstra_id: 'ren-1', kode: 'SS-01', deskripsi: 'Tata kelola akuntabel', urutan: 1, indikator_kinerjas: [] }]}
            units={[{ id: 'unit-a', nama: 'Unit A' }]}
            regulasis={[]}
            can={capabilities}
        />,
    );
}

it('menampilkan metadata indikator dan aksi sesuai capability server', () => {
    const { unmount } = renderShow(indicator({
        penanggung_jawab: { nama: 'Siti Aminah', status: 'aktif' },
        regulasi: { id: 'reg-1', jenis: 'kepmen', nomor: '358/M/KEP/2025', tahun: 2025, tentang: 'IKU Perguruan Tinggi' },
    }));
    expect(screen.getByRole('heading', { name: 'Capaian kinerja' })).toBeTruthy();
    expect(screen.getByText('Makin tinggi makin baik')).toBeTruthy();
    expect(screen.getByText(/2 komponen aktif/)).toBeTruthy();
    expect(screen.getByText('Siti Aminah')).toBeTruthy();
    expect(screen.getByText('Kepmen 358/M/KEP/2025')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/perencanaan/sasaran-indikator?renstra_id=ren-1');
    expect(screen.getByRole('link', { name: 'Kelola Komponen' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Ubah Indikator' })).toBeTruthy();
    unmount();

    renderShow(indicator({ penanggung_jawab: null }), { ...can, penanggung_jawab_update: false, regulasi_read: false, indikator_update: false });
    expect(screen.queryByText('Penanggung jawab')).toBeNull();
    expect(screen.queryByText('Regulasi rujukan')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Ubah Indikator' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Penanggung Jawab' })).toBeNull();
});

it('ubah dari halaman detail memuat editor server lalu mengirim penanda kembali ke detail', async () => {
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, headers: {}, data: JSON.stringify(definition()) });
    const put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    const user = userEvent.setup();
    renderShow(indicator());

    await user.click(screen.getByRole('button', { name: 'Ubah Indikator' }));
    expect(await screen.findByRole('heading', { name: 'Ubah Indikator Kinerja' })).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

    expect(put.mock.calls[0]?.[0]).toBe('/perencanaan/indikator/ind-1');
    expect(put.mock.calls[0]?.[1]).toMatchObject({ kembali: 'detail' });
});
