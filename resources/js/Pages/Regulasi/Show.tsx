import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, FileText } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import type { BerkasRegulasi, RegulasiDetail, RegulasiJenis } from '@/types/regulasi';

interface ShowRegulasiProps {
    regulasi: RegulasiDetail;
}

const jenisLabel: Record<RegulasiJenis, string> = {
    kepmen: 'Keputusan Menteri',
    permen: 'Peraturan Menteri',
    perpres: 'Peraturan Presiden',
    keputusan_lainnya: 'Keputusan lainnya',
};

function formatTanggal(value: string | null): string {
    if (!value) return 'Tidak dicantumkan';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatBytes(value: number | null): string {
    if (value === null) return '';
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function Attachment({ attachment }: { attachment: BerkasRegulasi }) {
    const title = attachment.mode === 'file'
        ? attachment.nama_asli ?? 'Dokumen tanpa nama'
        : attachment.mode === 'tautan'
            ? 'Tautan dokumen sumber'
            : 'Keterangan dokumen';

    return (
        <li className="rounded-lg border border-border bg-page px-4 py-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <p className="flex items-center gap-2 text-sm font-semibold text-ink">
                        <FileText className="h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                        {title}
                    </p>
                    {attachment.mode === 'file' && (
                        <p className="mt-1 text-xs text-muted">{attachment.mime} · {formatBytes(attachment.ukuran_bytes)}</p>
                    )}
                    {attachment.mode === 'teks' && (
                        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-ink">{attachment.isi_teks}</p>
                    )}
                </div>

                <div className="shrink-0">
                    {attachment.mode === 'file' && attachment.download_url && (
                        <a href={attachment.download_url} className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Unduh file
                        </a>
                    )}
                    {attachment.mode === 'tautan' && attachment.tautan && (
                        <a href={attachment.tautan} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                            <ExternalLink className="h-4 w-4" aria-hidden="true" />
                            Buka tautan
                        </a>
                    )}
                </div>
            </div>
        </li>
    );
}

export default function ShowRegulasi({ regulasi }: ShowRegulasiProps) {
    return (
        <AuthenticatedLayout
            title="Detail Dasar Aturan"
            breadcrumbs={[{ label: 'Dasar Aturan', href: '/regulasi' }, { label: `${regulasi.nomor}/${regulasi.tahun}` }]}
        >
            <Head title={`${regulasi.nomor}/${regulasi.tahun}`} />

            <div className="mx-auto max-w-5xl space-y-5">
                <Link href="/regulasi" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke daftar
                </Link>

                <Card>
                    <CardHeader className="items-start gap-4">
                        <div>
                            <CardTitle>{regulasi.nomor}/{regulasi.tahun}</CardTitle>
                            <p className="mt-1 text-sm leading-6 text-muted">{jenisLabel[regulasi.jenis]}</p>
                        </div>
                        <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${regulasi.aktif ? 'bg-success/10 text-success' : 'bg-soft text-muted'}`}>
                            {regulasi.aktif ? 'Aktif' : 'Nonaktif'}
                        </span>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold text-muted">Tentang</dt>
                                <dd className="mt-1 text-sm leading-6 text-ink">{regulasi.tentang}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Tanggal penetapan</dt>
                                <dd className="mt-1 text-sm text-ink">{formatTanggal(regulasi.tanggal)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Sumber resmi</dt>
                                <dd className="mt-1">
                                    {regulasi.tautan_sumber ? (
                                        <a href={regulasi.tautan_sumber} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                                            Buka sumber resmi <ExternalLink className="h-4 w-4" aria-hidden="true" />
                                        </a>
                                    ) : (
                                        <span className="text-sm text-muted">Tidak dicantumkan</span>
                                    )}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold text-muted">Catatan</dt>
                                <dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-ink">{regulasi.catatan || 'Tidak ada catatan.'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lampiran dokumen</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {regulasi.berkas.length > 0 ? (
                            <ul className="space-y-3">
                                {regulasi.berkas.map((attachment) => <Attachment key={attachment.id} attachment={attachment} />)}
                            </ul>
                        ) : (
                            <p className="text-sm leading-6 text-muted">Belum ada lampiran dokumen pada regulasi ini.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
