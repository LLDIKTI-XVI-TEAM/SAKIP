import { router } from '@inertiajs/react';
import { ChevronDown, User } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { LogoutActions } from '@/Components/Auth/LogoutActions';
import type { AuthUser } from '@/types/auth';

interface UserProfileDropdownProps {
    user: AuthUser | null;
    placement: 'header' | 'mobile';
    /**
     * Menjadikan profil inert, misalnya saat drawer navigasi mobile terbuka sebagai modal.
     * Panel sudah tertutup lebih dulu karena membuka drawer memindahkan pointer/fokus ke luar profil.
     */
    disabled?: boolean;
}

/** Dialog konfirmasi logout SSO dirender lewat portal sehingga berada di luar kontainer dropdown. */
const isInsideDialog = (target: EventTarget | null): boolean =>
    target instanceof Element && target.closest('dialog') !== null;

/**
 * Tombol profil header beserta panel identitas akun dan dua aksi keluar.
 * Panel tetap ter-mount saat tertutup agar status, error, dan penahan request
 * milik LogoutActions tidak hilang ketika pengguna menutup lalu membuka kembali panel.
 */
export function UserProfileDropdown({ user, placement, disabled = false }: UserProfileDropdownProps) {
    const [open, setOpen] = useState(false);
    const [logoutPending, setLogoutPending] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const panelId = useId();
    const nama = user?.nama || 'Pengguna';
    const peran = user?.role || 'Belum ada peran';
    const mobile = placement === 'mobile';

    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    useEffect(() => {
        if (!open) return;

        // Penutupan implisit ditahan selama logout berjalan agar hasil atau errornya tetap terbaca.
        const closeOnOutsidePointer = (event: PointerEvent) => {
            const target = event.target;
            if (logoutPending || !(target instanceof Node)) return;
            if (containerRef.current?.contains(target) || isInsideDialog(target)) return;
            setOpen(false);
        };
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key !== 'Escape' || isInsideDialog(event.target)) return;
            setOpen(false);
            triggerRef.current?.focus();
        };

        document.addEventListener('pointerdown', closeOnOutsidePointer);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOnOutsidePointer);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open, logoutPending]);

    return (
        <div
            ref={containerRef}
            inert={disabled || undefined}
            className={mobile ? 'relative' : 'relative border-l border-border pl-2'}
            onBlur={(event) => {
                // Fokus yang hilang tanpa tujuan (mis. tombol berubah disabled) tidak menutup panel.
                const next = event.relatedTarget;
                if (!open || logoutPending || !(next instanceof Node)) return;
                if (containerRef.current?.contains(next) || isInsideDialog(next)) return;
                setOpen(false);
            }}
        >
            <button
                ref={triggerRef}
                type="button"
                aria-expanded={open}
                aria-controls={panelId}
                aria-label={`Menu akun ${nama} (${peran})`}
                onClick={() => setOpen((isOpen) => !isOpen)}
                className={mobile
                    ? 'inline-flex items-center gap-1 rounded-lg p-1.5 text-white hover:bg-surface/10 focus:outline-none focus:ring-2 focus:ring-white/40'
                    : 'flex items-center gap-2.5 rounded-xl px-2 py-1.5 text-left hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25'}
            >
                <span
                    aria-hidden="true"
                    className={`flex shrink-0 items-center justify-center rounded-full ${mobile ? 'h-8 w-8 bg-surface/15 text-white' : 'h-9 w-9 bg-primary text-white'}`}
                >
                    <User className="h-4 w-4" />
                </span>
                {!mobile && (
                    <span aria-hidden="true" className="hidden min-w-0 lg:block">
                        <span className="block max-w-[160px] truncate text-xs font-bold leading-none text-primary">{nama}</span>
                        <span className="mt-1 block text-[11px] leading-none text-muted capitalize">{peran}</span>
                    </span>
                )}
                <ChevronDown
                    aria-hidden="true"
                    className={`h-4 w-4 shrink-0 transition-transform duration-150 motion-reduce:transition-none ${mobile ? 'text-white/80' : 'text-muted'} ${open ? 'rotate-180' : ''}`}
                />
            </button>

            <div
                id={panelId}
                hidden={!open}
                className={`z-50 rounded-xl border border-border bg-surface text-ink shadow-lg ${mobile
                    ? 'fixed right-4 top-[4.5rem] w-[calc(100vw-2rem)] max-w-xs'
                    : 'absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-2rem)]'}`}
            >
                <div className="flex items-center gap-3 border-b border-border px-4 py-3">
                    <span aria-hidden="true" className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                        <User className="h-4 w-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-ink">{nama}</p>
                        <p className="truncate text-xs text-muted capitalize">{peran}</p>
                    </div>
                </div>
                <div className="p-2">
                    <LogoutActions appearance="menu" onPendingChange={setLogoutPending} />
                </div>
            </div>
        </div>
    );
}
