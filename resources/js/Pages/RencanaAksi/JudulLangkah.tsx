import type { ReactNode } from 'react';
import { CardTitle } from '@/Components/Card';

/** Judul kartu halaman detail RA; nomor langkah hanya penanda visual sehingga nama heading tetap judulnya. */
export default function JudulLangkah({ id, nomor, children }: { id?: string; nomor?: number; children: ReactNode }) {
    return (
        <CardTitle id={id} className="flex items-center gap-2.5">
            {nomor !== undefined && (
                <span aria-hidden="true" className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                    {nomor}
                </span>
            )}
            {children}
        </CardTitle>
    );
}
