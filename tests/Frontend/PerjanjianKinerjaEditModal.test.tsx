import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PerjanjianKinerjaEditModal } from '@/Pages/PerjanjianKinerja/Partials/PerjanjianKinerjaEditModal';
import type { RenstraPkSummary } from '@/types/perjanjian-kinerja';

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function () {
        this.open = false;
    };
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const samplePk: RenstraPkSummary = {
    id: 'pk-uuid-1',
    renstra_id: 'renstra-uuid-1',
    tahun: 2026,
    nomor_pk: 'PK/LLDIKTI16/2026/001',
    tanggal_pk: '2026-01-15T00:00:00.000000Z',
    creator: {
        id: 'user-uuid-1',
        nama: 'Superadmin',
    },
    created_at: '2026-01-15T00:00:00.000000Z',
    updated_at: '2026-01-15T00:00:00.000000Z',
    renstra: {
        id: 'renstra-uuid-1',
        kode: 'RENSTRA-2025',
        nama: 'Renstra 2025-2029',
        tahun_mulai: 2025,
        tahun_selesai: 2029,
    },
};

const samplePk2: RenstraPkSummary = {
    id: 'pk-uuid-2',
    renstra_id: 'renstra-uuid-1',
    tahun: 2027,
    nomor_pk: 'PK/LLDIKTI16/2027/002',
    tanggal_pk: '2027-01-20T00:00:00.000000Z',
    creator: {
        id: 'user-uuid-1',
        nama: 'Superadmin',
    },
    created_at: '2027-01-20T00:00:00.000000Z',
    updated_at: '2027-01-20T00:00:00.000000Z',
    renstra: {
        id: 'renstra-uuid-1',
        kode: 'RENSTRA-2025',
        nama: 'Renstra 2025-2029',
        tahun_mulai: 2025,
        tahun_selesai: 2029,
    },
};

