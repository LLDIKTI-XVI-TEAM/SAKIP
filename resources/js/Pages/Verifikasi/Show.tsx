import { useEffect, useState, useRef, type ReactNode } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RentangPeriode } from '@/Components/Periode/RentangPeriode';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { StatusPerhitunganBadge } from '@/Components/Pengukuran/StatusPerhitunganBadge';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';
import type { HistoriVersiPengukuran, Pengukuran } from '@/Pages/Pengukuran/types';
import EvidenceList from '@/Pages/Pengukuran/EvidenceList';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import { tipePerhitunganLabel } from '@/lib/indikator';
import DecisionDialog, { type ReviewDecision } from './DecisionDialog';
import ClaimedActivities from './ClaimedActivities';

const labelJalur = (jalur: 'pic' | 'perencanaan' | null): string =>
    jalur === null ? '—' : jalur === 'pic' ? 'PIC' : 'Perencanaan';

function HistoriField({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-muted">{label}</dt>
            <dd className="mt-0.5 break-words text-xs font-semibold text-ink">{children}</dd>
        </div>
    );
}

/** Dialog alasan wajib untuk membuka kembali pengukuran yang telah disahkan. */
function BukaKembaliDialog({ pengukuran, onClose }: { pengukuran: Pengukuran; onClose: () => void }) {
    const recovery = useAuthRecovery();
    const [pesan, setPesan] = useState('');
    const [perluMuatUlang, setPerluMuatUlang] = useState(false);
    const form = useForm<{ versi: number; alasan: string }>({ versi: pengukuran.versi, alasan: '' });

    // 409/422 versi dan validasi field dikirim server; normalisasi sekali agar render hanya menerima teks.
    const galat = form.errors as Partial<Record<string, string | string[]>>;
    const teksGalat = (nilai: string | string[] | undefined): string | undefined =>
        Array.isArray(nilai) ? nilai.join(' ') : nilai;
    const galatAlasan = teksGalat(galat.alasan);
    const galatVersi = teksGalat(galat.versi);
    const alasanKosong = form.data.alasan.trim() === '';
    const terkunci = form.processing || perluMuatUlang || Boolean(recovery.recovery);
    const nomorResmi = pengukuran.snapshot?.nomor_pengajuan ?? null;
    const path = `/verifikasi/${pengukuran.id}/buka-kembali`;
    const muatUlang = () => router.reload({ onSuccess: () => onClose() });

    const kirim = () => {
        if (terkunci || alasanKosong) return;
        setPesan('');
        form.post(path, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onCancel: () => setPesan('Permintaan dibatalkan. Hasil buka kembali belum diketahui; muat ulang halaman sebelum mencoba lagi.'),
            onError: (errors) => {
                // Versi yang dikirim sudah tidak berlaku: data di layar harus dibaca ulang sebelum mengulang tindakan.
                if (errors.versi) setPerluMuatUlang(true);
            },
            onNetworkError: () => {
                setPesan('Koneksi terputus. Hasil buka kembali belum diketahui; muat ulang halaman sebelum mencoba lagi.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) return false;
                if (response.status === 409 || response.status === 422) {
                    setPerluMuatUlang(true);
                    setPesan('Data telah berubah atau tindakan belum dapat diproses. Muat ulang halaman sebelum mencoba lagi.');
                    return false;
                }
                setPesan(response.status === 403
                    ? 'Izin buka kembali tidak tersedia atau telah dicabut.'
                    : response.status === 429
                        ? 'Terlalu banyak permintaan. Muat ulang halaman sebelum mencoba lagi.'
                        : 'Hasil buka kembali belum dapat dipastikan. Muat ulang halaman sebelum mencoba lagi.');
                return false;
            },
        });
    };

    const notice = (
        <div className="space-y-2">
            {galatVersi && (
                <div role="alert" className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-ink">
                    <p className="font-medium">{galatVersi}</p>
                    <Button type="button" size="sm" variant="outline" onClick={muatUlang} disabled={form.processing}>Muat ulang halaman</Button>
                </div>
            )}
            {pesan && (
                <div role="alert" className="space-y-2 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                    <p>{pesan}</p>
                    {perluMuatUlang && !galatVersi && (
                        <Button type="button" size="sm" variant="outline" onClick={muatUlang} disabled={form.processing}>Muat ulang halaman</Button>
                    )}
                </div>
            )}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
        </div>
    );

    return (
        <AuditReasonModal
            open
            title="Buka kembali pengukuran"
            description={nomorResmi === null
                ? 'Pengukuran yang telah disahkan akan dibuka kembali untuk dikoreksi. Versi resmi tetap tersimpan sebagai histori.'
                : `Versi resmi nomor ${nomorResmi} akan dibuka kembali untuk dikoreksi. Versi resmi tetap tersimpan dan dapat dibaca sebagai histori.`}
            reason={form.data.alasan}
            error={galatAlasan}
            busy={form.processing}
            submitDisabled={terkunci || alasanKosong}
            reasonRequired
            confirmLabel="Buka Kembali"
            placeholder="Contoh: koreksi nilai berdasarkan berita acara reviu…"
            notice={notice}
            onReasonChange={(alasan) => form.setData('alasan', alasan)}
            onClose={() => { if (!form.processing) onClose(); }}
            onConfirm={kirim}
        />
    );
}

