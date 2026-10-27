import { Fragment, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Download } from 'lucide-react';
import { Head, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge, type BadgeVariant } from '@/Components/Badge';
import { SelisihTargetPk } from '@/Components/Pengukuran/SelisihTargetPk';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Pagination } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { peranKomponenLabel } from '@/lib/indikator';
import { formatRupiah } from '@/lib/rupiah';
import { statusKegiatanConfig } from '@/types/kegiatan';
import type { BarisRekap, RekapProps } from '@/types/laporan';

const tidakTercatat = 'Tidak tercatat pada versi ini';
const status: Record<BarisRekap['status_laporan'], { label: string; variant: BadgeVariant }> = {
    belum_disahkan: { label: 'Belum disahkan', variant: 'muted' },
    belum_dapat_diukur: { label: 'Belum dapat diukur', variant: 'info' },
    tanpa_target_periode: { label: 'Tanpa target periode', variant: 'info' },
    tidak_dapat_dihitung: { label: 'Tidak dapat dihitung', variant: 'warning' },
    disahkan: { label: 'Disahkan', variant: 'success' },
    konteks_tidak_lengkap: { label: 'Konteks versi tidak lengkap', variant: 'warning' },
};
const labelNarasi = {
    progres_kegiatan: 'Progres kegiatan', kendala_masalah: 'Kendala / masalah',
    strategi_tindak_lanjut: 'Strategi tindak lanjut', tindak_lanjut_rekomendasi: 'Tindak lanjut rekomendasi',
};

function Data({ label, children }: { label: string; children: ReactNode }) {
    return <div><dt className="text-xs text-muted">{label}</dt><dd className="mt-1 break-words text-sm text-ink">{children}</dd></div>;
}

