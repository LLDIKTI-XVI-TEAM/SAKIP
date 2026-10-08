import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { Badge, type BadgeVariant } from '@/Components/Badge';
import { WorkReadinessList } from '@/Components/Access/WorkReadinessList';

afterEach(cleanup);

describe('Badge canonical', () => {
    it('memakai teks success gelap pada ukuran kecil tanpa mengubah background atau border', () => {
        render(<Badge variant="success" size="sm" dot>Tersedia</Badge>);
        const badge = screen.getByText('Tersedia');
        expect(badge.classList.contains('text-success-dark')).toBe(true);
        expect(badge.classList.contains('text-success')).toBe(false);
        expect(badge.classList.contains('bg-success/10')).toBe(true);
        expect(badge.classList.contains('border-success/20')).toBe(true);
        expect(badge.classList.contains('text-[11px]')).toBe(true);
        expect(badge.querySelector('[aria-hidden="true"]')?.classList.contains('bg-success')).toBe(true);
    });

    it.each<[BadgeVariant, string, string]>([
        ['primary', 'text-primary', 'bg-primary/10'],
        ['secondary', 'text-ink', 'bg-secondary/15'],
        ['warning', 'text-warning-dark', 'bg-warning/10'],
        ['danger', 'text-danger', 'bg-danger/10'],
        ['info', 'text-info-dark', 'bg-info/10'],
        ['muted', 'text-muted', 'bg-soft'],
    ])('mempertahankan variant %s', (variant, text, background) => {
        render(<Badge variant={variant}>Status</Badge>);
        const badge = screen.getByText('Status');
        expect(badge.classList.contains(text)).toBe(true);
        expect(badge.classList.contains(background)).toBe(true);
        expect(badge.classList.contains('text-xs')).toBe(true);
        expect(badge.querySelector('[aria-hidden="true"]')).toBeNull();
    });

    it('mempertahankan label status dan default dot serta override children, dot, dan className', () => {
        const { rerender } = render(<Badge status="disahkan" />);
        expect(screen.getByText('Disahkan').classList.contains('text-success-dark')).toBe(true);
        expect(screen.getByText('Disahkan').querySelector('[aria-hidden="true"]')).not.toBeNull();
        rerender(<Badge status="disahkan" variant="success" dot={false} className="text-ink px-4">Label khusus</Badge>);
        const badge = screen.getByText('Label khusus');
        expect(badge.classList.contains('text-ink')).toBe(true);
        expect(badge.classList.contains('text-success-dark')).toBe(false);
        expect(badge.classList.contains('px-4')).toBe(true);
        expect(badge.querySelector('[aria-hidden="true"]')).toBeNull();
    });

    it('menampilkan izin tersedia dan kurang pada consumer readiness dengan badge canonical', () => {
        render(<WorkReadinessList readiness={{ complete: false, available: ['pengukuran:create'], missing: ['pengukuran:update'], permissions: [
            { permission: 'pengukuran:create', label: 'Buat pengukuran', allowed: true, reason: 'role' },
            { permission: 'pengukuran:update', label: 'Ubah pengukuran', allowed: false, reason: 'explicit_deny' },
        ] }} />);
        const list = screen.getByRole('list', { name: 'Kelengkapan izin kerja' });
        expect(within(list).getByText('Tersedia').classList.contains('text-success-dark')).toBe(true);
        expect(within(list).getByText('Belum tersedia').classList.contains('text-warning-dark')).toBe(true);
        expect(within(list).getByText('Ditolak secara eksplisit')).toBeTruthy();
    });
});
