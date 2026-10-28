import { useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, BadgeCheck, CheckCircle2, Info, RotateCcw, Undo2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableCellLabel, TableHead, TableHeader, TableRow, tableCellResponsive } from '@/Components/Table';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { formatRupiah } from '@/lib/rupiah';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { ReviewProps, StatusReview } from './review-types';
import { statusKegiatanConfig } from '@/types/kegiatan';
import { rencanaAksiStatusPerhitungan } from './types';

type Decision = 'verifikasi' | 'kembalikan' | 'sahkan' | 'buka_kembali';
const konteksTidakLengkap = 'Konteks versi belum lengkap';
// Sama dengan filter status DaftarRencanaAksi agar RA yang baru diproses tetap terlihat di daftar.
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
    const hrefDaftar = statusDaftar.includes(rencanaAksi.status_alur) ? `/rencana-aksi?status=${rencanaAksi.status_alur}` : '/rencana-aksi';

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

    // Alasan pengesahan hanya relevan pada tahap diverifikasi; buka-kembali hanya relevan pada tahap disahkan.
    const aksiTidakTersedia = (['verifikasi', 'kembalikan', 'sahkan', 'buka_kembali'] as const)
        .filter((aksi) => (aksi !== 'sahkan' || rencanaAksi.status_alur === 'diverifikasi') && (aksi !== 'buka_kembali' || rencanaAksi.status_alur === 'disahkan'))
        .filter((aksi) => rencanaAksi.businessErrors[aksi].length > 0);
    // Kolom matriks dari komponen yang muncul pada versi beku, urut kemunculan; indikator manual tidak punya kolom komponen.
    const kolomKomponen = rencanaAksi.target_periode
        .flatMap((periode) => periode.komponen)
        .filter((komponen, index, semua) => semua.findIndex((lain) => lain.komponen_id === komponen.komponen_id) === index);
    const adaKomponenKlaim = (rencanaAksi.klaim ?? []).some((item) => item.komponen !== null);
    const labelJalur = (jalur: 'pic' | 'perencanaan'): string => (jalur === 'pic' ? 'PIC' : 'Perencanaan');
    const kelasPemberitahuan = 'flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm';

    return (
        <AuthenticatedLayout title={`Reviu ${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`} breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: 'Reviu', href: hrefDaftar }, { label: `Versi ${versi.nomor}` }]}>
            <Head title={`Reviu Rencana Aksi ${rencanaAksi.indikator.kode}`} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={hrefDaftar} className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20"><ArrowLeft className="h-4 w-4" aria-hidden="true" />Kembali ke daftar rencana aksi</Link>
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
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-5 text-sm lg:grid-cols-4">
                            <div><dt className="text-muted">Unit pada versi</dt><dd className="mt-1 font-medium">{rencanaAksi.unit_kerja?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Penanggung jawab pada versi</dt><dd className="mt-1 font-medium">{rencanaAksi.pic?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Pengaju historis</dt><dd className="mt-1 font-medium">{versi.pengaju?.nama ?? konteksTidakLengkap}</dd></div>
                            <div><dt className="text-muted">Waktu pengajuan</dt><dd className="mt-1 font-medium">{formatTanggal(versi.diajukan_at, { withTime: true })} · Jalur {labelJalur(versi.jalur_pengajuan)}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Satu area pemberitahuan dengan gaya yang sama seperti halaman detail rencana aksi. */}
                <section aria-label="Pemberitahuan" className="space-y-2">
                    <div className={`${kelasPemberitahuan} border-info/30 bg-info/10 text-info-dark`}>
                        <Info className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        <p>Menampilkan substansi pengajuan versi {versi.nomor} yang dibekukan. Perubahan substansi harus melalui pengembalian dan pengajuan versi baru.</p>
                    </div>
                    {rencanaAksi.pengesahan && (
                        <div className={`${kelasPemberitahuan} border-success/30 bg-success/10 text-success-dark`}>
                            <BadgeCheck className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <div>
                                <p className="font-semibold">
                                    Disahkan oleh {rencanaAksi.pengesahan.oleh}
                                    {rencanaAksi.pengesahan.pada ? ` pada ${formatTanggal(rencanaAksi.pengesahan.pada, { withTime: true })}` : ''}
                                </p>
                                <p className="mt-1">Versi pengajuan {versi.nomor} menjadi dokumen resmi rencana aksi.</p>
                                {rencanaAksi.pengesahan.self_approval && (
                                    <p className="mt-1 font-semibold">Disahkan sendiri oleh pengaju melalui jalur Perencanaan (self-approval).</p>
                                )}
                            </div>
                        </div>
                    )}
                    {rencanaAksi.dibuka_kembali && rencanaAksi.status_alur === 'dikembalikan' && (
                        <div className={`${kelasPemberitahuan} border-info/30 bg-info/10 text-info-dark`}>
                            <RotateCcw className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <div>
                                <p className="font-semibold">Dibuka kembali setelah pengesahan</p>
                                <p className="mt-1">Pengajuan pengukuran indikator ini ditahan sampai versi baru disahkan. Versi yang disahkan sebelumnya tetap tersimpan dan dapat dibaca sebagai histori.</p>
                            </div>
                        </div>
                    )}
                    {aksiTidakTersedia.map((aksi) => (
                        <div key={aksi} className={`${kelasPemberitahuan} border-warning/40 bg-warning/10 text-warning-dark`}>
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <div>
                                <p className="font-semibold">{judulTidakTersedia[aksi]}</p>
                                <ul className="mt-1 list-disc space-y-1 pl-5">{rencanaAksi.businessErrors[aksi].map((error) => <li key={error}>{error}</li>)}</ul>
                            </div>
                        </div>
                    ))}
                </section>

                <Card role="region" aria-labelledby="reviu-target">
                    <CardHeader><CardTitle id="reviu-target">Target per periode</CardTitle></CardHeader>
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead className="whitespace-normal">Periode</TableHead>
                                {kolomKomponen.map((kolom) => <TableHead key={kolom.komponen_id} className="whitespace-normal normal-case tracking-normal">{kolom.kode} · {kolom.label || konteksTidakLengkap}</TableHead>)}
                                <TableHead className="whitespace-normal md:text-right">Target tersimpan</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {rencanaAksi.target_periode.length === 0 ? (
                                <TableRow className="block md:table-row"><TableCell colSpan={2 + kolomKomponen.length} className="block px-6 py-8 text-center text-muted md:table-cell">Target periode tidak tersedia pada versi ini.</TableCell></TableRow>
                            ) : rencanaAksi.target_periode.map((periode) => (
                                <TableRow key={periode.periode_id} className="grid min-w-0 grid-cols-2 gap-3 px-6 py-4 md:table-row">
                                    <TableCell className={`col-span-2 ${tableCellResponsive}`}>
                                        <p className="text-sm font-medium text-ink">{periode.periode_nama || konteksTidakLengkap}</p>
                                        {periode.keterangan && <p className="mt-1 whitespace-pre-wrap break-words text-xs text-muted">{periode.keterangan}</p>}
                                    </TableCell>
                                    {kolomKomponen.map((kolom) => {
                                        const sel = periode.komponen.find((komponen) => komponen.komponen_id === kolom.komponen_id);
                                        return (
                                            <TableCell key={kolom.komponen_id} className={`${tableCellResponsive} tabular-nums`}>
                                                <TableCellLabel>{kolom.kode} · {kolom.label || konteksTidakLengkap}</TableCellLabel>
                                                <span>{sel === undefined ? '—' : sel.nilai === null ? 'Belum tersedia' : formatNilai(sel.nilai, desimal)}</span>
                                                {sel?.keterangan && <p className="mt-1 whitespace-pre-wrap break-words text-xs text-muted">{sel.keterangan}</p>}
                                            </TableCell>
                                        );
                                    })}
                                    <TableCell className={`col-span-2 ${tableCellResponsive} tabular-nums md:text-right`}>
                                        <TableCellLabel>Target tersimpan</TableCellLabel>
                                        <span className="font-semibold">{periode.status_perhitungan === 'tidak_ditargetkan' ? 'Tidak ditargetkan' : periode.nilai === null ? 'Belum tersedia' : `${formatNilai(periode.nilai, desimal)} ${satuan}`}</span>
                                        {periode.status_perhitungan && periode.status_perhitungan !== 'tidak_ditargetkan' && <p className="mt-1 text-xs text-muted">{rencanaAksiStatusPerhitungan[periode.status_perhitungan] ?? periode.status_perhitungan}</p>}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Card role="region" aria-labelledby="reviu-uraian">
                    <CardHeader><CardTitle id="reviu-uraian">Uraian dan alasan deviasi</CardTitle></CardHeader>
                    <CardContent>
                        <dl className="space-y-4 text-sm">
                            <div><dt className="text-muted">Uraian rencana aksi</dt><dd className="mt-1 whitespace-pre-wrap break-words leading-6 text-ink">{rencanaAksi.uraian ?? 'Uraian tidak tersedia pada versi ini.'}</dd></div>
                            {rencanaAksi.alasan_deviasi_pk && <div><dt className="text-muted">Alasan deviasi target PK</dt><dd className="mt-1 whitespace-pre-wrap break-words leading-6 text-ink">{rencanaAksi.alasan_deviasi_pk}</dd></div>}
                        </dl>
                    </CardContent>
                </Card>

                {rencanaAksi.can.kegiatan && <Card role="region" aria-labelledby="reviu-kegiatan">
                    <CardHeader><CardTitle id="reviu-kegiatan">Kegiatan yang diklaim</CardTitle></CardHeader>
                    {rencanaAksi.klaim === null ? <CardContent><p className="text-sm text-muted">Versi ini dibentuk sebelum daftar kegiatan ikut dibekukan.</p></CardContent>
                        : rencanaAksi.klaim.length === 0 ? <CardContent><p className="text-sm text-muted">Tidak ada kegiatan yang diklaim pada versi ini.</p></CardContent>
                            : (
                                <Table wrapperClassName="overflow-x-visible" className="block md:table">
                                    <TableHeader className="max-md:sr-only">
                                        <TableRow>
                                            <TableHead>Periode</TableHead>
                                            <TableHead className="whitespace-normal">Kegiatan</TableHead>
                                            {adaKomponenKlaim && <TableHead>Komponen</TableHead>}
                                            <TableHead>Arah dampak</TableHead>
                                            <TableHead className="md:text-right">Anggaran</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="block md:table-row-group">
                                        {rencanaAksi.klaim.map((item) => {
                                            const status = item.status ? statusKegiatanConfig[item.status] ?? { label: item.status, variant: 'muted' as const } : null;
                                            return (
                                                <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 px-6 py-4 md:table-row">
                                                    <TableCell className={`col-span-2 ${tableCellResponsive} md:whitespace-nowrap`}>
                                                        <TableCellLabel>Periode</TableCellLabel>
                                                        <span>{item.periode_nama}</span>
                                                    </TableCell>
                                                    <TableCell className={`col-span-2 ${tableCellResponsive}`}>
                                                        <p className="break-words text-sm font-medium text-ink">{item.nama}</p>
                                                        {item.catatan && <p className="mt-0.5 break-words text-xs text-muted">{item.catatan}</p>}
                                                    </TableCell>
                                                    {adaKomponenKlaim && (
                                                        <TableCell className={`col-span-2 ${tableCellResponsive}`}>
                                                            <TableCellLabel>Komponen</TableCellLabel>
                                                            <span>{item.komponen ?? 'Tanpa komponen tertentu'}</span>
                                                        </TableCell>
                                                    )}
                                                    <TableCell className={tableCellResponsive}>
                                                        <TableCellLabel>Arah</TableCellLabel>
                                                        {item.arah_dampak === null ? <span>—</span> : <Badge variant={item.arah_dampak === 'menambah' ? 'success' : 'warning'}>{item.arah_dampak === 'menambah' ? 'Menambah' : 'Mengurangi'}</Badge>}
                                                    </TableCell>
                                                    <TableCell className={`${tableCellResponsive} md:whitespace-nowrap md:text-right`}>
                                                        <TableCellLabel>Anggaran</TableCellLabel>
                                                        <span className={item.anggaran === null ? 'text-muted' : 'font-medium'}>{formatRupiah(item.anggaran)}</span>
                                                    </TableCell>
                                                    <TableCell className={tableCellResponsive}>
                                                        <TableCellLabel>Status</TableCellLabel>
                                                        {status ? <Badge variant={status.variant}>{status.label}</Badge> : <span>—</span>}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            )}
                    {rencanaAksi.anggaran_kegiatan && (
                        <p className="border-t border-border px-6 py-4 text-sm text-ink">Total anggaran saat pengajuan: <span className="font-semibold">{formatRupiah(rencanaAksi.anggaran_kegiatan.total)}</span>
                            {rencanaAksi.anggaran_kegiatan.belum_diisi > 0 && ` (${rencanaAksi.anggaran_kegiatan.belum_diisi} kegiatan belum diisi)`}</p>
                    )}
                </Card>}

                {rencanaAksi.can.evidence && <Card role="region" aria-labelledby="reviu-bukti">
                    <CardHeader><CardTitle id="reviu-bukti">Bukti dukung</CardTitle></CardHeader>
                    <CardContent>{rencanaAksi.bukti_dukungs.length === 0 ? <p className="text-sm text-muted">Tidak ada bukti dukung pada versi ini.</p> : <ul className="space-y-3">{rencanaAksi.bukti_dukungs.map((bukti) => <BerkasLampiranItem key={bukti.id} berkas={{ ...bukti, mime: null, ukuran_bytes: null }} meta={bukti.mode === 'file' && !bukti.download_url ? 'Berkas ini sudah tidak berlaku pada rencana aksi; unduhan tidak tersedia.' : undefined} />)}</ul>}</CardContent>
                </Card>}

                <Card role="region" aria-labelledby="reviu-histori">
                    <CardHeader><CardTitle id="reviu-histori">Histori pengajuan</CardTitle></CardHeader>
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead>Versi</TableHead>
                                <TableHead>Diajukan</TableHead>
                                <TableHead>Jalur</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {rencanaAksi.histori.map((item) => (
                                <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 px-6 py-4 md:table-row">
                                    <TableCell className={tableCellResponsive}><span className="text-sm font-semibold text-ink">Versi {item.nomor}</span></TableCell>
                                    <TableCell className={`col-span-2 ${tableCellResponsive} md:col-span-1`}>
                                        <TableCellLabel>Diajukan</TableCellLabel>
                                        <span>{formatTanggal(item.diajukan_at, { withTime: true })}</span>
                                    </TableCell>
                                    <TableCell className={tableCellResponsive}>
                                        <TableCellLabel>Jalur</TableCellLabel>
                                        <span>{labelJalur(item.jalur_pengajuan)}</span>
                                    </TableCell>
                                    <TableCell className={`row-start-1 col-start-2 text-right ${tableCellResponsive}`}>
                                        <Link href={`/rencana-aksi/${rencanaAksi.id}/review?versi=${item.id}`} aria-current={item.id === versi.id ? 'page' : undefined} className="rounded px-3 py-2 text-xs font-semibold text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary">{item.id === versi.id ? 'Versi ditampilkan' : 'Lihat versi'}</Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
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
            <AuditReasonModal open={decision === 'kembalikan'} title="Kembalikan rencana aksi" showIcon={false} reason={form.data.alasan} error={form.errors.alasan} busy={form.processing} notice={notice} submitDisabled={blocked || !rencanaAksi.can.kembalikan || form.data.alasan.trim() === ''} confirmLabel="Konfirmasi pengembalian" onReasonChange={(alasan) => form.setData('alasan', alasan)} onClose={tutup} onConfirm={() => kirim('kembalikan')} />
            <AuditReasonModal open={decision === 'buka_kembali'} title="Buka kembali rencana aksi" showIcon={false} reason={form.data.alasan} error={form.errors.alasan} busy={form.processing} notice={notice} submitDisabled={blocked || !rencanaAksi.can.buka_kembali || form.data.alasan.trim() === ''} confirmLabel="Konfirmasi buka kembali" placeholder="Contoh: koreksi target periode berdasarkan SK resmi…" onReasonChange={(alasan) => form.setData('alasan', alasan)} onClose={tutup} onConfirm={() => kirim('buka_kembali')} />
        </AuthenticatedLayout>
    );
}