describe('PerjanjianKinerjaEditModal Lifecycle & Concurrency', () => {
    it('mengisi formulir dengan data awal saat modal dibuka', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        expect(nomorInput.value).toBe('PK/LLDIKTI16/2026/001');

        const tanggalInput = screen.getByLabelText<HTMLInputElement>(/Tanggal Penandatanganan/);
        expect(tanggalInput.value).toBe('2026-01-15');
    });

    it('menampilkan alasan dan rujukan opsional inline sebelum PK dipakai jadwal aktif, lalu meminta konfirmasi sesudahnya', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                alasanWajib={false}
            />
        );

        // Q43 §43.3 butir 3: sebelum jadwal aktif alasan opsional dan submit tidak terkunci.
        expect(screen.getByLabelText(/Alasan \(opsional\)/)).toBeTruthy();
        expect(screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/).required).toBe(false);
        expect(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }).hasAttribute('disabled')).toBe(false);

        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                alasanWajib={true}
            />
        );

        // Alasan diisi setelah Simpan ditekan; tombol pembuka tidak terkunci oleh alasan kosong.
        expect(screen.queryByLabelText(/^Alasan/)).toBeNull();
        const lanjutkan = screen.getByRole<HTMLButtonElement>('button', { name: /Perbarui Perjanjian Kinerja/ });
        expect(lanjutkan.disabled).toBe(false);
        fireEvent.click(lanjutkan);
        expect(router.post).not.toHaveBeenCalled();
        expect(screen.queryByRole('dialog', { name: 'Edit Perjanjian Kinerja' })).toBeNull();
        expect(screen.getByRole('dialog', { name: 'Konfirmasi perubahan Perjanjian Kinerja' })).toBeTruthy();
        expect(screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/).getAttribute('aria-required')).toBe('true');
    });

    it('menyimpan sebelum jadwal mengunci tanpa modal konfirmasi melalui POST spoof dengan rujukan opsional', () => {
        render(<PerjanjianKinerjaEditModal isOpen onClose={() => {}} pk={samplePk} />);
        fireEvent.change(screen.getByLabelText(/Rujukan dokumen resmi/), { target: { value: 'Nota dinas 7/2026' } });
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, data, options] = vi.mocked(router.post).mock.calls[0];
        expect(url).toBe(`/perjanjian-kinerja/${samplePk.id}`);
        expect(data).toMatchObject({ _method: 'put', rujukan: 'Nota dinas 7/2026', alasan: '' });
        expect(options?.forceFormData).toBe(true);
        expect(screen.queryByRole('dialog', { name: 'Konfirmasi perubahan Perjanjian Kinerja' })).toBeNull();
    });

    it.each(['nomor', 'tanggal'])('meminta rujukan bila %s PK berubah dan mempertahankan isian saat konfirmasi dibatalkan', (field) => {
        render(<PerjanjianKinerjaEditModal isOpen onClose={() => {}} pk={samplePk} alasanWajib />);
        const input = screen.getByLabelText(field === 'nomor' ? /Nomor Dokumen/ : /Tanggal Penandatanganan/);
        fireEvent.change(input, { target: { value: field === 'nomor' ? 'PK/KOREKSI' : '2026-01-20' } });
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));

        const source = screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/);
        expect(source.required).toBe(true);
        expect(source.maxLength).toBe(255);
        fireEvent.change(screen.getByLabelText(/Alasan perubahan/), { target: { value: 'Koreksi salah ketik.' } });
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' }).disabled).toBe(true);
        fireEvent.change(source, { target: { value: 'Surat PK 8/2026' } });
        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));

        expect(screen.getByLabelText<HTMLInputElement>(field === 'nomor' ? /Nomor Dokumen/ : /Tanggal Penandatanganan/).value)
            .toBe(field === 'nomor' ? 'PK/KOREKSI' : '2026-01-20');
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));
        expect(screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/).value).toBe('Koreksi salah ketik.');
        expect(screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/).value).toBe('Surat PK 8/2026');
        expect(router.post).not.toHaveBeenCalled();
    });

    it.each([samplePk, { ...samplePk, nomor_pk: null }])('metadata setara setelah normalisasi tetap membuat rujukan opsional', (pk) => {
        render(<PerjanjianKinerjaEditModal isOpen onClose={() => {}} pk={pk} alasanWajib />);
        fireEvent.change(screen.getByLabelText(/Nomor Dokumen/), { target: { value: ` ${pk.nomor_pk ?? ''} ` } });
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));
        expect(screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/).required).toBe(false);
        fireEvent.change(screen.getByLabelText(/Alasan perubahan/), { target: { value: 'Pemeriksaan dokumen.' } });
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' }).disabled).toBe(false);
    });

    it('lampiran-only tetap meminta alasan dan mempertahankan berkas saat kembali ke editor', () => {
        render(<PerjanjianKinerjaEditModal isOpen onClose={() => {}} pk={samplePk} alasanWajib />);
        fireEvent.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
        const file = new File(['PK tambahan'], 'pk-tambahan.pdf', { type: 'application/pdf' });
        fireEvent.change(screen.getByLabelText(/File lampiran 1/), { target: { files: [file] } });
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));
        expect(screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/).required).toBe(false);
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' }).disabled).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
        expect(screen.getByText(/pk-tambahan.pdf/)).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));
        fireEvent.change(screen.getByLabelText(/Alasan perubahan/), { target: { value: 'Tambahan dokumen anggaran.' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
        expect(vi.mocked(router.post).mock.calls[0][1]).toMatchObject({ lampiran: [{ file }], rujukan: '' });
    });

    it('mempertahankan modal dan data setelah galat server, menahan kirim ganda, dan menutup hanya setelah sukses', async () => {
        const onClose = vi.fn();
        render(<PerjanjianKinerjaEditModal isOpen onClose={onClose} pk={samplePk} alasanWajib />);
        fireEvent.change(screen.getByLabelText(/Nomor Dokumen/), { target: { value: 'PK/KOREKSI' } });
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));
        fireEvent.change(screen.getByLabelText(/Alasan perubahan/), { target: { value: 'Koreksi nomor PK.' } });
        fireEvent.change(screen.getByLabelText(/Rujukan dokumen resmi/), { target: { value: 'Surat 9/2026' } });
        const simpan = screen.getByRole('button', { name: 'Simpan perubahan' });
        fireEvent.click(simpan);
        fireEvent.click(simpan);
        expect(router.post).toHaveBeenCalledTimes(1);
        const options = vi.mocked(router.post).mock.calls[0][2]!;
        await act(async () => { options.onError?.({ rujukan: 'Rujukan wajib diisi.', konflik: 'Data PK sudah berubah. Muat ulang.' }); options.onFinish?.({} as never); });

        expect(onClose).not.toHaveBeenCalled();
        const source = screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/);
        expect(source.value).toBe('Surat 9/2026');
        expect(document.getElementById(source.getAttribute('aria-describedby') ?? '')?.textContent).toBe('Rujukan wajib diisi.');
        expect(screen.getByText('Data PK sudah berubah. Muat ulang.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
        expect(screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen/).value).toBe('PK/KOREKSI');
        expect(screen.getByText('Data PK sudah berubah. Muat ulang.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: /Perbarui Perjanjian Kinerja/ }));
        fireEvent.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
        const nextOptions = vi.mocked(router.post).mock.calls[1][2]!;
        await act(async () => { await nextOptions.onSuccess?.({} as never); });
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('submit formulir membuka konfirmasi dan menahan perubahan maupun penutupan selama request berjalan', async () => {
        const onClose = vi.fn();
        const { container } = render(<PerjanjianKinerjaEditModal isOpen onClose={onClose} pk={samplePk} alasanWajib />);
        fireEvent.submit(container.querySelector('form')!);
        fireEvent.change(screen.getByLabelText(/Alasan perubahan/), { target: { value: 'Menambahkan catatan dokumen.' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
        const options = vi.mocked(router.post).mock.calls[0][2]!;
        await act(async () => { options.onStart?.({} as never); });
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Batal' }).disabled).toBe(true);
        expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' }).disabled).toBe(true);
        expect(screen.getByLabelText<HTMLInputElement>(/Rujukan dokumen resmi/).disabled).toBe(true);
        const dialog = screen.getByRole('dialog', { name: 'Konfirmasi perubahan Perjanjian Kinerja' });
        fireEvent(dialog, new Event('cancel', { bubbles: true, cancelable: true }));
        expect(screen.getByLabelText<HTMLTextAreaElement>(/Alasan perubahan/).value).toBe('Menambahkan catatan dokumen.');
        expect(onClose).not.toHaveBeenCalled();
    });

    it('mempertahankan input koreksi pengguna saat props pk di-refresh dengan object reference baru selagi modal terbuka', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        act(() => {
            fireEvent.change(nomorInput, { target: { value: 'PK/KOREKSI/USER/999' } });
        });
        expect(nomorInput.value).toBe('PK/KOREKSI/USER/999');

        const alasanInput = screen.getByLabelText<HTMLInputElement>(/^Alasan/);
        act(() => {
            fireEvent.change(alasanInput, { target: { value: 'Koreksi nomor surat resmi' } });
        });
        expect(alasanInput.value).toBe('Koreksi nomor surat resmi');

        // Simulasikan props refresh dari Inertia (object reference baru untuk pk yang sama)
        const refreshedPk: RenstraPkSummary = {
            ...samplePk,
            updated_at: '2026-01-16T10:00:00.000000Z',
        };

        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={refreshedPk}
            />
        );

        // Input koreksi pengguna tidak boleh ter-reset ke data server
        expect(nomorInput.value).toBe('PK/KOREKSI/USER/999');
        expect(alasanInput.value).toBe('Koreksi nomor surat resmi');
    });

    it('mereset formulir ke data baru saat beralih ke PK yang berbeda', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        expect(nomorInput.value).toBe('PK/LLDIKTI16/2026/001');

        // Beralih ke PK lain
        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk2}
            />
        );

        expect(nomorInput.value).toBe('PK/LLDIKTI16/2027/002');
        const tanggalInput = screen.getByLabelText<HTMLInputElement>(/Tanggal Penandatanganan/);
        expect(tanggalInput.value).toBe('2027-01-20');
    });

    it('mereset formulir ke nilai baseline saat modal ditutup lalu dibuka kembali', () => {
        const { rerender } = render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        act(() => {
            fireEvent.change(nomorInput, { target: { value: 'PK/SEMENTARA' } });
        });
        expect(nomorInput.value).toBe('PK/SEMENTARA');

        // Tutup modal
        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={false}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        // Buka kembali modal
        rerender(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
            />
        );

        const reopenedNomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja/);
        expect(reopenedNomorInput.value).toBe('PK/LLDIKTI16/2026/001');
    });

    it('menampilkan banner informasi saat jadwal tahunan aktif atau terkunci', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                isJadwalAktif={true}
            />
        );

        expect(screen.getByText(/^Jadwal tahunan aktif: lampiran yang ada tidak dapat dihapus/)).toBeTruthy();
    });

    it('menyembunyikan banner informasi saat jadwal tahunan belum aktif / tidak terkunci', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                isJadwalAktif={false}
            />
        );

        expect(screen.queryByText(/lampiran yang ada tidak dapat dihapus/)).toBeNull();
    });

    it('menampilkan banner jadwal ditutup terkunci saat jadwalStatus ditutup', () => {
        render(
            <PerjanjianKinerjaEditModal
                isOpen={true}
                onClose={() => {}}
                pk={samplePk}
                isJadwalTerkunci={true}
                jadwalStatus="ditutup"
            />
        );

        expect(screen.getByText(/^Jadwal tahunan ditutup: lampiran yang ada tidak dapat dihapus/)).toBeTruthy();
    });

    it('lampiran baru (termasuk mode file) punya isian keterangan opsional yang terikat ke data form', () => {
        render(<PerjanjianKinerjaEditModal isOpen={true} onClose={() => {}} pk={samplePk} />);

        fireEvent.click(screen.getByRole('button', { name: 'Tambah Lampiran' }));
        act(() => {
            fireEvent.change(screen.getByLabelText<HTMLInputElement>(/^Keterangan \(opsional\)/), { target: { value: 'PK perubahan anggaran' } });
        });

        expect(screen.getByLabelText<HTMLInputElement>(/^Keterangan \(opsional\)/).value).toBe('PK perubahan anggaran');
        expect(document.querySelector('input[type=file]')).not.toBeNull();
    });

    it('nomor PK opsional: PK tanpa nomor membuka isian kosong yang tidak wajib', () => {
        render(<PerjanjianKinerjaEditModal isOpen={true} onClose={() => {}} pk={{ ...samplePk, nomor_pk: null }} />);

        const nomorInput = screen.getByLabelText<HTMLInputElement>(/Nomor Dokumen Perjanjian Kinerja \(opsional\)/);
        expect(nomorInput.value).toBe('');
        expect(nomorInput.required).toBe(false);
    });
});

