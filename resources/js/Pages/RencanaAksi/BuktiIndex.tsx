import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { BuktiRencanaAksiPanel } from '@/Components/RencanaAksi/BuktiRencanaAksiPanel';
import type { BuktiRencanaAksiPageProps } from '@/types/rencana-aksi';

export default function BuktiIndex(props: BuktiRencanaAksiPageProps) {
    const { rencanaAksi } = props;

    return (
        <AuthenticatedLayout
            title="Pemenuhan Bukti Dukung"
            breadcrumbs={[
                { label: 'Rencana Aksi', href: '/rencana-aksi' },
                { label: `${rencanaAksi.indikator?.kode || 'RA'} · ${rencanaAksi.tahun}`, href: `/rencana-aksi/${rencanaAksi.id}` },
                { label: 'Bukti Dukung' },
            ]}
        >
            <Head title={`Bukti Dukung Rencana Aksi · ${rencanaAksi.indikator?.kode || 'RA'}`} />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <Link
                        href={`/rencana-aksi/${rencanaAksi.id}`}
                        className="inline-flex items-center gap-2 rounded text-sm text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        <span>Kembali ke Rencana Aksi</span>
                    </Link>
                </div>

                <BuktiRencanaAksiPanel {...props} />
            </div>
        </AuthenticatedLayout>
    );
}