function IsiBaris({ row }: { row: BarisRekap }) {
    const format = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const nilai = (value: string | null, unit = true) => value === null ? '—' : `${format(value, row.indikator.desimal_tampilan)}${unit && row.indikator.satuan ? ` ${row.indikator.satuan}` : ''}`;
    const sah = row.versi !== null;
    const unavailable = sah ? tidakTercatat : 'Belum disahkan';
    return <>
        <TableCell className="block min-w-0 align-top lg:table-cell lg:w-2/5">
            <p className="text-xs font-semibold text-primary">{row.indikator.kode ?? tidakTercatat} · {row.periode.nama}</p>
            <h2 className="mt-1 break-words text-sm font-semibold leading-relaxed">{row.indikator.nama ?? tidakTercatat}</h2>
            <div className="mt-3"><Badge className="max-w-full whitespace-normal" variant={status[row.status_laporan].variant}>{status[row.status_laporan].label}</Badge></div>
            <dl className="mt-4 space-y-3">
                <Data label="Sasaran">{row.sasaran ? `${row.sasaran.kode} · ${row.sasaran.deskripsi}` : tidakTercatat}</Data>
                <Data label="Unit">{row.unit.nama ?? tidakTercatat}</Data>
                <Data label={sah ? 'PIC saat pengajuan' : 'PIC saat ini'}>{row.pic?.nama ?? (sah ? tidakTercatat : 'Belum ditetapkan')}</Data>
                {sah && row.pic_saat_ini?.id !== row.pic?.id && <Data label="PIC saat ini">{row.pic_saat_ini?.nama ?? 'Belum ditetapkan'}</Data>}
                <Data label="Cara hitung">{row.indikator.tipe_perhitungan === 'manual' ? 'Nilai manual' : row.indikator.tipe_perhitungan === 'rasio_persen' ? 'Rasio persen berbobot' : 'Penjumlahan berbobot'}</Data>
            </dl>
            {row.versi && <p className="mt-4 text-xs text-muted">Versi pengajuan {row.versi.nomor} · disahkan {formatTanggal(row.versi.disahkan_pada)}</p>}
        </TableCell>
        <TableCell className="block min-w-0 border-t border-border align-top lg:table-cell lg:w-[30%] lg:border-t-0">
            <h3 className="mb-3 text-sm font-semibold lg:sr-only">Target</h3>
            <dl className="space-y-4 tabular-nums">
                <Data label="Baseline">{nilai(row.baseline)}</Data>
                <Data label="Target PK tahunan">{nilai(row.target_pk)}</Data>
                <Data label="Target periode">{row.tanpa_target_periode ? 'Tanpa target periode' : row.target_periode === null ? unavailable : nilai(row.target_periode)}</Data>
            </dl>
            {row.indikator.definisi && <details className="mt-4 text-xs"><summary className="cursor-pointer rounded py-1 text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25">Definisi indikator</summary><p className="mt-2 whitespace-pre-wrap break-words leading-relaxed">{row.indikator.definisi}</p></details>}
        </TableCell>
        <TableCell className="block min-w-0 border-t border-border align-top lg:table-cell lg:w-[30%] lg:border-t-0">
            <h3 className="mb-3 text-sm font-semibold lg:sr-only">Realisasi & capaian</h3>
            <dl className="space-y-4 tabular-nums">
                <Data label="Realisasi">{sah ? row.nilai === null ? status[row.status_laporan].label : nilai(row.nilai) : 'Belum disahkan'}</Data>
                <Data label="Capaian terhadap target periode">{!sah ? 'Belum disahkan' : row.capaian_persen === null ? '—' : `${format(row.capaian_persen, 2)}%`}</Data>
                <Data label="Selisih terhadap target PK">{sah ? <SelisihTargetPk selisih={row.selisih_target_pk} arah={row.indikator.arah} satuan={row.indikator.satuan ?? ''} desimal={row.indikator.desimal_tampilan} /> : 'Belum disahkan'}</Data>
            </dl>
            {row.keterangan_capaian && <p className="mt-2 text-xs text-muted">{row.keterangan_capaian}</p>}
            {row.alasan_tidak_dapat_dihitung && <p className="mt-3 whitespace-pre-wrap break-words text-sm">{row.alasan_tidak_dapat_dihitung}</p>}
            {row.komponen.length > 0 && <details className="mt-4 text-xs"><summary className="cursor-pointer rounded py-1 text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25">Komponen ({row.komponen.length})</summary>
                <dl className="mt-2 space-y-3">{row.komponen.map((c) => <div key={c.komponen_id} className="border-t border-border pt-2">
                    <dt className="break-words font-semibold">{c.kode} · {c.label}</dt>
                    <dd className="mt-1 break-words text-muted">{peranKomponenLabel[c.peran] ?? c.peran} · bobot {format(c.bobot, c.bobot.split('.')[1]?.replace(/0+$/, '').length ?? 0)}</dd>
                    <dd className="mt-1">Target: {nilai(c.target, false)}</dd><dd>Realisasi: {sah ? nilai(c.realisasi, false) : 'Belum disahkan'}</dd>
                </div>)}</dl>
            </details>}
        </TableCell>
    </>;
}

