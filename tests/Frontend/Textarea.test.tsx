import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
import { Textarea } from '@/Components/Textarea';

afterEach(cleanup);

it('menghubungkan label dan error dengan ID unik tanpa ID atau name dari caller', () => {
    render(<><Textarea label="Alasan" error="Alasan wajib diisi." /><Textarea label="Catatan" helperText="Catatan pendukung." /></>);
    const alasan = screen.getByRole('textbox', { name: 'Alasan' });
    const catatan = screen.getByRole('textbox', { name: 'Catatan' });
    expect(alasan.id).not.toBe(catatan.id);
    expect(alasan.getAttribute('aria-invalid')).toBe('true');
    expect(alasan.getAttribute('aria-describedby')).toBe(screen.getByRole('alert').id);
    expect(document.getElementById(catatan.getAttribute('aria-describedby') ?? '')?.textContent).toBe('Catatan pendukung.');
});

it('mengganti hubungan helper dengan error lalu memulihkannya tanpa kehilangan nilai', () => {
    const props = { id: 'alasan-edit', name: 'alasan', label: 'Alasan', helperText: 'Minimal lima karakter.', defaultValue: 'Draf alasan' };
    const { rerender } = render(<Textarea {...props} />);
    const input = screen.getByRole('textbox', { name: 'Alasan' }) as HTMLTextAreaElement;
    expect(input.id).toBe('alasan-edit');
    expect(input.getAttribute('aria-describedby')).toBe(screen.getByText(props.helperText).id);
    rerender(<Textarea {...props} error="Alasan ditolak server." />);
    expect(screen.queryByText(props.helperText)).toBeNull();
    expect(input.getAttribute('aria-describedby')).toBe(screen.getByRole('alert').id);
    expect(input.getAttribute('aria-invalid')).toBe('true');
    rerender(<Textarea {...props} />);
    expect(screen.queryByRole('alert')).toBeNull();
    expect(input.getAttribute('aria-invalid')).toBeNull();
    expect(input.getAttribute('aria-describedby')).toBe(screen.getByText(props.helperText).id);
    expect(input.value).toBe('Draf alasan');
});

it('mempertahankan deskripsi caller dan status invalid caller saat tidak ada error komponen', () => {
    const props = { name: 'alasan', label: 'Alasan', helperText: 'Minimal lima karakter.', 'aria-describedby': 'petunjuk-audit', 'aria-invalid': 'grammar' as const };
    const { rerender } = render(<><p id="petunjuk-audit">Dicatat pada audit.</p><Textarea {...props} /></>);
    const input = screen.getByRole('textbox', { name: 'Alasan' });
    expect(input.id).toBe('alasan');
    expect(input.getAttribute('aria-invalid')).toBe('grammar');
    expect(input.getAttribute('aria-describedby')?.split(' ')).toEqual(['petunjuk-audit', screen.getByText(props.helperText).id]);
    rerender(<><p id="petunjuk-audit">Dicatat pada audit.</p><Textarea {...props} aria-invalid={false} error="Alasan wajib diisi." /></>);
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(input.getAttribute('aria-describedby')?.split(' ')).toEqual(['petunjuk-audit', screen.getByRole('alert').id]);
});
