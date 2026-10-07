import { useState, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import SahkanDialog from './SahkanDialog';
import type { RencanaAksiSahkan } from './types';

export default function RencanaAksiShow({ rencanaAksi }: { rencanaAksi: RencanaAksiSahkan }) {
    const [confirming, setConfirming] = useState(false);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const close = () => { setConfirming(false); if (trigger.current?.isConnected) trigger.current.focus(); };

    return <AuthenticatedLayout title="Pengesahan Rencana Aksi" breadcrumbs={[{ label: 'Rencana Aksi' }]}>
        <Head title="Pengesahan Rencana Aksi" />
        <div className="mx-auto max-w-4xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3"><Link href="/dashboard" className="rounded text-sm text-primary focus:outline-none focus:ring-2 focus:ring-primary">Kembali</Link><Badge status={rencanaAksi.status} /></div>
            {rencanaAksi.status === 'disahkan' && <div className="rounded-lg border border-success/30 bg-success/10 p-4 text-sm"><p className="font-semibold">Rencana aksi telah disahkan</p><p className="mt-1">Versi pengajuan beku dipertahankan sebagai dokumen resmi.</p></div>}
            <Card>
                <CardHeader><CardTitle>Pengesahan Rencana Aksi</CardTitle></CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <p className="text-muted">Pengajuan ke-{rencanaAksi.nomor_pengajuan} · versi data {rencanaAksi.versi}.</p>
                    {rencanaAksi.can.ratify && rencanaAksi.status === 'diverifikasi' && <div className="flex flex-wrap gap-3">
                        <Button type="button" className="bg-primary text-white hover:bg-primary/90 focus:ring-primary" onClick={(event) => { trigger.current = event.currentTarget; setConfirming(true); }}>Sahkan rencana aksi</Button>
                    </div>}
                </CardContent>
            </Card>
            {confirming && <SahkanDialog rencanaAksi={rencanaAksi} onClose={close} />}
        </div>
    </AuthenticatedLayout>;
}
