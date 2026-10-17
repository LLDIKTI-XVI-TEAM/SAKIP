import { useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, CheckCircle2, RotateCcw, Undo2 } from 'lucide-react';
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
import type { ReviewProps, StatusReview } from './review-types';
import { statusKegiatanConfig } from '@/types/kegiatan';
import { rencanaAksiStatusPerhitungan } from './types';

type Decision = 'verifikasi' | 'kembalikan' | 'sahkan' | 'buka_kembali';
const konteksTidakLengkap = 'Konteks versi belum lengkap';
// Sama dengan filter status DaftarReviewRencanaAksi agar RA yang baru diproses tetap terlihat.
const statusDaftar: readonly StatusReview[] = ['diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'];
const statusHasil: Record<Decision, string> = { verifikasi: 'diverifikasi', kembalikan: 'dikembalikan', sahkan: 'disahkan', buka_kembali: 'dikembalikan' };
const judulTidakTersedia: Record<Decision, string> = {
    verifikasi: 'Verifikasi tidak tersedia',
    kembalikan: 'Pengembalian tidak tersedia',
    sahkan: 'Pengesahan tidak tersedia',
    buka_kembali: 'Buka kembali tidak tersedia',
};

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
    const hrefDaftar = statusDaftar.includes(rencanaAksi.status_alur) ? `/rencana-aksi/review?status=${rencanaAksi.status_alur}` : '/rencana-aksi/review';

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
        const path = `/rencana-aksi/${rencanaAksi.id}/${aksi === 'buka_kembali' ? 'buka-kembali' : aksi}`;
        const status = statusHasil[aksi];
        form.transform((data) => aksi === 'kembalikan' || aksi === 'buka_kembali' ? data : {
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
            {Object.entries(form.errors).filter(([field]) => field !== 'alasan' || (decision !== 'kembalikan' && decision !== 'buka_kembali')).map(([field, error]) => <p key={field} role="alert" className="text-sm text-danger">{error}</p>)}
            {form.processing && <p role="status" className="text-sm text-muted">Tindakan sedang diproses…</p>}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {message && !recovery.recovery && <p role="alert" className="text-sm text-danger">{message}</p>}
            {blocked && <Button type="button" variant="outline" disabled={form.processing} onClick={muatUlang}>Periksa status terbaru</Button>}
        </div>
    );

    return (
        <AuthenticatedLayout title={`Review ${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`} breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: 'Review', href: hrefDaftar }, { label: `Versi ${versi.nomor}` }]}>
            <Head title={`Review Rencana Aksi ${rencanaAksi.indikator.kode}`} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={hrefDaftar} className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20"><ArrowLeft className="h-4 w-4" aria-hidden="true" />Kembali ke daftar review</Link>
                    <div className="flex flex-wrap gap-2">
                        {rencanaAksi.can.kembalikan && <Button type="button" variant="outline" size="sm" disabled={form.processing || blocked} onClick={() => buka('kembalikan')}><Undo2 className="h-3.5 w-3.5" aria-hidden="true" />Kembalikan untuk revisi</Button>}
                        {rencanaAksi.can.verifikasi && <Button type="button" size="sm" disabled={form.processing || blocked} onClick={() => buka('verifikasi')}><CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />Verifikasi</Button>}
                        {rencanaAksi.can.sahkan && <Button type="button" size="sm" disabled={form.processing || blocked} onClick={() => buka('sahkan')}><BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />Sahkan</Button>}
                        {rencanaAksi.can.buka_kembali && <Button type="button" variant="outline" size="sm" disabled={form.processing || blocked} onClick={() => buka('buka_kembali')}><RotateCcw className="h-3.5 w-3.5" aria-hidden="true" />Buka kembali</Button>}
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
                        {rencanaAksi.pengesahan && (
                            <section aria-label="Pengesahan" className="rounded-lg border border-success/30 bg-success/10 p-4 text-sm text-success-dark">
                                <h3 className="font-semibold">
                                    Disahkan oleh {rencanaAksi.pengesahan.oleh}
                                    {rencanaAksi.pengesahan.pada ? ` pada ${formatTanggal(rencanaAksi.pengesahan.pada, { withTime: true })}` : ''}
                                </h3>
                                <p className="mt-1">Versi pengajuan {versi.nomor} menjadi dokumen resmi rencana aksi.</p>
                                {rencanaAksi.pengesahan.self_approval && (
                                    <p className="mt-1 font-semibold">Disahkan sendiri oleh pengaju melalui jalur Perencanaan (self-approval).</p>
                                )}
                            </section>
                        )}
                        {rencanaAksi.dibuka_kembali && rencanaAksi.status_alur === 'dikembalikan' && (
                            <section aria-label="Dibuka kembali" className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm text-info-dark">
                                <h3 className="font-semibold">Dibuka kembali setelah pengesahan</h3>
                                <p className="mt-1">Pengajuan pengukuran indikator ini ditahan sampai versi baru disahkan. Versi yang disahkan sebelumnya tetap tersimpan dan dapat dibaca sebagai histori.</p>
                            </section>
                        )}
                        <dl className="grid gap-5 text-sm sm:grid-cols-2">
                            <div><dt className="text-muted">Unit pada versi</dt><dd className="mt-1 font-medium">{rencanaAksi.unit_kerja?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Penanggung jawab pada versi</dt><dd className="mt-1 font-medium">{rencanaAksi.pic?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Pengaju historis</dt><dd className="mt-1 font-medium">{versi.pengaju?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Waktu pengajuan</dt><dd className="mt-1 font-medium">{formatTanggal(versi.diajukan_at, { withTime: true })} · Jalur {versi.jalur_pengajuan === 'pic' ? 'PIC' : 'Perencanaan'}</dd></div>
                        </dl>
                        <div><h3 className="text-sm font-semibold">Uraian rencana aksi</h3><p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6">{rencanaAksi.uraian ?? 'Uraian tidak tersedia pada versi ini.'}</p></div>
                        {rencanaAksi.alasan_deviasi_pk && <div><h3 className="text-sm font-semibold">Alasan deviasi target PK</h3><p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6">{rencanaAksi.alasan_deviasi_pk}</p></div>}
                        {/* Alasan pengesahan hanya relevan pada tahap diverifikasi; buka-kembali hanya relevan pada tahap disahkan. */}
                        {(['verifikasi', 'kembalikan', 'sahkan', 'buka_kembali'] as const).filter((aksi) => (aksi !== 'sahkan' || rencanaAksi.status_alur === 'diverifikasi') && (aksi !== 'buka_kembali' || rencanaAksi.status_alur === 'disahkan')).map((aksi) => rencanaAksi.businessErrors[aksi].length > 0 && (
                            <section key={aksi} aria-label={`Alasan ${aksi} tidak tersedia`} className="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
                                <h3 className="font-semibold text-warning-dark">{judulTidakTersedia[aksi]}</h3>
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
                                    <TableCell className="tabular-nums"><span>{periode.status_perhitungan === 'tidak_ditargetkan' ? 'Tidak ditargetkan' : periode.nilai === null ? 'Belum tersedia' : `${formatNilai(periode.nilai, desimal)} ${satuan}`}</span>{periode.status_perhitungan && periode.status_perhitungan !== 'tidak_ditargetkan' && <p className="mt-1 text-xs text-muted">{rencanaAksiStatusPerhitungan[periode.status_perhitungan] ?? periode.status_perhitungan}</p>}</TableCell>
                                    <TableCell>{periode.komponen.length === 0 ? '—' : <ul className="space-y-1">{periode.komponen.map((komponen) => <li key={komponen.komponen_id}><span>{komponen.kode} · {komponen.label || konteksTidakLengkap}: </span><span className="tabular-nums">{komponen.nilai === null ? 'Belum tersedia' : formatNilai(komponen.nilai, desimal)}</span>{komponen.keterangan && <p className="mt-1 whitespace-pre-wrap break-words text-sm text-muted">{komponen.keterangan}</p>}</li>)}</ul>}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
                {rencanaAksi.can.kegiatan && <Card>
                    <CardHeader><CardTitle>Kegiatan yang diklaim pada versi {versi.nomor}</CardTitle></CardHeader>
                    <CardContent>
                        {rencanaAksi.klaim === null ? <p className="text-sm text-muted">Versi ini dibentuk sebelum daftar kegiatan ikut dibekukan.</p>
                            : rencanaAksi.klaim.length === 0 ? <p className="text-sm text-muted">Tidak ada kegiatan yang diklaim pada versi ini.</p>
                                : <ul className="divide-y divide-border">{rencanaAksi.klaim.map((item) => <li key={item.id} className="space-y-1 py-2">
                                    <p className="break-words text-sm font-semibold text-ink">{item.nama}</p>
                                    <p className="text-xs text-muted">{item.periode_nama} · {item.komponen ?? 'Tanpa komponen tertentu'} · {item.arah_dampak === 'mengurangi' ? 'Mengurangi' : 'Menambah'}{item.status ? ` · ${statusKegiatanConfig[item.status]?.label ?? item.status}` : ''}</p>
                                    {item.catatan && <p className="break-words text-xs text-muted">{item.catatan}</p>}
                                </li>)}</ul>}
                    </CardContent>
                </Card>}
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
            <Modal
                isOpen={decision === 'sahkan'}
                onClose={tutup}
                title="Sahkan rencana aksi"
                description={`Konfirmasi pengesahan versi pengajuan ${versi.nomor}.`}
                size="lg"
                showCloseButton={!form.processing}
                footer={
                    <>
                        <Button type="button" variant="outline" disabled={form.processing} onClick={tutup}>Batal</Button>
                        <Button type="submit" form="sahkan-rencana-aksi" isLoading={form.processing} disabled={blocked || !rencanaAksi.can.sahkan}>Konfirmasi pengesahan</Button>
                    </>
                }
            >
                <form id="sahkan-rencana-aksi" onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); kirim('sahkan'); }} className="space-y-4" aria-busy={form.processing}>
                    <p className="text-sm text-muted">Pengesahan menyimpan versi pengajuan {versi.nomor} sebagai dokumen resmi rencana aksi dan dicatat dalam audit dengan alasan baku server.</p>
                    {notice}
                </form>
            </Modal>
            <AuditReasonModal open={decision === 'kembalikan'} title="Kembalikan rencana aksi" description={`Tuliskan perbaikan versi ${versi.nomor} yang harus ditindaklanjuti penanggung jawab.`} reason={form.data.alasan} error={form.errors.alasan} busy={form.processing} notice={notice} submitDisabled={blocked || !rencanaAksi.can.kembalikan || form.data.alasan.trim() === ''} confirmLabel="Konfirmasi pengembalian" onReasonChange={(alasan) => form.setData('alasan', alasan)} onClose={tutup} onConfirm={() => kirim('kembalikan')} />
            <AuditReasonModal open={decision === 'buka_kembali'} title="Buka kembali rencana aksi" description={`Tuliskan dasar koreksi pembukaan kembali versi ${versi.nomor} yang telah disahkan agar penanggung jawab dapat memperbaiki.`} reason={form.data.alasan} error={form.errors.alasan} busy={form.processing} notice={notice} submitDisabled={blocked || !rencanaAksi.can.buka_kembali || form.data.alasan.trim() === ''} confirmLabel="Konfirmasi buka kembali" placeholder="Contoh: koreksi target periode berdasarkan SK resmi…" onReasonChange={(alasan) => form.setData('alasan', alasan)} onClose={tutup} onConfirm={() => kirim('buka_kembali')} />
        </AuthenticatedLayout>
    );
}
