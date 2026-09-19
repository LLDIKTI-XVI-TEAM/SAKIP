import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Button } from '@/Components/Button';

afterEach(cleanup);

describe('Button', () => {
    it('mencegah klik selama pending dan dapat dipakai kembali setelah selesai', async () => {
        const onClick = vi.fn();
        const user = userEvent.setup();
        const view = render(<Button isLoading onClick={onClick}>Simpan</Button>);

        await user.click(screen.getByRole('button', { name: 'Simpan' }));
        expect(onClick).not.toHaveBeenCalled();

        view.rerender(<Button onClick={onClick}>Simpan</Button>);
        await user.click(screen.getByRole('button', { name: 'Simpan' }));
        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('tetap mencegah klik ketika disabled meskipun tidak sedang pending', async () => {
        const onClick = vi.fn();
        const user = userEvent.setup();
        render(<Button disabled onClick={onClick}>Simpan</Button>);

        await user.click(screen.getByRole('button', { name: 'Simpan' }));
        expect(onClick).not.toHaveBeenCalled();
    });
});
