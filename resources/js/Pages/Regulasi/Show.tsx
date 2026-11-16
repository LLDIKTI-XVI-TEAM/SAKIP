import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { jenisRegulasiLabel } from '@/lib/regulasi';
import { tautanAman } from '@/lib/url';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { RegulasiDetail } from '@/types/regulasi';

interface ShowRegulasiProps {
    regulasi: RegulasiDetail;
}

export default function ShowRegulasi({ regulasi }: ShowRegulasiProps) {
    const formatTanggal = useFormatTanggal();
    const sumberResmi = tautanAman(regulasi.tautan_sumber);

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
                            <p className="mt-1 text-sm leading-6 text-muted">{jenisRegulasiLabel[regulasi.jenis]}</p>
                        </div>
                        <Badge variant={regulasi.aktif ? 'success' : 'muted'} dot className="shrink-0">
                            {regulasi.aktif ? 'Aktif' : 'Nonaktif'}
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold text-muted">Tentang</dt>
                                <dd className="mt-1 text-sm leading-6 text-ink">{regulasi.tentang}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Tanggal penetapan</dt>
                                <dd className="mt-1 text-sm text-ink">{regulasi.tanggal ? formatTanggal(regulasi.tanggal) : 'Tidak dicantumkan'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Sumber resmi</dt>
                                <dd className="mt-1">
                                    {sumberResmi ? (
                                        <a href={sumberResmi} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
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
                                {regulasi.berkas.map((berkas) => <BerkasLampiranItem key={berkas.id} berkas={berkas} />)}
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