function RincianBaris({ row }: { row: BarisRekap }) {
    const sah = row.versi !== null;
    return <div className="grid gap-6 pt-5 xl:grid-cols-3">
        <section className="min-w-0">
            <h3 className="mb-4 text-sm font-semibold">Analisis pengukuran</h3>
            {!row.narasi ? <p className="text-sm text-muted">Belum disahkan</p> : <dl className="space-y-4">{(Object.keys(labelNarasi) as (keyof typeof labelNarasi)[]).map((key) => <Data key={key} label={labelNarasi[key]}><span className="whitespace-pre-wrap">{row.narasi?.[key] ?? tidakTercatat}</span></Data>)}</dl>}
            {row.rekomendasi_sebelumnya?.isi && <details className="mt-4 text-xs"><summary className="cursor-pointer rounded py-1 text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25">Rekomendasi yang ditindaklanjuti</summary><p className="mt-2 whitespace-pre-wrap break-words leading-relaxed">{row.rekomendasi_sebelumnya.isi}</p></details>}
        </section>
        <section className="min-w-0 border-t border-border pt-5 xl:border-l xl:border-t-0 xl:pl-6 xl:pt-0">
            <h3 className="mb-4 text-sm font-semibold">Kegiatan & anggaran</h3>
            {!sah ? <p className="text-sm text-muted">Belum disahkan</p> : !row.can.lihat_kegiatan ? <p className="text-sm text-muted">Detail kegiatan dibatasi sesuai akses unit.</p> : row.kegiatan === null ? <p className="text-sm text-muted">{tidakTercatat}</p> : row.kegiatan.length === 0 ? <p className="text-sm text-muted">Tidak ada kegiatan yang diklaim pada versi ini.</p> : <ul className="space-y-4">{row.kegiatan.map((k, index) => <li key={k.id ?? index} className="space-y-2 border-b border-border pb-4 last:border-0">
                <p className="break-words text-sm font-semibold">{k.nama ?? tidakTercatat}</p>
                <Badge className="max-w-full whitespace-normal break-words" variant={statusKegiatanConfig[k.status ?? '']?.variant ?? 'muted'}>{statusKegiatanConfig[k.status ?? '']?.label ?? k.status ?? tidakTercatat}</Badge>
                <p className="break-words text-sm tabular-nums">{!k.anggaran_tercatat ? tidakTercatat : formatRupiah(k.anggaran)}</p>
                {k.kegiatan_bersama === true ? <Badge className="max-w-full whitespace-normal" variant="info">Kegiatan bersama</Badge> : k.kegiatan_bersama === null ? <p className="text-xs text-muted">Kegiatan bersama: {tidakTercatat.toLowerCase()}</p> : null}
                {(k.uraian_pelaksanaan || k.kendala || k.strategi_tindaklanjut) && <details className="text-xs"><summary className="cursor-pointer rounded py-1 text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25">Catatan kegiatan</summary><dl className="mt-2 space-y-2">{k.uraian_pelaksanaan && <Data label="Pelaksanaan">{k.uraian_pelaksanaan}</Data>}{k.kendala && <Data label="Kendala">{k.kendala}</Data>}{k.strategi_tindaklanjut && <Data label="Strategi">{k.strategi_tindaklanjut}</Data>}</dl></details>}
            </li>)}</ul>}
        </section>
        <section className="min-w-0 border-t border-border pt-5 xl:border-l xl:border-t-0 xl:pl-6 xl:pt-0">
            <h3 className="mb-4 text-sm font-semibold">Rekomendasi & bukti</h3>
            <dl className="space-y-4">
                <Data label="Rekomendasi Pimpinan periode ini">{row.rekomendasi ? <><p className="whitespace-pre-wrap">{row.rekomendasi.isi}</p>{row.rekomendasi.cadangan && <p className="mt-2 text-xs text-muted">Persetujuan cadangan</p>}</> : 'Belum ada rekomendasi disetujui'}</Data>
                <Data label="Status capaian">{row.status_capaian}</Data>
                <Data label="Bukti wajib pengukuran">{!row.bukti ? 'Belum disahkan' : row.bukti.status === 'tidak_tercatat' ? tidakTercatat : row.bukti.total === 0 ? 'Tidak ada persyaratan wajib pada versi ini' : `${row.bukti.terpenuhi} dari ${row.bukti.total} terpenuhi`}</Data>
            </dl>
            {row.bukti?.histori_tidak_dapat_dipenuhi && <div className="mt-3"><Badge className="max-w-full whitespace-normal" variant="warning">Histori: tidak dapat dipenuhi</Badge></div>}
        </section>
    </div>;
}

