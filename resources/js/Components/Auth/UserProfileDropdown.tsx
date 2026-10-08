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
                    ? 'inline-flex items-center gap-1.5 rounded-lg p-1.5 text-white hover:bg-surface/10 focus:outline-none focus:ring-2 focus:ring-white/40'
                    : `flex items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-left transition-colors focus:outline-none focus:ring-2 focus:ring-primary/25 ${
                        open ? 'bg-soft ring-1 ring-border' : 'hover:bg-soft'
                    }`}
            >
                <span
                    aria-hidden="true"
                    className={`flex shrink-0 items-center justify-center rounded-full shadow-2xs ${
                        mobile ? 'h-8 w-8 bg-surface/15 text-white' : 'h-9 w-9 bg-primary text-white'
                    }`}
                >
                    <User className={mobile ? 'h-4 w-4' : 'h-4.5 w-4.5'} />
                </span>
                {!mobile && (
                    <span aria-hidden="true" className="hidden min-w-0 lg:block text-left">
                        <span className="block max-w-[180px] truncate text-sm font-semibold leading-tight text-ink">
                            {nama}
                        </span>
                        <span className="mt-0.5 block truncate text-xs font-medium leading-tight text-muted capitalize">
                            {peran}
                        </span>
                    </span>
                )}
                <ChevronDown
                    aria-hidden="true"
                    className={`h-4 w-4 shrink-0 transition-transform duration-200 motion-reduce:transition-none ${
                        mobile ? 'text-white/80' : 'text-muted'
                    } ${open ? 'rotate-180 text-primary' : ''}`}
                />
            </button>

            <div
                id={panelId}
                hidden={!open}
                className={`z-50 rounded-2xl border border-border/80 bg-surface text-ink shadow-xl ring-1 ring-black/5 ${
                    mobile
                        ? 'fixed right-4 top-[4.5rem] w-[calc(100vw-2rem)] max-w-xs'
                        : 'absolute right-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)]'
                }`}
            >
                <div className="flex items-start gap-3 border-b border-border/80 p-4 bg-soft/40">
                    <span
                        aria-hidden="true"
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-white shadow-2xs mt-0.5"
                    >
                        <User className="h-5 w-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-bold text-ink leading-snug">{nama}</p>
                        {user?.email && (
                            <p className="mt-0.5 truncate text-xs text-muted leading-snug">{user.email}</p>
                        )}
                        <div className="mt-2 flex items-center">
                            <span className="inline-flex items-center rounded-md border border-border bg-surface px-2 py-0.5 text-xs font-medium text-muted capitalize leading-none">
                                {peran}
                            </span>
                        </div>
                    </div>
                </div>
                <div className="p-2">
                    <LogoutActions appearance="menu" onPendingChange={setLogoutPending} />
                </div>
            </div>
        </div>
    );
}
