import { useState, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import SahkanDialog from './SahkanDialog';
import type { RencanaAksiDetail } from './types';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

export default function RencanaAksiReviu({ rencanaAksi }: { rencanaAksi: RencanaAksiDetail }) {
    const [confirming, setConfirming] = useState(false);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const formatTanggal = useFormatTanggal();
    const close = () => { setConfirming(false); if (trigger.current?.isConnected) trigger.current.focus(); };

    return <AuthenticatedLayout title="Pengesahan Rencana Aksi" breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: rencanaAksi.indikator.kode }]}>
        <Head title={`Pengesahan ${rencanaAksi.indikator.kode}`} />
        <div className="mx-auto max-w-4xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3"><Link href="/rencana-aksi?status=antrean" className="rounded text-sm text-primary focus:outline-none focus:ring-2 focus:ring-primary">Kembali ke antrean rencana aksi</Link><Badge status={rencanaAksi.status} /></div>
            {rencanaAksi.status === 'disahkan' && <div className="rounded-lg border border-success/30 bg-success/10 p-4 text-sm"><p className="font-semibold">Rencana aksi telah disahkan</p><p className="mt-1">Versi pengajuan beku dipertahankan sebagai dokumen resmi.{rencanaAksi.disahkan_pada ? ` Waktu pengesahan: ${formatTanggal(rencanaAksi.disahkan_pada, { withTime: true })}.` : ''}</p>{rencanaAksi.disahkan_oleh && <p className="mt-1">Disahkan oleh: <strong>{rencanaAksi.disahkan_oleh.nama}</strong></p>}{rencanaAksi.self_approval && <p className="mt-1 font-semibold">Disahkan sendiri oleh pengaju melalui jalur Perencanaan (self-approval).</p>}</div>}
            {rencanaAksi.konteks_tidak_lengkap.length > 0 && <div role="status" className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark"><p className="font-semibold">Sebagian konteks beku tidak lengkap</p><p className="mt-1">Snapshot versi ini tidak memuat: {rencanaAksi.konteks_tidak_lengkap.join(', ')}. Nilai bertanda &ldquo;konteks tidak lengkap&rdquo; bukan data terbaru.</p></div>}
            <div className="flex flex-wrap gap-3 rounded-lg border border-border bg-surface p-3 text-xs text-muted">
                <span>Pengajuan ke-{rencanaAksi.nomor_pengajuan}</span>
                {rencanaAksi.jalur_pengajuan && <span>Jalur pengajuan: <strong className="capitalize text-ink">{rencanaAksi.jalur_pengajuan}</strong></span>}
                {rencanaAksi.diajukan_oleh && <span>Diajukan oleh: <strong className="text-ink">{rencanaAksi.diajukan_oleh.nama}</strong></span>}
                {rencanaAksi.diajukan_pada && <span>Diajukan pada: {formatTanggal(rencanaAksi.diajukan_pada, { withTime: true })}</span>}
            </div>
            <Card>
                <CardHeader><CardTitle>{rencanaAksi.indikator.kode} · {rencanaAksi.indikator.nama}</CardTitle></CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <p className="text-muted">{rencanaAksi.unit_kerja.nama} · PIC: {rencanaAksi.pic?.nama || '—'} · Tahun {rencanaAksi.tahun}</p>
                    <p className="whitespace-pre-wrap">{rencanaAksi.uraian || 'Tidak ada uraian.'}</p>
                    <p className="text-muted">Pengajuan ke-{rencanaAksi.nomor_pengajuan} · versi data {rencanaAksi.versi}.</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Matriks target beku</CardTitle></CardHeader>
                <CardContent>
                    {rencanaAksi.target_periode.length === 0 ? (
                        <p className="text-sm text-muted">Target periode belum tersedia pada versi pengajuan ini.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <caption className="mb-2 text-left font-medium">Target per periode dari snapshot beku</caption>
                                <thead>
                                    <tr className="border-b border-border">
                                        <th scope="col" className="p-2">Periode</th>
                                        <th scope="col" className="p-2 text-right">Nilai target</th>
                                        <th scope="col" className="p-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rencanaAksi.target_periode.map((row) => (
                                        <tr key={row.periode_id || row.periode_nama || 'tanpa-periode'} className="border-b border-border">
                                            <th scope="row" className="p-2 font-normal">{row.periode_nama || row.periode_id || '—'}</th>
                                            <td className="p-2 text-right">{row.nilai === null || row.nilai === undefined ? 'Belum diisi' : String(row.nilai)}</td>
                                            <td className="p-2 text-muted">{row.status_perhitungan || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Rincian komponen beku</CardTitle></CardHeader>
                <CardContent>
                    {rencanaAksi.target_periode.every((row) => (row.komponen ?? []).length === 0) ? (
                        <p className="text-sm text-muted">Tidak ada rincian komponen pada versi pengajuan ini.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <caption className="mb-2 text-left font-medium">Nilai tiap komponen beku per periode</caption>
                                <thead>
                                    <tr className="border-b border-border">
                                        <th scope="col" className="p-2">Periode</th>
                                        <th scope="col" className="p-2">Kode komponen</th>
                                        <th scope="col" className="p-2">Label komponen</th>
                                        <th scope="col" className="p-2 text-right">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rencanaAksi.target_periode.flatMap((row) =>
                                        (row.komponen ?? []).map((komponen, index) => (
                                            <tr key={`${row.periode_id ?? row.periode_nama ?? 'tanpa-periode'}-${komponen.komponen_id ?? index}`} className="border-b border-border">
                                                <th scope="row" className="p-2 font-normal">{row.periode_nama || row.periode_id || '—'}</th>
                                                <td className="p-2 font-mono text-xs">{komponen.kode || komponen.komponen_id || '—'}</td>
                                                <td className="p-2 text-muted">{komponen.label || '—'}</td>
                                                <td className="p-2 text-right">{komponen.nilai === null || komponen.nilai === undefined ? 'Belum diisi' : String(komponen.nilai)}</td>
                                            </tr>
                                        )),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader><CardTitle>Bukti dukung pengajuan</CardTitle></CardHeader>
                <CardContent>
                    {!rencanaAksi.can.evidence ? (
                        <p className="text-sm text-muted">Anda tidak memiliki akses untuk melihat bukti dukung pengajuan ini.</p>
                    ) : rencanaAksi.bukti_dukungs.length === 0 ? (
                        <p className="text-sm text-muted">Tidak ada bukti dukung pada versi pengajuan ini.</p>
                    ) : (
                        <ul className="space-y-3 text-sm">
                            {rencanaAksi.bukti_dukungs.map((bukti) => (
                                <li key={bukti.id} className="rounded-lg border border-border bg-soft p-3">
                                    <p className="font-medium">{bukti.nama_asli || bukti.mode}</p>
                                    <p className="mt-0.5 text-xs text-muted">Mode: {bukti.mode}</p>
                                    {bukti.mode === 'file' && bukti.download_url && (
                                        <p className="mt-1 text-xs">
                                            <a href={bukti.download_url} className="rounded text-primary underline focus:outline-none focus:ring-2 focus:ring-primary">Unduh berkas</a>
                                        </p>
                                    )}
                                    {bukti.mode === 'tautan' && bukti.tautan && <p className="mt-1 break-all text-xs text-primary">{bukti.tautan}</p>}
                                    {bukti.mode === 'teks' && bukti.isi_teks && <p className="mt-1 whitespace-pre-wrap text-xs">{bukti.isi_teks}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
            {rencanaAksi.can.ratify && rencanaAksi.status === 'diverifikasi' && <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface p-5">
                <div><h2 className="text-sm font-semibold">Keputusan rencana aksi</h2><p className="mt-1 text-xs text-muted">Pengesahan menyimpan versi pengajuan beku sebagai dokumen resmi.</p></div>
                <div className="flex flex-wrap gap-3">
                    <Button type="button" className="bg-primary text-white hover:bg-primary/90 focus:ring-primary" onClick={(event) => { trigger.current = event.currentTarget; setConfirming(true); }}>Sahkan rencana aksi</Button>
                </div>
            </div>}
            {confirming && <SahkanDialog rencanaAksi={rencanaAksi} onClose={close} />}
        </div>
    </AuthenticatedLayout>;
}