export default function Rekap({ filters, jadwal, pilihan_jadwal, pilihan_periode, baris, pagination, can, ada_data_ekspor }: RekapProps) {
    const form = useForm({ ...filters });
    const [failure, setFailure] = useState('');
    const [downloadFailure, setDownloadFailure] = useState('');
    const [downloading, setDownloading] = useState(false);
    const downloadRequest = useRef<AbortController | null>(null);
    const filterChanged = form.data.jadwal_id !== filters.jadwal_id || form.data.periode_id !== filters.periode_id;
    useEffect(() => () => downloadRequest.current?.abort(), []);

    const download = async () => {
        if (downloadRequest.current || form.processing || filterChanged || !can.ekspor || !ada_data_ekspor) return;
        const controller = new AbortController();
        downloadRequest.current = controller;
        setDownloading(true);
        setDownloadFailure('');
        try {
            // Unduhan adalah respons berkas; jangan kirim sebagai kunjungan atau props Inertia.
            const response = await fetch(`/laporan/rekap/ekspor?${new URLSearchParams(filters)}`, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal,
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error('Sesi Anda berakhir. Muat ulang halaman dan masuk kembali.');
            }
            if (response.status === 403) {
                throw new Error('Anda tidak memiliki izin untuk mengekspor laporan ini. Muat ulang halaman untuk memeriksa akses Anda.');
            }
            if (response.status === 422) {
                const payload: unknown = await response.json().catch(() => null);
                let message = 'Filter ekspor tidak valid. Terapkan kembali filter lalu coba lagi.';
                if (payload && typeof payload === 'object' && 'errors' in payload && payload.errors && typeof payload.errors === 'object') {
                    for (const messages of Object.values(payload.errors)) {
                        if (Array.isArray(messages) && typeof messages[0] === 'string') { message = messages[0]; break; }
                    }
                }
                throw new Error(message);
            }
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                throw new Error('Berkas Excel belum dapat disiapkan. Coba lagi atau hubungi pengelola.');
            }
            const blob = await response.blob();
            if (controller.signal.aborted) return;
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? `rekap-kinerja-${jadwal?.tahun ?? ''}.xlsx`;
            document.body.append(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (error) {
            if (!controller.signal.aborted) {
                setDownloadFailure(error instanceof TypeError ? 'Unduhan gagal. Periksa koneksi lalu coba lagi.'
                    : error instanceof Error ? error.message : 'Unduhan gagal. Coba lagi.');
            }
        } finally {
            if (!controller.signal.aborted) setDownloading(false);
            downloadRequest.current = null;
        }
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || downloading) return;
        setFailure('');
        form.get('/laporan/rekap', {
            preserveState: false, preserveScroll: true,
            onNetworkError: () => { setFailure('Filter belum dapat diterapkan. Periksa koneksi lalu coba lagi.'); return false; },
            onHttpException: () => { setFailure('Rekap belum dapat dimuat. Muat ulang halaman dan periksa akses Anda.'); return false; },
        });
    };

    return <AuthenticatedLayout title="Rekapitulasi Kinerja" breadcrumbs={[{ label: 'Rekapitulasi Kinerja' }]}>
        <Head title="Rekapitulasi Kinerja" />
        <div className="min-w-0 space-y-5">
            <Card className="p-4 sm:p-5">
                <form onSubmit={submit} aria-label="Filter rekapitulasi" aria-busy={form.processing} className="space-y-4">
                    <div className="grid items-start gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <Select label="Tahun / jadwal" labelClassName="sr-only" value={form.data.jadwal_id} disabled={form.processing || downloading || pilihan_jadwal.length === 0} error={form.errors.jadwal_id}
                            options={pilihan_jadwal.length ? [{ value: '', label: 'Pilih tahun / jadwal' }, ...pilihan_jadwal.map((j) => ({ value: j.id, label: j.label }))] : [{ value: '', label: 'Belum ada jadwal' }]}
                            onChange={(event) => { form.setData({ jadwal_id: event.target.value, periode_id: '' }); }} />
                        <Select label="Periode" labelClassName="sr-only" value={form.data.periode_id} disabled={form.processing || downloading || form.data.jadwal_id !== filters.jadwal_id} error={form.errors.periode_id}
                            options={[{ value: '', label: 'Semua periode efektif' }, ...pilihan_periode.map((p) => ({ value: p.id, label: p.nama }))]}
                            helperText={form.data.jadwal_id !== filters.jadwal_id ? 'Terapkan jadwal untuk memuat pilihan periodenya.' : undefined}
                            onChange={(event) => form.setData('periode_id', event.target.value)} />
                        <Button type="submit" className="h-[42px] justify-self-start" isLoading={form.processing} disabled={downloading || !form.data.jadwal_id}>Terapkan</Button>
                    </div>
                    {failure && <p role="alert" className="text-sm text-danger">{failure}</p>}
                </form>
            </Card>
            <Card>
                <div className="space-y-3 border-b border-border px-4 py-4 sm:px-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="min-w-0 break-words text-base font-semibold">{jadwal ? `${jadwal.renstra} · ${jadwal.tahun}` : 'Rekapitulasi indikator'}</h2>
                        {can.ekspor && <div className="flex min-w-0 flex-col items-start gap-2 sm:items-end">
                            <Button type="button" variant="outline" onClick={download} isLoading={downloading}
                                aria-busy={downloading} aria-describedby={!ada_data_ekspor ? 'ekspor-kosong' : filterChanged ? 'ekspor-filter' : undefined}
                                disabled={form.processing || filterChanged || !ada_data_ekspor || !jadwal}>
                                {!downloading && <Download className="h-4 w-4" aria-hidden="true" />}{downloading ? 'Menyiapkan Excel…' : 'Unduh Excel'}
                            </Button>
                            {!ada_data_ekspor ? <p id="ekspor-kosong" className="text-sm text-muted">Tidak ada data untuk diekspor</p>
                                : filterChanged ? <p id="ekspor-filter" className="text-sm text-muted">Terapkan filter terlebih dahulu untuk mengunduh Excel.</p> : null}
                            {downloading && <p role="status" className="text-sm text-muted">Berkas Excel sedang disiapkan.</p>}
                        </div>}
                    </div>
                    {downloadFailure && <p role="alert" className="text-sm text-danger">{downloadFailure}</p>}
                    <div aria-label="Legenda rekapitulasi" className="flex flex-wrap gap-x-5 gap-y-2 text-xs leading-relaxed text-muted"><span><strong className="font-medium text-ink">Belum disahkan:</strong> belum ada realisasi resmi.</span><span><strong className="font-medium text-ink">Tanpa target periode:</strong> capaian persen tidak dihitung.</span><span><strong className="font-medium text-ink">Belum dapat diukur:</strong> nilai belum tersedia.</span></div>
                </div>
                {baris.length === 0 ? <p role="status" className="px-5 py-12 text-center text-sm text-muted">{jadwal ? 'Belum ada indikator pada jadwal dan periode yang dipilih.' : 'Belum ada jadwal pelaporan yang dapat ditampilkan.'}</p> : <Table aria-label="Matriks rekapitulasi indikator dan periode" className="block lg:table lg:table-fixed" wrapperClassName="overflow-visible">
                    <TableHeader className="hidden lg:table-header-group"><TableRow>{['Indikator & PIC', 'Target', 'Realisasi & capaian'].map((label, index) => <TableHead key={label} className={`whitespace-normal ${index === 0 ? 'w-2/5' : 'w-[30%]'}`}>{label}</TableHead>)}</TableRow></TableHeader>
                    <TableBody className="block divide-y-0 lg:table-row-group">{baris.map((row) => <Fragment key={row.id}>
                        <TableRow className="block border-t border-border first:border-t-0 hover:bg-surface lg:table-row"><IsiBaris row={row} /></TableRow>
                        <TableRow className="block hover:bg-surface lg:table-row"><TableCell colSpan={3} className="block pt-0 lg:table-cell">
                            <details className="rounded-lg border border-border bg-soft/40 p-4">
                                <summary className="cursor-pointer rounded text-sm font-medium text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25">Analisis, kegiatan & rekomendasi<span className="sr-only"> — {row.indikator.kode ?? row.indikator.nama ?? tidakTercatat}, {row.periode.nama}</span></summary>
                                <RincianBaris row={row} />
                            </details>
                        </TableCell></TableRow>
                    </Fragment>)}</TableBody>
                </Table>}
                <Pagination {...pagination} ariaLabel="Halaman rekapitulasi" className="border-t border-border px-5 py-3.5" />
            </Card>
        </div>
    </AuthenticatedLayout>;
}
