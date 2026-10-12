import { useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Undo2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { ReviewProps } from './review-types';
import { rencanaAksiStatusPerhitungan } from './types';

type Decision = 'verifikasi' | 'kembalikan';
const konteksTidakLengkap = 'Konteks versi belum lengkap';

export default function Review(props: ReviewProps) {
    return <ReviewForm key={`${props.rencanaAksi.id}:${props.rencanaAksi.versi_pengajuan.id}`} {...props} />;
}

function ReviewForm({ rencanaAksi }: ReviewProps) {
    const versi = rencanaAksi.versi_pengajuan;
    const formatTanggal = useFormatTanggal();
    const formatNilai = useFormatNilai();
    const { satuan, desimal_tampilan: desimal } = rencanaAksi.indikator;
    const [decision, setDecision] = useState<Decision | null>(null);
    const [message, setMessage] = useState('');
    const errorSummary = useRef<HTMLDivElement>(null);
    const recovery = useAuthRecovery();
    const form = useForm({ expected_versi: rencanaAksi.expected_versi, versi_pengajuan_id: versi.id, alasan: '' });
    const blocked = Boolean(message || recovery.recovery);

    const buka = (aksi: Decision): void => {
        if (form.processing || !rencanaAksi.can[aksi]) return;
        form.setData({ ...form.data, expected_versi: rencanaAksi.expected_versi, versi_pengajuan_id: versi.id });
        setDecision(aksi);
    };
    const tutup = (): void => { if (!form.processing) setDecision(null); };
    const muatUlang = (): void => {
        router.get(`/rencana-aksi/${rencanaAksi.id}/review`, {}, { preserveState: false, preserveScroll: true });
    };
    const kirim = (aksi: Decision): void => {
        if (form.processing || blocked || !rencanaAksi.can[aksi]) return;
        const path = `/rencana-aksi/${rencanaAksi.id}/${aksi}`;
        const status = aksi === 'verifikasi' ? 'diverifikasi' : 'dikembalikan';
        form.transform((data) => aksi === 'kembalikan' ? data : {
            expected_versi: data.expected_versi,
            versi_pengajuan_id: data.versi_pengajuan_id,
        });
        form.post(path, {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.expected_versi || errors.versi_pengajuan_id) setMessage('Muat ulang untuk meninjau versi dan status terbaru.');
                requestAnimationFrame(() => {
                    if (errors.alasan) errorSummary.current?.parentElement?.querySelector<HTMLTextAreaElement>('textarea')?.focus();
                    else errorSummary.current?.focus();
                });
            },
            onSuccess: (page) => {
                const updated = page.props.rencanaAksi;
                if (page.component === 'RencanaAksi/Review' && updated && typeof updated === 'object'
                    && 'id' in updated && updated.id === rencanaAksi.id
                    && 'status_alur' in updated && updated.status_alur === status
                    && 'expected_versi' in updated && typeof updated.expected_versi === 'number'
                    && updated.expected_versi > form.data.expected_versi) {
                    setDecision(null);
                    form.reset('alasan');
                } else {
                    setMessage('Hasil tindakan belum terkonfirmasi. Periksa status terbaru sebelum mencoba kembali.');
                }
            },
            onCancel: () => setMessage('Permintaan dibatalkan. Hasil tindakan belum diketahui; periksa status terbaru sebelum mencoba kembali.'),
            onNetworkError: () => {
                setMessage('Koneksi terputus. Hasil tindakan belum diketahui; periksa status terbaru sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) return false;
                setMessage(response.status === 403
                    ? 'Izin tindakan ditolak. Muat ulang untuk melihat akses terbaru.'
                    : response.status === 409
                        ? 'Versi atau status rencana aksi sudah berubah. Muat ulang sebelum mencoba kembali.'
                        : response.status === 429
                            ? 'Terlalu banyak permintaan. Muat ulang untuk memeriksa status sebelum mencoba kembali.'
                            : 'Hasil tindakan belum dapat dipastikan. Periksa status terbaru sebelum mencoba kembali.');
                return false;
            },
        });
    };
    const notice = (
        <div ref={errorSummary} tabIndex={-1} className="space-y-3 focus:outline-none">
            {Object.entries(form.errors).filter(([field]) => field !== 'alasan' || decision !== 'kembalikan').map(([field, error]) => <p key={field} role="alert" className="text-sm text-danger">{error}</p>)}
            {form.processing && <p role="status" className="text-sm text-muted">Tindakan sedang diproses…</p>}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {message && !recovery.recovery && <p role="alert" className="text-sm text-danger">{message}</p>}
            {blocked && <Button type="button" variant="outline" disabled={form.processing} onClick={muatUlang}>Periksa status terbaru</Button>}
        </div>
    );

    return (
        <AuthenticatedLayout title={`Review ${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`} breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: 'Review', href: '/rencana-aksi/review' }, { label: `Versi ${versi.nomor}` }]}>
            <Head title={`Review Rencana Aksi ${rencanaAksi.indikator.kode}`} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href="/rencana-aksi/review" className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20"><ArrowLeft className="h-4 w-4" aria-hidden="true" />Kembali ke daftar review</Link>
                    <div className="flex flex-wrap gap-2">
                        {rencanaAksi.can.kembalikan && <Button type="button" variant="outline" size="sm" disabled={form.processing || blocked} onClick={() => buka('kembalikan')}><Undo2 className="h-3.5 w-3.5" aria-hidden="true" />Kembalikan untuk revisi</Button>}
                        {rencanaAksi.can.verifikasi && <Button type="button" size="sm" disabled={form.processing || blocked} onClick={() => buka('verifikasi')}><CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />Verifikasi</Button>}
                    </div>
                </div>
                {blocked && decision === null && <Card><CardContent>{notice}</CardContent></Card>}
                <Card>
                    <CardHeader>
                        <div className="min-w-0 flex-1 space-y-1.5">
                            <CardTitle className="break-words">{rencanaAksi.indikator.kode} · {rencanaAksi.indikator.nama}</CardTitle>
                            <p className="text-xs text-muted">Tahun {rencanaAksi.tahun} · Pengajuan versi {versi.nomor}</p>
                        </div>
                        <Badge status={rencanaAksi.status_alur} />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted">Menampilkan substansi pengajuan versi {versi.nomor} yang dibekukan. Perubahan substansi harus melalui pengembalian dan pengajuan versi baru.</p>
                        <dl className="grid gap-5 text-sm sm:grid-cols-2">
                            <div><dt className="text-muted">Unit pada versi</dt><dd className="mt-1 font-medium">{rencanaAksi.unit_kerja?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Penanggung jawab pada versi</dt><dd className="mt-1 font-medium">{rencanaAksi.pic?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Pengaju historis</dt><dd className="mt-1 font-medium">{versi.pengaju?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Waktu pengajuan</dt><dd className="mt-1 font-medium">{formatTanggal(versi.diajukan_at, { withTime: true })} · Jalur {versi.jalur_pengajuan === 'pic' ? 'PIC' : 'Perencanaan'}</dd></div>
                        </dl>
                        <div><h3 className="text-sm font-semibold">Uraian rencana aksi</h3><p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6">{rencanaAksi.uraian ?? 'Uraian tidak tersedia pada versi ini.'}</p></div>
                        {rencanaAksi.alasan_deviasi_pk && <div><h3 className="text-sm font-semibold">Alasan deviasi target PK</h3><p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6">{rencanaAksi.alasan_deviasi_pk}</p></div>}
                        {(['verifikasi', 'kembalikan'] as const).map((aksi) => rencanaAksi.businessErrors[aksi].length > 0 && (
                            <section key={aksi} aria-label={`Alasan ${aksi} tidak tersedia`} className="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
                                <h3 className="font-semibold text-warning-dark">{aksi === 'verifikasi' ? 'Verifikasi tidak tersedia' : 'Pengembalian tidak tersedia'}</h3>
                                <ul className="mt-2 list-disc space-y-1 pl-5">{rencanaAksi.businessErrors[aksi].map((error) => <li key={error}>{error}</li>)}</ul>
                            </section>
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Target per periode pada versi {versi.nomor}</CardTitle></CardHeader>
                    <Table className="table-fixed [&_th]:whitespace-normal [&_th]:break-words [&_td]:break-words">
                        <TableHeader><TableRow><TableHead>Periode</TableHead><TableHead>Target tersimpan</TableHead><TableHead>Komponen</TableHead></TableRow></TableHeader>
                        <TableBody>
                            {rencanaAksi.target_periode.length === 0 ? <TableRow><TableCell colSpan={3} className="py-8 text-center text-muted">Target periode tidak tersedia pada versi ini.</TableCell></TableRow> : rencanaAksi.target_periode.map((periode) => (
                                <TableRow key={periode.periode_id}>
                                    <TableCell>{periode.periode_nama || konteksTidakLengkap}{periode.keterangan && <p className="mt-1 whitespace-pre-wrap break-words text-sm text-muted">{periode.keterangan}</p>}</TableCell>
                                    <TableCell className="tabular-nums"><span>{periode.nilai === null ? 'Belum tersedia' : `${formatNilai(periode.nilai, desimal)} ${satuan}`}</span>{periode.status_perhitungan && <p className="mt-1 text-xs text-muted">{rencanaAksiStatusPerhitungan[periode.status_perhitungan] ?? periode.status_perhitungan}</p>}</TableCell>
                                    <TableCell>{periode.komponen.length === 0 ? '—' : <ul className="space-y-1">{periode.komponen.map((komponen) => <li key={komponen.komponen_id}><span>{komponen.kode} · {komponen.label || konteksTidakLengkap}: </span><span className="tabular-nums">{komponen.nilai === null ? 'Belum tersedia' : formatNilai(komponen.nilai, desimal)}</span>{komponen.keterangan && <p className="mt-1 whitespace-pre-wrap break-words text-sm text-muted">{komponen.keterangan}</p>}</li>)}</ul>}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
                {rencanaAksi.can.evidence && <Card>
                    <CardHeader><CardTitle>Bukti dukung pada versi {versi.nomor}</CardTitle></CardHeader>
                    <CardContent>{rencanaAksi.bukti_dukungs.length === 0 ? <p className="text-sm text-muted">Tidak ada bukti dukung pada versi ini.</p> : <ul className="space-y-3">{rencanaAksi.bukti_dukungs.map((bukti) => <BerkasLampiranItem key={bukti.id} berkas={{ ...bukti, mime: null, ukuran_bytes: null }} meta={bukti.mode === 'file' && !bukti.download_url ? 'Berkas ini sudah tidak berlaku pada rencana aksi; unduhan tidak tersedia.' : undefined} />)}</ul>}</CardContent>
                </Card>}
                <Card>
                    <CardHeader><CardTitle>Histori pengajuan</CardTitle></CardHeader>
                    <CardContent>
                        <ul className="divide-y divide-border">{rencanaAksi.histori.map((item) => <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                            <div><p className="font-semibold">Versi {item.nomor}</p><p className="mt-1 text-xs text-muted">{formatTanggal(item.diajukan_at, { withTime: true })} · Jalur {item.jalur_pengajuan === 'pic' ? 'PIC' : 'Perencanaan'}</p></div>
                            <Link href={`/rencana-aksi/${rencanaAksi.id}/review?versi=${item.id}`} aria-current={item.id === versi.id ? 'page' : undefined} className="rounded px-3 py-2 font-semibold text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary">{item.id === versi.id ? 'Versi ditampilkan' : 'Lihat versi'}</Link>
                        </li>)}</ul>
                    </CardContent>
                    <Pagination pagination={rencanaAksi.pagination} label="versi pengajuan" />
                </Card>
            </div>
            <Modal
                isOpen={decision === 'verifikasi'}
                onClose={tutup}
                title="Verifikasi rencana aksi"
                description={`Konfirmasi hasil pemeriksaan versi pengajuan ${versi.nomor}.`}
                size="lg"
                showCloseButton={!form.processing}
                footer={
                    <>
                        <Button type="button" variant="outline" disabled={form.processing} onClick={tutup}>Batal</Button>
                        <Button type="submit" form="verifikasi-rencana-aksi" isLoading={form.processing} disabled={blocked || !rencanaAksi.can.verifikasi}>Konfirmasi verifikasi</Button>
                    </>
                }
            >
                <form id="verifikasi-rencana-aksi" onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); kirim('verifikasi'); }} className="space-y-4" aria-busy={form.processing}>
                    <p className="text-sm text-muted">Verifikasi dicatat dalam audit dengan alasan baku server. Substansi versi pengajuan tetap dibekukan.</p>
                    {notice}
                </form>
            </Modal>
            <AuditReasonModal open={decision === 'kembalikan'} title="Kembalikan rencana aksi" description={`Tuliskan perbaikan versi ${versi.nomor} yang harus ditindaklanjuti penanggung jawab.`} reason={form.data.alasan} error={form.errors.alasan} busy={form.processing} notice={notice} submitDisabled={blocked || !rencanaAksi.can.kembalikan || form.data.alasan.trim() === ''} confirmLabel="Konfirmasi pengembalian" onReasonChange={(alasan) => form.setData('alasan', alasan)} onClose={tutup} onConfirm={() => kirim('kembalikan')} />
        </AuthenticatedLayout>
    );
}
