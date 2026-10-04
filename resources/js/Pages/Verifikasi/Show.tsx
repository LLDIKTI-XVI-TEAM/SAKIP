import { useState, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { Clock, History } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Alert } from '@/Components/Alert';
import { Badge } from '@/Components/Badge';
import { BackButton } from '@/Components/BackButton';
import { Button } from '@/Components/Button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/Table';
import { statusPerhitungan, type Pengukuran } from '@/Pages/Pengukuran/types';
import EvidenceList from '@/Pages/Pengukuran/EvidenceList';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import DecisionDialog, { type ReviewDecision } from './DecisionDialog';
import ClaimedActivities from './ClaimedActivities';

export default function VerifikasiShow({ pengukuran }: { pengukuran: Pengukuran }) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const { can } = pengukuran;
    const [decision, setDecision] = useState<ReviewDecision | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const indikator = pengukuran.penugasan_indikator.indikator_kinerja;

    const choose = (action: ReviewDecision, button: HTMLButtonElement) => {
        trigger.current = button;
        setDecision(action);
    };

    const closeDecision = () => {
        setDecision(null);
        if (trigger.current?.isConnected) trigger.current.focus();
    };

    const riwayats = pengukuran.riwayats ?? [];
    const hasNotesOrHistory = Boolean(
        pengukuran.catatan || pengukuran.alasan_tidak_dapat_dihitung || riwayats.length > 0
    );

    return (
        <AuthenticatedLayout
            title="Verifikasi & Pengesahan"
            breadcrumbs={[
                { label: 'Verifikasi & Pengesahan', href: '/verifikasi' },
                { label: indikator.kode },
            ]}
        >
            <Head title={`Reviu ${indikator.kode}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header Actions */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <BackButton href="/verifikasi">
                        Kembali ke antrean verifikasi
                    </BackButton>
                    <Badge status={pengukuran.status} />
                </div>

                {/* Ratification Notice */}
                {pengukuran.status === 'disahkan' && (
                    <Alert variant="success" title="Pengukuran telah disahkan">
                        Snapshot resmi tersimpan. Waktu pengesahan:{' '}
                        {formatTanggal(pengukuran.snapshot?.disahkan_pada, { withTime: true })}.
                    </Alert>
                )}

                {/* Submission Metadata Bar */}
                <div className="flex flex-wrap items-center gap-2.5 rounded-lg border border-border bg-surface px-4 py-3 text-xs text-muted">
                    <span>
                        Pengajuan ke-<strong className="font-semibold text-ink">{pengukuran.nomor_pengajuan}</strong>
                    </span>
                    {pengukuran.jalur_pengajuan && (
                        <>
                            <span className="text-border" aria-hidden="true">•</span>
                            <span>
                                Jalur:{' '}
                                <strong className="capitalize font-semibold text-ink">
                                    {pengukuran.jalur_pengajuan}
                                </strong>
                            </span>
                        </>
                    )}
                    {pengukuran.self_approval && (
                        <Badge variant="info" size="sm">
                            Persetujuan sendiri
                        </Badge>
                    )}
                    {pengukuran.reviu_terlambat && (
                        <Badge variant="warning" size="sm">
                            Reviu terlambat
                        </Badge>
                    )}
                </div>

                {/* Indicator and Performance Data */}
                <Card>
                    <CardHeader>
                        <CardTitle className="font-semibold text-ink">
                            <span className="font-mono">{indikator.kode}</span> · {indikator.nama}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <p className="text-xs text-muted">
                            {pengukuran.penugasan_indikator.unit_kerja.nama} · PIC:{' '}
                            <span className="font-medium text-ink">
                                {pengukuran.penugasan_indikator.pic?.nama || '-'}
                            </span>
                        </p>
                        {indikator.definisi_operasional && (
                            <p className="text-xs leading-relaxed text-muted bg-soft/50 p-3 rounded-lg border border-border/80">
                                {indikator.definisi_operasional}
                            </p>
                        )}
                        <dl className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-border bg-soft p-4">
                                <dt className="text-xs font-medium text-muted">Target PK tahunan</dt>
                                <dd className="mt-1 text-xl font-semibold text-ink">
                                    {pengukuran.target_pk == null ? (
                                        <span className="text-sm font-normal text-muted">Belum tersedia</span>
                                    ) : (
                                        <>
                                            <span className="font-mono">
                                                {formatNilai(pengukuran.target_pk, indikator.desimal_tampilan)}
                                            </span>{' '}
                                            <span className="text-sm font-normal text-muted">{indikator.satuan}</span>
                                        </>
                                    )}
                                </dd>
                            </div>
                            <div className="rounded-lg border border-border bg-soft p-4">
                                <dt className="text-xs font-medium text-muted">
                                    Target periode RA · {pengukuran.periode_jadwal.nama_periode}
                                </dt>
                                <dd className="mt-1 text-xl font-semibold text-ink">
                                    {pengukuran.target === null ? (
                                        <span className="text-sm font-normal text-muted">Belum tersedia</span>
                                    ) : (
                                        <>
                                            <span className="font-mono">
                                                {formatNilai(pengukuran.target, indikator.desimal_tampilan)}
                                            </span>{' '}
                                            <span className="text-sm font-normal text-muted">{indikator.satuan}</span>
                                        </>
                                    )}
                                </dd>
                            </div>
                            <div className="rounded-lg border border-border bg-soft p-4">
                                <dt className="text-xs font-medium text-muted">Nilai pengukuran (realisasi)</dt>
                                <dd className="mt-1 text-xl font-semibold text-ink">
                                    {pengukuran.nilai === null ? (
                                        <span className="text-sm font-normal text-muted">Belum diisi</span>
                                    ) : (
                                        <>
                                            <span className="font-mono">
                                                {formatNilai(pengukuran.nilai, indikator.desimal_tampilan)}
                                            </span>{' '}
                                            <span className="text-sm font-normal text-muted">{indikator.satuan}</span>
                                        </>
                                    )}
                                </dd>
                            </div>
                            <div className="rounded-lg border border-border bg-soft p-4">
                                <dt className="text-xs font-medium text-muted">Hasil perhitungan</dt>
                                <dd className="mt-1 text-base font-semibold text-ink">
                                    {statusPerhitungan[pengukuran.status_perhitungan]}
                                </dd>
                                <dd className="mt-0.5 text-xs text-muted capitalize">
                                    Tipe: {indikator.tipe_perhitungan.replaceAll('_', ' ')}
                                </dd>
                            </div>
                        </dl>

                        {/* Components Breakdown Table */}
                        {pengukuran.komponen.length > 0 && (
                            <div className="mt-4 space-y-2">
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted">
                                    Komponen Pengajuan
                                </h4>
                                <Table className="min-w-full">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="px-4 py-2.5">Komponen</TableHead>
                                            <TableHead className="px-4 py-2.5">Peran / Bobot</TableHead>
                                            <TableHead className="px-4 py-2.5 text-right">Nilai</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pengukuran.komponen.map((item) => (
                                            <TableRow key={item.komponen_id}>
                                                <TableCell className="px-4 py-2.5">
                                                    <span className="font-mono font-semibold text-ink">{item.kode}</span> · {item.label}
                                                </TableCell>
                                                <TableCell className="px-4 py-2.5 text-muted">
                                                    {item.peran}
                                                    {item.bobot === null ? '' : ` / ${item.bobot}`}
                                                </TableCell>
                                                <TableCell className="px-4 py-2.5 text-right font-semibold text-ink">
                                                    {item.nilai === null ? (
                                                        <span className="font-sans font-normal text-muted">Belum diisi</span>
                                                    ) : (
                                                        <span className="font-mono">
                                                            {formatNilai(item.nilai, indikator.desimal_tampilan)}
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Unified Notes and History Section */}
                {hasNotesOrHistory && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <History className="h-4 w-4 text-muted" aria-hidden="true" />
                                Catatan & Riwayat Verifikasi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {pengukuran.catatan && (
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted mb-1">
                                        Catatan Pengajuan
                                    </h4>
                                    <p className="whitespace-pre-wrap rounded-lg border border-border bg-soft/40 p-3 text-xs text-ink leading-relaxed">
                                        {pengukuran.catatan}
                                    </p>
                                </div>
                            )}

                            {pengukuran.alasan_tidak_dapat_dihitung && (
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-warning-dark mb-1">
                                        Alasan Hasil Tidak Dapat Dihitung
                                    </h4>
                                    <p className="whitespace-pre-wrap rounded-lg border border-warning/20 bg-warning/5 p-3 text-xs text-ink leading-relaxed">
                                        {pengukuran.alasan_tidak_dapat_dihitung}
                                    </p>
                                </div>
                            )}

                            {riwayats.length > 0 && (
                                <div className="space-y-2 pt-1">
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted mb-2">
                                        Jejak Riwayat Status ({riwayats.length})
                                    </h4>
                                    <ul className="divide-y divide-border rounded-lg border border-border" aria-label="Riwayat status pengukuran">
                                        {riwayats.map((r) => (
                                            <li key={r.id} className="p-3 text-xs space-y-1">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div className="flex items-center gap-2">
                                                        <Badge status={r.status_ke} size="sm" />
                                                        <span className="font-medium text-ink">
                                                            {r.user?.nama || 'Sistem'}
                                                        </span>
                                                    </div>
                                                    <span className="flex items-center gap-1 text-[11px] text-muted">
                                                        <Clock className="h-3 w-3" aria-hidden="true" />
                                                        {formatTanggal(r.created_at, { withTime: true })}
                                                    </span>
                                                </div>
                                                {r.catatan && (
                                                    <p className="mt-1 text-muted whitespace-pre-wrap bg-soft/50 p-2 rounded border border-border/60">
                                                        {r.catatan}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Evidence List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Bukti Dukung Pengajuan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EvidenceList pengukuran={pengukuran} />
                    </CardContent>
                </Card>

                {/* Claimed Activities */}
                <ClaimedActivities pengukuran={pengukuran} />

                {/* Decision Actions Bar */}
                {(can.verify || can.ratify || can.return) && (
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-surface p-5 shadow-xs">
                        <div>
                            <h2 className="text-sm font-semibold text-ink">Keputusan Pengukuran</h2>
                            <p className="mt-0.5 text-xs text-muted">
                                Pilih tindakan verifikasi sesuai hasil pemeriksaan substansi.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2.5">
                            {can.return && (
                                <Button
                                    type="button"
                                    variant="danger"
                                    onClick={(event) => choose('kembalikan', event.currentTarget)}
                                >
                                    Kembalikan untuk revisi
                                </Button>
                            )}
                            {can.verify && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onClick={(event) => choose('verifikasi', event.currentTarget)}
                                >
                                    Verifikasi pengukuran
                                </Button>
                            )}
                            {can.ratify && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onClick={(event) => choose('sahkan', event.currentTarget)}
                                >
                                    Sahkan kinerja resmi
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                {/* Decision Modal Dialog */}
                {decision && (
                    <DecisionDialog
                        key={`${pengukuran.id}-${decision}`}
                        pengukuran={pengukuran}
                        decision={decision}
                        onClose={closeDecision}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
