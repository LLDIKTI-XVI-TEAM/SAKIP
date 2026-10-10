import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from '@inertiajs/core';
import { definition } from './indikatorFixtures';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/Perencanaan/SasaranIndikator/Index';
import Show from '@/Pages/Perencanaan/Indikator/Show';
import type {
    IndikatorKinerjaItem,
    SasaranIndikatorCapabilities,
    SasaranStrategisItem,
} from '@/types/sasaran-indikator';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const indikator: IndikatorKinerjaItem = {
    id: 'ind-1',
    sasaran_strategis_id: 'sas-1',
    regulasi_id: null,
    kode: 'IKU-01',
    nama: 'Persentase capaian kinerja',
    definisi_operasional: null,
    satuan: '%',
    unit_id: 'unit-a',
    unit_nama: 'Unit A',
    arah: 'naik_baik',
    tipe_perhitungan: 'manual',
    presisi: 2,
    desimal_tampilan: 2,
    wajib_catatan: false,
    status: 'aktif',
    tahun_mulai_berlaku: 2025,
};

const sasaran: SasaranStrategisItem = {
    id: 'sas-1',
    renstra_id: 'ren-1',
    kode: 'SS-01',
    deskripsi: 'Terwujudnya tata kelola yang akuntabel',
    urutan: 1,
    indikator_kinerjas: [indikator],
};

const fullCan: SasaranIndikatorCapabilities = {
    sasaran_create: true,
    sasaran_update: true,
    sasaran_delete: true,
    indikator_create: true,
    indikator_read: true,
    indikator_update: true,
    indikator_delete: true,
};

function renderIndex(can: SasaranIndikatorCapabilities = fullCan) {
    render(
        <Index
            renstras={[
                {
                    id: 'ren-1',
                    kode: 'REN-2025',
                    nama: 'Renstra 2025-2029',
                    tahun_mulai: 2025,
                    tahun_selesai: 2029,
                    is_aktif: true,
                },
            ]}
            selectedRenstraId="ren-1"
            sasarans={[sasaran]}
            units={[
                { id: 'unit-a', nama: 'Unit A' },
                { id: 'unit-b', nama: 'Unit B' },
            ]}
            regulasis={[]}
            can={can}
        />
    );
}

describe('Pindah Unit Indikator (R2-03)', () => {
    it('menampilkan aksi Pindah Unit pada menu baris bila can.indikator_update', async () => {
        const user = userEvent.setup();
        renderIndex();
        await user.click(screen.getByRole('button', { name: 'Aksi IKU-01' }));
        expect(
            screen.getByRole('menuitem', { name: 'Pindah unit indikator IKU-01' })
        ).toBeTruthy();
    });

    it('menyembunyikan aksi Pindah Unit bila can.indikator_update false', async () => {
        const user = userEvent.setup();
        renderIndex({ ...fullCan, indikator_update: false });
        const aksi = screen.queryByRole('button', { name: 'Aksi IKU-01' });
        if (aksi) await user.click(aksi);
        expect(
            screen.queryByRole('menuitem', { name: 'Pindah unit indikator IKU-01' })
        ).toBeNull();
    });

    it('membuka modal pindah dengan daftar tujuan tanpa unit asal', async () => {
        const user = userEvent.setup();
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Aksi IKU-01' }));
        await user.click(
            screen.getByRole('menuitem', { name: 'Pindah unit indikator IKU-01' })
        );

        expect(
            screen.getByRole('heading', { name: 'Pindah Unit — IKU-01' })
        ).toBeTruthy();

        const tujuan = screen.getByLabelText(/unit tujuan/i) as HTMLSelectElement;
        const optionLabels = Array.from(tujuan.options).map((o) => o.text);
        expect(optionLabels).not.toContain('Unit A');
        expect(optionLabels).toContain('Unit B');
        expect(screen.getByLabelText(/alasan pemindahan/i)).toBeTruthy();
    });

    it('menolak submit tanpa tujuan dan alasan pendek tanpa memanggil PATCH', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Aksi IKU-01' }));
        await user.click(
            screen.getByRole('menuitem', { name: 'Pindah unit indikator IKU-01' })
        );
        await user.click(screen.getByRole('button', { name: 'Pindahkan Unit' }));

        expect(
            screen.getByText('Unit penanggung jawab tujuan wajib dipilih.')
        ).toBeTruthy();
        expect(
            screen.getByText('Alasan pemindahan unit penanggung jawab minimal 10 karakter.')
        ).toBeTruthy();
        expect(patchSpy).not.toHaveBeenCalled();
    });

    it('mengirim PATCH pindah-unit saat tujuan dan alasan valid', async () => {
        const user = userEvent.setup();
        const patchSpy = vi
            .spyOn(router, 'patch')
            .mockImplementation((() => undefined) as unknown as typeof router.patch);
        renderIndex();

        await user.click(screen.getByRole('button', { name: 'Aksi IKU-01' }));
        await user.click(
            screen.getByRole('menuitem', { name: 'Pindah unit indikator IKU-01' })
        );
        await user.selectOptions(screen.getByLabelText(/unit tujuan/i), 'unit-b');
        await user.type(
            screen.getByLabelText(/alasan pemindahan/i),
            'Reorganisasi struktur unit kerja baru'
        );
        await user.click(screen.getByRole('button', { name: 'Pindahkan Unit' }));

        expect(patchSpy).toHaveBeenCalledTimes(1);
        expect(patchSpy.mock.calls[0]?.[0]).toBe(
            '/perencanaan/indikator/ind-1/pindah-unit'
        );
        const sentData = patchSpy.mock.calls[0]?.[1] as
            | Record<string, unknown>
            | undefined;
        expect(sentData?.['unit_id']).toBe('unit-b');
        expect(sentData?.['alasan']).toBe('Reorganisasi struktur unit kerja baru');
    });

    it('modal edit menampilkan unit read-only tanpa field alasan pindah', async () => {
        vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, headers: {}, data: JSON.stringify(definition({ indikator })) });
        const user = userEvent.setup();
        // Ubah indikator kini dibuka dari halaman detail.
        render(
            <Show
                indikator={indikator}
                sasaran={{ id: sasaran.id, kode: sasaran.kode, deskripsi: sasaran.deskripsi }}
                renstra={{ id: 'ren-1', kode: 'REN-2025', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029, is_aktif: true }}
                jumlahKomponenAktif={0}
                sasarans={[{ ...sasaran, indikator_kinerjas: [] }]}
                units={[{ id: 'unit-a', nama: 'Unit A' }, { id: 'unit-b', nama: 'Unit B' }]}
                regulasis={[]}
                can={fullCan}
            />
        );

        await user.click(screen.getByRole('button', { name: 'Ubah Indikator' }));

        expect(
            await screen.findByRole('heading', { name: 'Ubah Indikator Kinerja' })
        ).toBeTruthy();
        // Unit tampil sebagai teks read-only, bukan dropdown
        expect(
            screen.queryByRole('combobox', { name: 'Unit Penanggung Jawab' })
        ).toBeNull();
        expect(screen.getByDisplayValue('Unit A')).toBeTruthy();
        expect(screen.getByText(/Untuk mengganti unit, gunakan aksi “Pindah Unit” pada tabel/i)).toBeTruthy();
        // Tidak ada field alasan pindah unit di modal edit
        expect(screen.queryByLabelText(/alasan pindah/i)).toBeNull();
    });
});
