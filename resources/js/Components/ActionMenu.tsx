import React, { useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { clsx } from 'clsx';
import { Button } from '@/Components/Button';

export interface ActionMenuItem {
    key: string;
    label: string;
    icon?: LucideIcon;
    /** Nama aksesibel yang lebih spesifik dari label, mis. menyertakan kode objek. */
    ariaLabel?: string;
    href?: string;
    onSelect?: () => void;
    danger?: boolean;
}

interface ActionMenuProps {
    items: ActionMenuItem[];
    trigger: ReactNode;
    /** Wajib bila pemicu hanya berisi ikon. */
    triggerLabel?: string;
    variant?: 'primary' | 'outline' | 'ghost';
    triggerClassName?: string;
}

const MENU_GAP = 4;
const VIEWPORT_MARGIN = 8;

/**
 * Menu aksi bergaya dropdown. Posisi `fixed` dihitung dari tombol pemicu agar menu tidak terpotong
 * oleh kontainer `overflow` (mis. tabel di dalam kartu); visibilitas item tetap ditentukan pemanggil dari `can.*`.
 */
export function ActionMenu({ items, trigger, triggerLabel, variant = 'outline', triggerClassName }: ActionMenuProps) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const buttonId = useId();
    const menuId = useId();

    const close = (returnFocus: boolean) => {
        setOpen(false);
        if (returnFocus) document.getElementById(buttonId)?.focus();
    };

    useLayoutEffect(() => {
        const menu = menuRef.current;
        const anchor = containerRef.current?.getBoundingClientRect();
        if (!open || !menu || !anchor) return;
        const fitsBelow = anchor.bottom + MENU_GAP + menu.offsetHeight <= window.innerHeight - VIEWPORT_MARGIN;
        const top = fitsBelow ? anchor.bottom + MENU_GAP : anchor.top - MENU_GAP - menu.offsetHeight;
        const left = Math.min(
            Math.max(anchor.right - menu.offsetWidth, VIEWPORT_MARGIN),
            window.innerWidth - menu.offsetWidth - VIEWPORT_MARGIN,
        );
        menu.style.top = `${Math.max(top, VIEWPORT_MARGIN)}px`;
        menu.style.left = `${left}px`;
        menu.querySelector<HTMLElement>('[role=menuitem]')?.focus();
    }, [open]);

    useEffect(() => {
        if (!open) return;
        const handlePointerDown = (event: PointerEvent) => {
            const target = event.target as Node;
            if (!containerRef.current?.contains(target) && !menuRef.current?.contains(target)) setOpen(false);
        };
        // Posisi fixed tidak mengikuti scroll/resize, jadi menu ditutup.
        const handleViewportChange = () => setOpen(false);
        document.addEventListener('pointerdown', handlePointerDown);
        window.addEventListener('scroll', handleViewportChange, true);
        window.addEventListener('resize', handleViewportChange);
        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            window.removeEventListener('scroll', handleViewportChange, true);
            window.removeEventListener('resize', handleViewportChange);
        };
    }, [open]);

    if (items.length === 0) return null;

    const handleMenuKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const menuItems = Array.from(menuRef.current?.querySelectorAll<HTMLElement>('[role=menuitem]') ?? []);
        const current = menuItems.indexOf(document.activeElement as HTMLElement);
        const focusAt = (index: number) => menuItems[(index + menuItems.length) % menuItems.length]?.focus();
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
        } else if (event.key === 'Tab') {
            setOpen(false);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusAt(current + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusAt(current - 1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            focusAt(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            focusAt(menuItems.length - 1);
        }
    };

    const firstDanger = items.findIndex((item) => item.danger);

    return (
        <div ref={containerRef} className="relative inline-flex">
            <Button
                type="button"
                id={buttonId}
                variant={variant}
                size="sm"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={open ? menuId : undefined}
                aria-label={triggerLabel}
                onClick={() => setOpen((previous) => !previous)}
                className={triggerClassName}
            >
                {trigger}
            </Button>

            {open && (
                <div
                    ref={menuRef}
                    id={menuId}
                    role="menu"
                    aria-labelledby={buttonId}
                    onKeyDown={handleMenuKeyDown}
                    className="fixed z-50 w-52 overflow-hidden rounded-lg border border-border bg-surface py-1 shadow-md"
                >
                    {items.map((item, index) => {
                        const Icon = item.icon;
                        const className = clsx(
                            'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm font-medium focus:outline-none',
                            item.danger ? 'text-danger hover:bg-danger/10 focus:bg-danger/10' : 'text-ink hover:bg-soft focus:bg-soft',
                        );
                        const content = (
                            <>
                                {Icon && <Icon className={clsx('h-4 w-4 shrink-0', !item.danger && 'text-muted')} aria-hidden="true" />}
                                {item.label}
                            </>
                        );

                        return (
                            <React.Fragment key={item.key}>
                                {index === firstDanger && index > 0 && <div role="separator" className="my-1 border-t border-border" />}
                                {item.href ? (
                                    <Link href={item.href} role="menuitem" tabIndex={-1} aria-label={item.ariaLabel} className={className} onClick={() => setOpen(false)}>
                                        {content}
                                    </Link>
                                ) : (
                                    <button
                                        type="button"
                                        role="menuitem"
                                        tabIndex={-1}
                                        aria-label={item.ariaLabel}
                                        className={className}
                                        onClick={() => {
                                            close(false);
                                            item.onSelect?.();
                                        }}
                                    >
                                        {content}
                                    </button>
                                )}
                            </React.Fragment>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
