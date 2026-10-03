import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { FileSearch } from 'lucide-react';
import { EmptyState } from '@/Components/EmptyState';

afterEach(cleanup);

describe('EmptyState Component', () => {
    it('merender judul dan deskripsi dengan benar', () => {
        render(
            <EmptyState
                icon={FileSearch}
                title="Tidak ada data ditemukan"
                description="Coba ubah kata kunci filter pencarian Anda."
            />
        );

        expect(screen.getByText('Tidak ada data ditemukan')).toBeDefined();
        expect(screen.getByText('Coba ubah kata kunci filter pencarian Anda.')).toBeDefined();
    });

    it('merender action slot jika diberikan', () => {
        render(
            <EmptyState
                title="Belum ada data"
                action={<button type="button">Tambah Data</button>}
            />
        );

        expect(screen.getByRole('button', { name: 'Tambah Data' })).toBeDefined();
    });
});
