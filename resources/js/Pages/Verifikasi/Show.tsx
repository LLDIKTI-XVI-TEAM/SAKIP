import { useState, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { statusPerhitungan, type Pengukuran } from '@/Pages/Pengukuran/types';
import EvidenceList from '@/Pages/Pengukuran/EvidenceList';
import { formatNilai } from '@/Pages/Pengukuran/formatNilai';
import DecisionDialog, { type ReviewDecision } from './DecisionDialog';
import ClaimedActivities from './ClaimedActivities';

export default function VerifikasiShow({ pengukuran }: { pengukuran: Pengukuran }) {
    const { can } = pengukuran;
    const [decision, setDecision] = useState<ReviewDecision | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const indikator = pengukuran.penugasan_indikator.indikator_kinerja;
    const choose = (action: ReviewDecision, button: HTMLButtonElement) => { trigger.current = button; setDecision(action); };
    const closeDecision = () => { setDecision(null); if (trigger.current?.isConnected) trigger.current.focus(); };

    return <AuthenticatedLayout title="Reviu & Verifikasi Kinerja" breadcrumbs={[{ label: 'Verifikasi Kinerja', href: '/verifikasi' }, { label: indikator.kode }]}>
        <Head title={`Reviu ${indikator.kode}`} />
        <div className="mx-auto max-w-4xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3"><Link href="/verifikasi" className="rounded text-sm text-primary focus:outline-none focus:ring-2 focus:ring-primary">Kembali ke antrean verifikasi</Link><Badge status={pengukuran.status} /></div>
            {pengukuran.status === 'disahkan' && <div className="rounded-lg border border-success/30 bg-success/10 p-4 text-sm"><p className="font-semibold">Pengukuran telah disahkan</p><p className="mt-1">Snapshot resmi tersimpan. Waktu pengesahan: {pengukuran.snapshot?.disahkan_pada ? new Date(pengukuran.snapshot.disahkan_pada).toLocaleString('id-ID') : '—'}.</p></div>}
            <div className="flex flex-wrap gap-3 rounded-lg border border-border bg-surface p-3 text-xs text-muted">
                <span>Pengajuan ke-{pengukuran.nomor_pengajuan}</span>
                {pengukuran.jalur_pengajuan && <span>Jalur pengajuan: <strong className="capitalize text-ink">{pengukuran.jalur_pengajuan}</strong></span>}
                {pengukuran.self_approval && <span className="font-medium text-info-dark">Tindakan oleh pengaju tercatat sesuai kewenangan Perencanaan</span>}
                {pengukuran.reviu_terlambat && <span className="font-medium text-warning-dark">Reviu terlambat</span>}
            </div>
            <Card>
                <CardHeader><CardTitle>{indikator.kode} · {indikator.nama}</CardTitle></CardHeader>
                <CardContent className="space-y-5">
                    <p className="text-sm text-muted">{pengukuran.penugasan_indikator.unit_kerja.nama} · PIC: {pengukuran.penugasan_indikator.pic?.nama || '—'}</p>
                    {indikator.definisi_operasional && <p className="text-sm text-muted">{indikator.definisi_operasional}</p>}
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-lg border border-border bg-soft p-4"><dt className="text-xs text-muted">Target PK tahunan</dt><dd className="mt-1 text-xl font-semibold">{pengukuran.target_pk == null ? 'Belum tersedia' : `${formatNilai(pengukuran.target_pk, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd></div>
                        <div className="rounded-lg border border-border bg-soft p-4"><dt className="text-xs text-muted">Target periode RA · {pengukuran.periode_jadwal.nama_periode}</dt><dd className="mt-1 text-xl font-semibold">{pengukuran.target === null ? 'Belum tersedia' : `${formatNilai(pengukuran.target, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd></div>
                        <div className="rounded-lg border border-border bg-soft p-4"><dt className="text-xs text-muted">Nilai pengukuran</dt><dd className="mt-1 text-xl font-semibold">{pengukuran.nilai === null ? '—' : `${formatNilai(pengukuran.nilai, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd></div>
                        <div className="rounded-lg border border-border bg-soft p-4"><dt className="text-xs text-muted">Hasil perhitungan</dt><dd className="mt-1 font-semibold">{statusPerhitungan[pengukuran.status_perhitungan]}</dd><dd className="mt-1 text-xs text-muted">{indikator.tipe_perhitungan.replaceAll('_', ' ')}</dd></div>
                    </dl>
                    {pengukuran.komponen.length > 0 && <div className="overflow-x-auto"><table className="w-full text-left text-sm"><caption className="mb-2 text-left font-medium">Komponen pengajuan</caption><thead><tr className="border-b border-border"><th scope="col" className="p-2">Komponen</th><th scope="col" className="p-2">Peran / Bobot</th><th scope="col" className="p-2 text-right">Nilai</th></tr></thead><tbody>{pengukuran.komponen.map((item) => <tr key={item.komponen_id} className="border-b border-border"><th scope="row" className="p-2 font-normal">{item.kode} · {item.label}</th><td className="p-2">{item.peran}{item.bobot === null ? '' : ` / ${item.bobot}`}</td><td className="p-2 text-right">{item.nilai === null ? 'Belum diisi' : item.nilai}</td></tr>)}</tbody></table></div>}
                </CardContent>
            </Card>
            <Card><CardHeader><CardTitle>Catatan pengukuran</CardTitle></CardHeader><CardContent className="space-y-4 text-sm"><p className="whitespace-pre-wrap">{pengukuran.catatan || 'Tidak ada catatan.'}</p>{pengukuran.alasan_tidak_dapat_dihitung && <div><h2 className="font-medium">Alasan hasil tidak dapat dihitung</h2><p className="mt-1 whitespace-pre-wrap text-muted">{pengukuran.alasan_tidak_dapat_dihitung}</p></div>}</CardContent></Card>
            <Card><CardHeader><CardTitle>Bukti dukung pengajuan</CardTitle></CardHeader><CardContent><EvidenceList pengukuran={pengukuran} /></CardContent></Card>
            <ClaimedActivities pengukuran={pengukuran} />
            {(can.verify || can.ratify || can.return) && <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface p-5">
                <div><h2 className="text-sm font-semibold">Keputusan pengukuran</h2><p className="mt-1 text-xs text-muted">Tindakan mengikuti status dan kewenangan Anda saat ini.</p></div>
                <div className="flex flex-wrap gap-3">
                    {can.return && <Button type="button" variant="danger" className="bg-danger text-white hover:bg-danger/90 focus:ring-danger" onClick={(event) => choose('kembalikan', event.currentTarget)}>Kembalikan untuk revisi</Button>}
                    {can.verify && <Button type="button" className="bg-primary text-white hover:bg-primary/90 focus:ring-primary" onClick={(event) => choose('verifikasi', event.currentTarget)}>Verifikasi pengukuran</Button>}
                    {can.ratify && <Button type="button" className="bg-primary text-white hover:bg-primary/90 focus:ring-primary" onClick={(event) => choose('sahkan', event.currentTarget)}>Sahkan kinerja resmi</Button>}
                </div>
            </div>}
            {decision && <DecisionDialog key={`${pengukuran.id}-${decision}`} pengukuran={pengukuran} decision={decision} onClose={closeDecision} />}
        </div>
    </AuthenticatedLayout>;
}
