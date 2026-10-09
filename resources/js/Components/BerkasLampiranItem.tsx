import React, { type ReactNode } from 'react';
import { Download, ExternalLink, FileText, Link2, Quote } from 'lucide-react';

export interface BerkasLampiran {
    mode: 'file' | 'tautan' | 'teks';
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    download_url: string | null;
}

function formatBytes(value: number | null): string {
    if (!value || value <= 0) return '';
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

const modeIcon = { file: FileText, tautan: Link2, teks: Quote };

/** Satu baris lampiran tersimpan pada halaman detail; unduhan memakai URL ber-otorisasi dari server. */
export function BerkasLampiranItem({ berkas, action, meta }: { berkas: BerkasLampiran; action?: ReactNode; meta?: ReactNode }) {
    const Icon = modeIcon[berkas.mode];
    const title = berkas.nama_asli
        ?? (berkas.mode === 'file' ? 'Dokumen tanpa nama' : berkas.mode === 'tautan' ? 'Tautan dokumen' : 'Keterangan dokumen');
    const fileMeta = [berkas.mime, formatBytes(berkas.ukuran_bytes)].filter(Boolean).join(' · ');

    return (
        <li className="rounded-lg border border-border bg-page px-4 py-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <p className="flex items-center gap-2 text-sm font-semibold text-ink">
                        <Icon className="h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                        <span className="truncate">{title}</span>
                    </p>
                    {berkas.mode === 'file' && fileMeta && <p className="mt-1 text-xs text-muted">{fileMeta}</p>}
                    {berkas.mode === 'tautan' && berkas.tautan && <p className="mt-1 truncate text-xs text-muted">{berkas.tautan}</p>}
                    {berkas.mode === 'teks' && (berkas.isi_teks
                        ? <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-ink">{berkas.isi_teks}</p>
                        : <p className="mt-1 text-xs text-muted">Isi teks tidak tersedia.</p>)}
                    {meta && <p className="mt-1 text-xs text-muted">{meta}</p>}
                </div>

                <div className="flex shrink-0 items-center gap-3">
                    {berkas.mode === 'file' && berkas.download_url && (
                        <a href={berkas.download_url} className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Unduh file
                        </a>
                    )}
                    {berkas.mode === 'tautan' && berkas.tautan && (
                        <a href={berkas.tautan} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                            <ExternalLink className="h-4 w-4" aria-hidden="true" />
                            Buka tautan
                        </a>
                    )}
                    {action}
                </div>
            </div>
        </li>
    );
}
