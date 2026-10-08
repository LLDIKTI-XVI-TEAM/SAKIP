import React, { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/Components/Button';

interface TambahMenuProps {
    showSasaran: boolean;
    showIndikator: boolean;
    onAddSasaran: () => void;
    onAddIndikator: () => void;
}

/**
 * Tombol gabungan "Tambah" untuk header Sasaran & Indikator.
 * Gate capability dihitung pemanggil dari props `can.*` server;
 * bila kedua item tidak memenuhi syarat, tombol tidak tampil sama sekali.
 */
export function TambahMenu({
    showSasaran,
    showIndikator,
    onAddSasaran,
    onAddIndikator,
}: TambahMenuProps) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const buttonId = useId();
    const menuId = useId();

    useEffect(() => {
        if (!open) return;

        const handlePointerDown = (event: PointerEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                setOpen(false);
                document.getElementById(buttonId)?.focus();
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [open, buttonId]);

    if (!showSasaran && !showIndikator) {
        return null;
    }

    const handleSelect = (handler: () => void) => () => {
        setOpen(false);
        handler();
        document.getElementById(buttonId)?.focus();
    };

    return (
        <div ref={containerRef} className="relative w-full lg:w-40">
            <Button
                variant="primary"
                type="button"
                id={buttonId}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((prev) => !prev)}
                className="w-full"
            >
                Tambah
            </Button>

            {open && (
                <div
                    id={menuId}
                    role="menu"
                    aria-labelledby={buttonId}
                    className="absolute right-0 z-30 mt-2 w-full overflow-hidden rounded-lg border border-border bg-surface py-1 shadow-md"
                >
                    {showSasaran && (
                        <button
                            type="button"
                            role="menuitem"
                            onClick={handleSelect(onAddSasaran)}
                            className="w-full px-3 py-2 text-left text-sm font-medium text-ink hover:bg-soft focus:bg-soft focus:outline-none"
                        >
                            Tambah Sasaran
                        </button>
                    )}
                    {showIndikator && (
                        <button
                            type="button"
                            role="menuitem"
                            onClick={handleSelect(onAddIndikator)}
                            className="w-full px-3 py-2 text-left text-sm font-medium text-ink hover:bg-soft focus:bg-soft focus:outline-none"
                        >
                            Tambah Indikator
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