export default function VerifikasiShow({ pengukuran }: { pengukuran: Pengukuran }) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const labelUnit = useLabelUnit();
    const { can, peringatan_verifikasi: peringatan } = pengukuran;
    const businessErrors = pengukuran.businessErrors ?? { verifikasi: [], sahkan: [], kembalikan: [], buka_kembali: [] };
    const alasanGuard: string[] = [...new Set([
        ...(!can.verify ? (businessErrors.verifikasi ?? []) : []),
        ...(!can.ratify ? (businessErrors.sahkan ?? []) : []),
        ...(!can.return ? (businessErrors.kembalikan ?? []) : []),
        ...(!can.reopen ? (businessErrors.buka_kembali ?? []) : []),
    ])];
    const panelKeputusanTerlihat: boolean = can.verify || can.ratify || can.return || can.reopen === true;
    const judulGuard = pengukuran.status === 'disahkan' && !panelKeputusanTerlihat
        ? 'Tindakan koreksi tidak tersedia'
        : panelKeputusanTerlihat ? 'Sebagian tindakan tidak tersedia' : 'Tindakan reviu tidak tersedia';
    const [decision, setDecision] = useState<ReviewDecision | null>(null);
    const [reopenTerbuka, setReopenTerbuka] = useState(false);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const indikator = pengukuran.penugasan_indikator.indikator_kinerja;
    const historiVersi: HistoriVersiPengukuran[] = pengukuran.histori_versi ?? [];
    const choose = (action: ReviewDecision, button: HTMLButtonElement) => { trigger.current = button; setDecision(action); };
    const bukaReopen = (button: HTMLButtonElement) => { trigger.current = button; setReopenTerbuka(true); };
    const closeDecision = () => { setDecision(null); };
    useEffect(() => {
        // Tunggu dialog dilepas agar fokus tidak ditolak oleh halaman yang masih inert.
        if (decision === null && !reopenTerbuka && trigger.current?.isConnected) trigger.current.focus();
    }, [decision, reopenTerbuka]);

    return (
        <AuthenticatedLayout
            title="Reviu & Verifikasi Kinerja"
            breadcrumbs={[{ label: 'Verifikasi Kinerja', href: '/verifikasi' }, { label: indikator.kode }]}
            headerActions={
                <Link
                    href="/verifikasi"
                    aria-label="Kembali ke antrean verifikasi"
                    className="ml-auto inline-flex h-9 shrink-0 translate-y-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali
                </Link>
            }
        >
            <Head title={`Reviu ${indikator.kode}`} />
            <div className="space-y-5">
                {pengukuran.status === 'disahkan' && (
                    <div className="rounded-lg border border-success/30 bg-success/10 p-4 text-sm">
                        <p className="font-semibold">Pengukuran telah disahkan</p>
                        <p className="mt-1">Snapshot resmi tersimpan. Waktu pengesahan: {formatTanggal(pengukuran.snapshot?.disahkan_pada, { withTime: true })}.</p>
                    </div>
                )}

                <Card>
                    <CardHeader className="items-start">
                        <div className="min-w-0 flex-1">
                            <CardTitle className="break-words">{indikator.kode} · {indikator.nama}</CardTitle>
                            <p className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
                                <span>Pengajuan ke-{pengukuran.nomor_pengajuan}</span>
                                {pengukuran.jalur_pengajuan && (
                                    <span>Jalur pengajuan: <strong className="capitalize text-ink">{pengukuran.jalur_pengajuan}</strong></span>
                                )}
                            </p>
                        </div>
                        <Badge status={pengukuran.status} />
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div className="min-w-0">
                                <dt className="text-muted">{labelUnit}</dt>
                                <dd className="mt-1 break-words font-medium">{pengukuran.penugasan_indikator.unit_kerja.nama}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="text-muted">PIC</dt>
                                <dd className="mt-1 break-words font-medium">{pengukuran.penugasan_indikator.pic?.nama || '—'}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="text-muted">Periode</dt>
                                <dd className="mt-1 font-medium">{pengukuran.periode_jadwal.nama_periode} {pengukuran.periode_jadwal.tahun}<RentangPeriode tanggal={pengukuran.periode_jadwal.tanggal} tahun={pengukuran.periode_jadwal.tahun} /></dd>
                            </div>
                        </dl>
                        {indikator.definisi_operasional && (
                            <p className="whitespace-pre-wrap break-words text-sm leading-relaxed text-muted">{indikator.definisi_operasional}</p>
                        )}
                        {(pengukuran.self_approval || pengukuran.reviu_terlambat) && (
                            <div className="flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                {pengukuran.self_approval && <span className="font-medium text-info-dark">Tindakan oleh pengaju tercatat sesuai kewenangan Perencanaan</span>}
                                {pengukuran.reviu_terlambat && <span className="font-medium text-warning-dark">Reviu terlambat</span>}
                            </div>
                        )}
                        <dl className="grid gap-x-6 gap-y-5 border-t border-border pt-5 sm:grid-cols-2 xl:grid-cols-4">
                            <div className="min-w-0">
                                <dt className="text-xs text-muted">Target PK tahunan</dt>
                                <dd className="mt-1 break-words text-lg font-semibold tabular-nums">{pengukuran.target_pk == null ? 'Belum tersedia' : `${formatNilai(pengukuran.target_pk, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="text-xs text-muted">Target periode RA · {pengukuran.periode_jadwal.nama_periode}</dt>
                                <dd className="mt-1 break-words text-lg font-semibold tabular-nums">{pengukuran.tanpa_target_periode ? 'Tanpa target periode' : pengukuran.target === null ? 'Belum tersedia' : `${formatNilai(pengukuran.target, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="text-xs text-muted">Nilai pengukuran</dt>
                                <dd className="mt-1 break-words text-lg font-semibold tabular-nums">{pengukuran.nilai === null ? '—' : `${formatNilai(pengukuran.nilai, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="text-xs text-muted">Hasil perhitungan</dt>
                                <dd className="mt-1 break-words text-lg font-semibold"><StatusPerhitunganBadge status={pengukuran.status_perhitungan} /></dd>
                                <dd className="mt-1 text-xs text-muted">{tipePerhitunganLabel[indikator.tipe_perhitungan]}</dd>
                            </div>
                        </dl>
                        {pengukuran.selisih_target_pk !== undefined && (
                            <div className="border-t border-border pt-4 text-sm">
                                <p className="text-muted">Selisih terhadap target PK</p>
                                <p className="mt-1 break-words text-lg font-semibold tabular-nums">{pengukuran.selisih_target_pk === null ? 'Belum dapat dihitung' : `${formatNilai(pengukuran.selisih_target_pk, indikator.desimal_tampilan)} ${indikator.satuan}`}</p>
                                <p className="mt-1 text-xs leading-relaxed text-muted">Realisasi dikurangi target PK; arti tanda mengikuti arah indikator.</p>
                            </div>
                        )}
                        {pengukuran.komponen.length > 0 && (
                            <section className="space-y-3 border-t border-border pt-5" aria-labelledby="komponen-pengajuan-title">
                                <h2 id="komponen-pengajuan-title" className="text-sm font-semibold">Komponen pengajuan</h2>
                                <Table aria-labelledby="komponen-pengajuan-title" wrapperClassName="rounded-lg border border-border">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Komponen</TableHead>
                                            <TableHead>Peran / Bobot</TableHead>
                                            <TableHead className="text-right">Nilai</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pengukuran.komponen.map((item) => (
                                            <TableRow key={item.komponen_id}>
                                                <TableHead scope="row" className="whitespace-normal font-normal normal-case tracking-normal">{item.kode} · {item.label}</TableHead>
                                                <TableCell>{item.peran}{item.bobot === null ? '' : ` / ${item.bobot}`}</TableCell>
                                                <TableCell className="text-right tabular-nums">{item.nilai === null ? 'Belum diisi' : formatNilai(item.nilai, indikator.desimal_tampilan)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </section>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Narasi pengukuran</CardTitle></CardHeader>
                    <CardContent className="space-y-5 text-sm">
                        <dl className="grid gap-x-6 gap-y-5 md:grid-cols-2">
                            {([['progres_kegiatan', 'Progres kegiatan'], ['kendala_masalah', 'Kendala/masalah'], ['strategi_tindak_lanjut', 'Tindak lanjut/strategi penyelesaian']] as const).map(([field, label]) => (
                                <div key={field} className="min-w-0">
                                    <dt className="font-semibold text-ink">{label}</dt>
                                    <dd className="mt-1 whitespace-pre-wrap break-words leading-relaxed text-ink">{pengukuran[field] || 'Tidak diisi.'}</dd>
                                </div>
                            ))}
                        </dl>
                        <dl className="grid gap-x-6 gap-y-5 border-t border-border pt-5 md:grid-cols-2">
                            <div className="min-w-0">
                                <dt className="font-semibold text-ink">Rekomendasi periode sebelumnya</dt>
                                <dd className="mt-1 whitespace-pre-wrap break-words leading-relaxed text-ink">{pengukuran.rekomendasi_sebelumnya?.isi ?? 'Tidak ada rekomendasi pada versi pengajuan ini.'}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="font-semibold text-ink">Tindak lanjut rekomendasi</dt>
                                <dd className="mt-1 whitespace-pre-wrap break-words leading-relaxed text-ink">{pengukuran.tindak_lanjut_rekomendasi || 'Tidak diisi.'}</dd>
                            </div>
                        </dl>
                        <div className="border-t border-border pt-4">
                            <h2 className="font-semibold">Catatan pengukuran</h2>
                            <p className="mt-1 whitespace-pre-wrap break-words leading-relaxed">{pengukuran.catatan || 'Tidak ada catatan.'}</p>
                        </div>
                        {pengukuran.alasan_tidak_dapat_dihitung && (
                            <div>
                                <h2 className="font-medium">Alasan hasil tidak dapat dihitung</h2>
                                <p className="mt-1 whitespace-pre-wrap break-words leading-relaxed text-muted">{pengukuran.alasan_tidak_dapat_dihitung}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Bukti dukung pengajuan</CardTitle></CardHeader>
                    <CardContent><EvidenceList pengukuran={pengukuran} /></CardContent>
                </Card>
                <ClaimedActivities pengukuran={pengukuran} />

                <Card aria-labelledby="histori-versi-title">
                    <CardHeader>
                        <div className="min-w-0">
                            <CardTitle id="histori-versi-title">Histori versi pengukuran</CardTitle>
                            <p className="mt-1 text-xs leading-relaxed text-muted">Metadata versi pengajuan dan status capaian yang menempel pada versi. Hanya dapat dibaca.</p>
                        </div>
                        <Badge variant="muted" size="sm">{historiVersi.length} versi</Badge>
                    </CardHeader>

                    {historiVersi.length === 0 ? (
                        <p className="px-6 py-10 text-center text-sm text-muted">Belum ada versi pengajuan.</p>
                    ) : (
                        <>
                            <Table wrapperClassName="hidden md:block" aria-label="Histori versi pengukuran">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Versi</TableHead>
                                        <TableHead>Diajukan</TableHead>
                                        <TableHead>Disahkan</TableHead>
                                        <TableHead>Jalur</TableHead>
                                        <TableHead>Status capaian</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {historiVersi.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="whitespace-nowrap">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-semibold">Versi {item.nomor}</span>
                                                    {item.resmi && <Badge variant="primary" size="sm">Resmi</Badge>}
                                                </div>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">{formatTanggal(item.diajukan_at, { withTime: true })}</TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {item.disahkan_at === null
                                                    ? <span className="text-muted">Belum disahkan</span>
                                                    : formatTanggal(item.disahkan_at, { withTime: true })}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">{labelJalur(item.jalur_pengajuan)}</TableCell>
                                            <TableCell>
                                                {item.status_capaian === null ? (
                                                    <span className="text-muted">Belum ditetapkan</span>
                                                ) : (
                                                    <div className="min-w-0">
                                                        <StatusCapaianBadge kode={item.status_capaian.status} size="sm" />
                                                        {(item.status_capaian.penetap || item.status_capaian.ditetapkan_pada) && (
                                                            <p className="mt-1 break-words text-muted">
                                                                {item.status_capaian.penetap?.nama ?? 'Penetap tidak tercatat'}
                                                                {item.status_capaian.ditetapkan_pada
                                                                    ? ` · ${formatTanggal(item.status_capaian.ditetapkan_pada, { withTime: true })}`
                                                                    : ''}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            <ul className="divide-y divide-border md:hidden">
                                {historiVersi.map((item) => (
                                    <li key={item.id} className="space-y-3 p-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-sm font-semibold text-ink">Versi {item.nomor}</p>
                                            {item.resmi && <Badge variant="primary" size="sm">Resmi</Badge>}
                                        </div>
                                        <dl className="grid grid-cols-2 gap-3">
                                            <HistoriField label="Diajukan">{formatTanggal(item.diajukan_at, { withTime: true })}</HistoriField>
                                            <HistoriField label="Disahkan">
                                                {item.disahkan_at === null
                                                    ? <span className="font-normal text-muted">Belum disahkan</span>
                                                    : formatTanggal(item.disahkan_at, { withTime: true })}
                                            </HistoriField>
                                            <HistoriField label="Jalur">{labelJalur(item.jalur_pengajuan)}</HistoriField>
                                            <HistoriField label="Status capaian">
                                                {item.status_capaian === null ? (
                                                    <span className="font-normal text-muted">Belum ditetapkan</span>
                                                ) : (
                                                    <>
                                                        <StatusCapaianBadge kode={item.status_capaian.status} size="sm" />
                                                        {(item.status_capaian.penetap || item.status_capaian.ditetapkan_pada) && (
                                                            <span className="mt-1 block break-words text-xs font-normal text-muted">
                                                                {item.status_capaian.penetap?.nama ?? 'Penetap tidak tercatat'}
                                                                {item.status_capaian.ditetapkan_pada
                                                                    ? ` · ${formatTanggal(item.status_capaian.ditetapkan_pada, { withTime: true })}`
                                                                    : ''}
                                                            </span>
                                                        )}
                                                    </>
                                                )}
                                            </HistoriField>
                                        </dl>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Card>

                {peringatan && (peringatan.realisasi_sama || peringatan.narasi_sama || peringatan.keterangan) && (
                    <section aria-labelledby="peringatan-verifikasi-title" className={`rounded-lg border p-4 text-sm ${peringatan.realisasi_sama || peringatan.narasi_sama ? 'border-warning/30 bg-warning/10' : 'border-border bg-soft'}`}>
                        <h2 id="peringatan-verifikasi-title" className="font-semibold">{peringatan.realisasi_sama || peringatan.narasi_sama ? 'Data sama dengan periode sebelumnya' : 'Perbandingan antarperiode'}</h2>
                        {peringatan.periode_pembanding && <p className="mt-1 text-xs">Pembanding resmi: {peringatan.periode_pembanding}.</p>}
                        {peringatan.realisasi_sama && <p className="mt-2">Realisasi sama dengan periode pembanding.</p>}
                        {peringatan.narasi_sama && <p className="mt-2">Ketiga narasi utama sama dengan periode pembanding.</p>}
                        {peringatan.keterangan && <p className="mt-2">{peringatan.keterangan}</p>}
                        {(peringatan.realisasi_sama || peringatan.narasi_sama) && <p className="mt-2 leading-relaxed">Periksa kewajaran data dan bukti dukung. Peringatan ini tidak menghalangi verifikasi.</p>}
                    </section>
                )}

                {panelKeputusanTerlihat && (
                    <Card>
                        <CardContent className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div className="min-w-0">
                                <h2 className="text-sm font-semibold">Keputusan pengukuran</h2>
                                <p className="mt-1 text-xs leading-relaxed text-muted">Tindakan mengikuti status dan kewenangan Anda saat ini.</p>
                            </div>
                            <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
                                {can.return && (
                                    <Button type="button" variant="danger" className="w-full bg-danger text-white hover:bg-danger/90 focus:ring-danger sm:w-auto" onClick={(event) => choose('kembalikan', event.currentTarget)}>
                                        Kembalikan untuk revisi
                                    </Button>
                                )}
                                {can.verify && (
                                    <Button type="button" className="w-full bg-primary text-white hover:bg-primary/90 focus:ring-primary sm:w-auto" onClick={(event) => choose('verifikasi', event.currentTarget)}>
                                        Verifikasi pengukuran
                                    </Button>
                                )}
                                {can.ratify && (
                                    <Button type="button" className="w-full bg-primary text-white hover:bg-primary/90 focus:ring-primary sm:w-auto" onClick={(event) => choose('sahkan', event.currentTarget)}>
                                        Sahkan kinerja resmi
                                    </Button>
                                )}
                                {can.reopen && (
                                    <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={(event) => bukaReopen(event.currentTarget)}>
                                        <RotateCcw className="h-4 w-4" aria-hidden="true" />
                                        Buka Kembali
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
                {alasanGuard.length > 0 && (
                    <div role="status" className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm">
                        <p className="font-semibold text-warning-dark">{judulGuard}</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5">{alasanGuard.map((alasan: string) => <li key={alasan}>{alasan}</li>)}</ul>
                    </div>
                )}
                {decision && <DecisionDialog key={`${pengukuran.id}-${decision}`} pengukuran={pengukuran} decision={decision} onClose={closeDecision} />}
                {reopenTerbuka && <BukaKembaliDialog key={`${pengukuran.id}-${pengukuran.versi}`} pengukuran={pengukuran} onClose={() => setReopenTerbuka(false)} />}
            </div>
        </AuthenticatedLayout>
    );
}
