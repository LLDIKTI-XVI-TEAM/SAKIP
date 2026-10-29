import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, PenLine, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Modal } from '@/Components/Modal';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type {
    BarisStatusCapaian,
    FormStatusCapaian,
    ModeStatusCapaian,
    StatusCapaianProps,
} from '@/types/statusCapaian';

const tautanPaginasi = 'inline-flex items-center rounded-lg border border-border bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';
const labelStatus: Record<'tercapai' | 'belum_tercapai', string> = {
    tercapai: 'Tercapai',
    belum_tercapai: 'Belum tercapai',
};

function Data({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-muted">{label}</dt>
            <dd className="mt-0.5 break-words text-xs font-semibold text-ink">{children}</dd>
        </div>
    );
}

/** Selisih realisasi terhadap target PK; tanda mengikuti nilai server, warna tidak dipakai sebagai satu-satunya makna. */
function NilaiSelisihPk({ selisih, desimal, satuan }: { selisih: string | null; desimal: number; satuan: string | null }) {
    const formatNilai = useFormatNilai();
    if (selisih === null) return <span className="text-muted">—</span>;

    const negatif = selisih.startsWith('-');
    const mutlak = formatNilai(negatif ? selisih.slice(1) : selisih, desimal);
    const nol = !/[1-9]/.test(mutlak);
    const tanda = nol ? '' : negatif ? '−' : '+';

    return (
        <span>
            <span className="font-semibold tabular-nums text-ink">{`${tanda}${mutlak}${satuan ? ` ${satuan}` : ''}`}</span>
            <span className="block text-xs font-normal text-muted">{nol ? 'sama dengan target PK' : negatif ? 'di bawah target PK' : 'di atas target PK'}</span>
        </span>
    );
}

function DialogStatusCapaian({ baris, mode, onClose }: { baris: BarisStatusCapaian; mode: ModeStatusCapaian; onClose: () => void }) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const recovery = useAuthRecovery();
    const formId = useId();
    const statusErrorId = `${formId}-status-error`;
    const [pesan, setPesan] = useState('');
    const [perluMuatUlang, setPerluMuatUlang] = useState(false);
    const form = useForm<FormStatusCapaian>({
        status: mode === 'revisi' ? (baris.status_berlaku?.status ?? '') : '',
        pengukuran_versi_id: baris.versi_pengukuran?.id ?? '',
        versi: baris.versi,
        alasan: '',
    });

    // Konflik 409 dan penolakan field dikirim server dengan key di luar data form.
    // Sebagian field (mis. status_capaian) dapat tiba sebagai array pesan validasi;
    // render hanya menerima teks, jadi normalisasi dilakukan sekali di sini.
    const galat = form.errors as Record<string, string | string[] | undefined>;
    const teksGalat = (nilai: string | string[] | undefined): string | undefined =>
        Array.isArray(nilai) ? nilai.join(' ') : nilai;
    const desimal = baris.indikator.desimal_tampilan ?? 0;
    const satuan = baris.indikator.satuan;
    const angka = (nilai: string | null) => nilai === null ? '—' : `${formatNilai(nilai, desimal)}${satuan ? ` ${satuan}` : ''}`;
    const konflik = teksGalat(galat.versi);
    const galatPengukuranVersi = teksGalat(galat.pengukuran_versi_id);
    const galatStatus = teksGalat(galat.status);
    const galatAlasan = teksGalat(galat.alasan);
    const statusError = teksGalat(galat.status_capaian) ?? galatStatus;
    const versiTidakTersedia = baris.versi_pengukuran === null;
    const terkunci = form.processing || Boolean(konflik) || perluMuatUlang || Boolean(recovery.recovery);
    const judul = mode === 'revisi' ? 'Revisi status capaian' : 'Tetapkan status capaian';
    const muatUlang = () => router.reload({ onSuccess: () => onClose() });

    const simpan = (event: FormEvent) => {
        event.preventDefault();
        if (terkunci || form.data.status === '' || versiTidakTersedia) return;
        setPesan('');
        form.post(`/verifikasi/${baris.id}/status-capaian`, {
            preserveScroll: true,
            onSuccess: onClose,
            onCancel: () => setPesan('Permintaan dibatalkan. Hasil penetapan belum diketahui; muat ulang sebelum mencoba lagi.'),
            onNetworkError: () => {
                setPesan('Koneksi terputus. Hasil penetapan belum diketahui; muat ulang sebelum mencoba lagi.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/verifikasi/${baris.id}/status-capaian`, mutation: true })) return false;
                if (response.status === 409) {
                    setPerluMuatUlang(true);
                    setPesan('Data telah berubah. Muat ulang halaman sebelum menetapkan ulang.');
                    return false;
                }
                setPesan(response.status === 403
                    ? 'Izin menetapkan status capaian tidak tersedia atau telah dicabut.'
                    : response.status === 429
                        ? 'Terlalu banyak permintaan. Muat ulang halaman sebelum mencoba lagi.'
                        : 'Hasil penetapan belum dapat dipastikan. Muat ulang halaman sebelum mencoba lagi.');
                return false;
            },
        });
    };

    return (
        <Modal
            isOpen
            onClose={() => { if (!form.processing) onClose(); }}
            size="lg"
            title={judul}
            description={`${baris.indikator.kode ?? 'Indikator'} · ${baris.indikator.nama ?? 'Tanpa nama'} · ${baris.periode.nama ?? 'Periode'} ${baris.tahun}`}
            footer={(
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>Batal</Button>
                    <Button type="submit" form={formId} isLoading={form.processing} disabled={terkunci || form.data.status === '' || versiTidakTersedia}>
                        Simpan
                    </Button>
                </div>
            )}
        >
            <form id={formId} onSubmit={simpan} className="space-y-4" aria-busy={form.processing}>
                {recovery.recovery && <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />}

                {pesan && (
                    <div role="alert" className="space-y-2 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                        <p>{pesan}</p>
                        {perluMuatUlang && <Button type="button" size="sm" variant="outline" onClick={muatUlang}>Muat ulang halaman</Button>}
                    </div>
                )}
                {konflik && (
                    <div role="alert" className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-ink">
                        <p className="font-medium">{konflik}</p>
                        <Button type="button" size="sm" variant="outline" onClick={muatUlang}>Muat ulang halaman</Button>
                    </div>
                )}
                {galatPengukuranVersi && <p role="alert" className="text-sm text-danger">{galatPengukuranVersi}</p>}
                {statusError && !konflik && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">{statusError}</p>}

                <dl className="grid grid-cols-2 gap-3 rounded-lg bg-soft p-3">
                    <Data label="Realisasi">{angka(baris.nilai)}</Data>
                    <Data label="Target periode">{angka(baris.target)}</Data>
                    <Data label="Capaian terhadap target">{baris.capaian_persen === null ? '—' : `${formatNilai(baris.capaian_persen, 2)}%`}</Data>
                    <Data label="Target PK">{angka(baris.target_pk)}</Data>
                    <Data label="Selisih terhadap target PK"><NilaiSelisihPk selisih={baris.selisih_target_pk} desimal={desimal} satuan={satuan} /></Data>
                    <Data label="Versi pengukuran">
                        {baris.versi_pengukuran === null
                            ? 'Belum tersedia'
                            : `Versi ${baris.versi_pengukuran.nomor} · disahkan ${formatTanggal(baris.versi_pengukuran.disahkan_pada)}`}
                    </Data>
                    {mode === 'revisi' && baris.status_berlaku && (
                        <div className="col-span-2 min-w-0 border-t border-border pt-2">
                            <dt className="text-xs font-medium text-muted">Status berlaku</dt>
                            <dd className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink">
                                <StatusCapaianBadge kode={baris.status_berlaku.status} size="sm" />
                                <span className="text-muted">
                                    {baris.status_berlaku.penetap ?? 'Penetap tidak tercatat'} · {formatTanggal(baris.status_berlaku.ditetapkan_pada, { withTime: true })}
                                </span>
                            </dd>
                        </div>
                    )}
                </dl>

                {versiTidakTersedia && (
                    <p role="alert" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-ink">
                        Versi pengukuran yang disahkan belum tersedia, sehingga status belum dapat ditetapkan.
                    </p>
                )}

                <fieldset disabled={form.processing || Boolean(konflik) || perluMuatUlang} aria-describedby={galatStatus ? statusErrorId : undefined}>
                    <legend className="text-sm font-semibold text-ink">
                        Status capaian <span className="text-danger" aria-hidden="true">*</span>
                    </legend>
                    <p className="mt-0.5 text-xs text-muted">
                        {mode === 'revisi'
                            ? 'Status baru menggantikan status berlaku; riwayat versi sebelumnya tetap tersimpan.'
                            : 'Status berlaku untuk versi pengukuran ini dan tidak mengubah versi berikutnya.'}
                    </p>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                        {(['tercapai', 'belum_tercapai'] as const).map((nilai) => (
                            <label
                                key={nilai}
                                className={`flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors focus-within:ring-2 focus-within:ring-primary/25 ${form.data.status === nilai ? 'border-primary bg-primary/5 text-ink' : 'border-border bg-surface text-ink hover:bg-soft'}`}
                            >
                                <input
                                    type="radio"
                                    name={`status-${formId}`}
                                    value={nilai}
                                    checked={form.data.status === nilai}
                                    onChange={() => form.setData('status', nilai)}
                                    aria-invalid={Boolean(galatStatus)}
                                    aria-required="true"
                                    className="mt-0.5 h-4 w-4 accent-primary"
                                />
                                <span>
                                    <span className="block font-semibold">{labelStatus[nilai]}</span>
                                    <span className="block text-xs font-normal text-muted">
                                        {nilai === 'tercapai' ? 'Status capaian dinyatakan tercapai.' : 'Status capaian dinyatakan belum tercapai.'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {galatStatus && <p id={statusErrorId} role="alert" className="mt-1.5 text-xs font-medium text-danger">{galatStatus}</p>}
                </fieldset>

                <Textarea
                    label="Alasan (opsional)"
                    name="alasan"
                    rows={3}
                    maxLength={1000}
                    value={form.data.alasan}
                    error={galatAlasan}
                    onChange={(event) => form.setData('alasan', event.target.value)}
                    helperText="Alasan tidak wajib diisi. Penetapan tetap dicatat pada audit log beserta pengguna, waktu, dan nilai sebelum/sesudah."
                />
            </form>
        </Modal>
    );
}

export default function StatusCapaian({ kandidat, sudah_ditetapkan, pagination, pagination_status, can }: StatusCapaianProps) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const labelUnit = useLabelUnit();
    const [dialog, setDialog] = useState<{ baris: BarisStatusCapaian; mode: ModeStatusCapaian } | null>(null);

    const angka = (baris: BarisStatusCapaian, nilai: string | null) => {
        if (nilai === null) return '—';
        const desimal = baris.indikator.desimal_tampilan ?? 0;
        return `${formatNilai(nilai, desimal)}${baris.indikator.satuan ? ` ${baris.indikator.satuan}` : ''}`;
    };
    const capaian = (baris: BarisStatusCapaian) => baris.capaian_persen === null ? '—' : `${formatNilai(baris.capaian_persen, 2)}%`;
    const periode = (baris: BarisStatusCapaian) => `${baris.periode.nama ?? '—'} ${baris.tahun}`;
    const versi = (baris: BarisStatusCapaian) => baris.versi_pengukuran === null
        ? 'Belum ada versi sah'
        : <>Versi {baris.versi_pengukuran.nomor}<span className="block text-xs text-muted">disahkan {formatTanggal(baris.versi_pengukuran.disahkan_pada)}</span></>;

    return (
        <AuthenticatedLayout
            title="Status Capaian"
            breadcrumbs={[{ label: 'Verifikasi Kinerja', href: '/verifikasi' }, { label: 'Status Capaian' }]}
        >
            <Head title="Status Capaian" />

            <div className="min-w-0 space-y-5">
                <p className="text-sm text-muted">
                    Reviu &amp; Pengesahan: tetapkan status capaian pada versi pengukuran yang telah disahkan. Penetapan melekat pada versi
                    tersebut; perubahan berikutnya dicatat sebagai revisi dan seluruh riwayat tetap tersimpan.
                </p>

                {!can.tetapkan && (
                    <div role="status" className="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
                        Anda tidak memiliki izin menetapkan status capaian. Halaman ini hanya dapat dibaca.
                    </div>
                )}

                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-2 border-b border-border px-4 py-4 sm:px-5">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold text-ink">Belum ditetapkan</h2>
                            <p className="mt-0.5 text-xs text-muted">Versi pengukuran yang telah disahkan dan belum memiliki status capaian berlaku.</p>
                        </div>
                        <Badge variant="muted" size="sm">{pagination.total} pengukuran</Badge>
                    </div>

                    {kandidat.length === 0 ? (
                        <div role="status" className="px-5 py-10 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-border bg-soft text-muted">
                                <Search className="h-6 w-6" aria-hidden="true" />
                            </div>
                            <p className="mt-3 text-sm font-semibold text-ink">Tidak ada pengukuran yang menunggu penetapan</p>
                            <p className="mx-auto mt-1 max-w-md text-xs text-muted">
                                Semua versi pengukuran yang disahkan sudah memiliki status capaian, atau belum ada versi yang memenuhi syarat penetapan.
                            </p>
                        </div>
                    ) : (
                        <>
                            <Table wrapperClassName="hidden md:block" aria-label="Kandidat penetapan status capaian">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-3 py-3">INDIKATOR</TableHead>
                                        <TableHead className="px-3 py-3">{labelUnit.toUpperCase()}</TableHead>
                                        <TableHead className="px-3 py-3">PERIODE</TableHead>
                                        <TableHead className="px-3 py-3">VERSI</TableHead>
                                        <TableHead className="px-3 py-3 text-right">
                                            <span className="block">REALISASI</span>
                                            <span className="block font-normal">CAPAIAN</span>
                                        </TableHead>
                                        <TableHead className="px-3 py-3 text-right">
                                            <span className="block">TARGET</span>
                                            <span className="block font-normal">TARGET PK</span>
                                        </TableHead>
                                        <TableHead className="px-3 py-3 text-right">SELISIH PK</TableHead>
                                        <TableHead className="sticky right-0 z-10 border-l border-border bg-soft px-3 py-3 text-right">AKSI</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {kandidat.map((baris) => (
                                        <TableRow key={baris.id} className="group hover:bg-soft transition-colors">
                                            <TableCell className="max-w-xs px-3 py-3 align-top">
                                                <p className="text-xs font-bold text-ink">{baris.indikator.kode ?? '—'}</p>
                                                <p className="mt-0.5 whitespace-normal break-words text-xs leading-relaxed text-muted">{baris.indikator.nama ?? '—'}</p>
                                            </TableCell>
                                            <TableCell className="px-3 py-3 align-top text-xs text-ink">{baris.unit.nama ?? '—'}</TableCell>
                                            <TableCell className="px-3 py-3 align-top text-xs text-ink">{periode(baris)}</TableCell>
                                            <TableCell className="px-3 py-3 align-top text-xs text-ink">{versi(baris)}</TableCell>
                                            <TableCell className="whitespace-nowrap px-3 py-3 text-right align-top tabular-nums text-ink">
                                                <span className="block font-semibold">{angka(baris, baris.nilai)}</span>
                                                <span className="block text-xs font-normal">{capaian(baris)}</span>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap px-3 py-3 text-right align-top tabular-nums text-ink">
                                                <span className="block">{angka(baris, baris.target)}</span>
                                                <span className="block text-xs font-normal">{angka(baris, baris.target_pk)}</span>
                                            </TableCell>
                                            <TableCell className="px-3 py-3 text-right align-top tabular-nums text-ink">
                                                <NilaiSelisihPk selisih={baris.selisih_target_pk} desimal={baris.indikator.desimal_tampilan ?? 0} satuan={baris.indikator.satuan} />
                                            </TableCell>
                                            <TableCell className="sticky right-0 z-10 whitespace-nowrap border-l border-border bg-surface px-3 py-3 text-right align-top group-hover:bg-soft">
                                                {can.tetapkan && baris.can.tetapkan ? (
                                                    <Button type="button" size="sm" onClick={() => setDialog({ baris, mode: 'tetapkan' })} aria-label={`Tetapkan status capaian ${baris.indikator.kode ?? baris.indikator.nama ?? ''}`}>
                                                        <ClipboardCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                                        Tetapkan
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-muted">{can.tetapkan ? 'Tidak memenuhi syarat' : 'Tanpa izin'}</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            <ul className="divide-y divide-border md:hidden">
                                {kandidat.map((baris) => (
                                    <li key={baris.id} className="space-y-3 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-xs font-bold text-primary">{baris.indikator.kode ?? '—'}</p>
                                                <p className="mt-0.5 break-words text-sm font-semibold leading-snug text-ink">{baris.indikator.nama ?? '—'}</p>
                                            </div>
                                            <StatusCapaianBadge kode="belum_ditetapkan" size="sm" className="shrink-0" />
                                        </div>
                                        <dl className="grid grid-cols-2 gap-3">
                                            <Data label={labelUnit}>{baris.unit.nama ?? '—'}</Data>
                                            <Data label="Periode">{periode(baris)}</Data>
                                            <Data label="Versi">{versi(baris)}</Data>
                                            <Data label="Realisasi">{angka(baris, baris.nilai)}</Data>
                                            <Data label="Target periode">{angka(baris, baris.target)}</Data>
                                            <Data label="Capaian">{capaian(baris)}</Data>
                                            <Data label="Target PK">{angka(baris, baris.target_pk)}</Data>
                                            <Data label="Selisih terhadap target PK">
                                                <NilaiSelisihPk selisih={baris.selisih_target_pk} desimal={baris.indikator.desimal_tampilan ?? 0} satuan={baris.indikator.satuan} />
                                            </Data>
                                        </dl>
                                        {can.tetapkan && baris.can.tetapkan ? (
                                            <Button type="button" size="sm" className="w-full" onClick={() => setDialog({ baris, mode: 'tetapkan' })} aria-label={`Tetapkan status capaian ${baris.indikator.kode ?? baris.indikator.nama ?? ''}`}>
                                                <ClipboardCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                                Tetapkan
                                            </Button>
                                        ) : (
                                            <p className="text-xs text-muted">{can.tetapkan ? 'Tidak memenuhi syarat penetapan.' : 'Tanpa izin menetapkan.'}</p>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            {pagination.last_page > 1 && (
                                <nav aria-label="Halaman kandidat status capaian" className="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3">
                                    {pagination.prev_page_url
                                        ? <Link href={pagination.prev_page_url} preserveScroll className={tautanPaginasi}>Sebelumnya</Link>
                                        : <span aria-hidden="true" />}
                                    <p className="text-xs text-muted">Halaman {pagination.current_page} dari {pagination.last_page} · {pagination.total} baris</p>
                                    {pagination.next_page_url
                                        ? <Link href={pagination.next_page_url} preserveScroll className={tautanPaginasi}>Berikutnya</Link>
                                        : <span aria-hidden="true" />}
                                </nav>
                            )}
                        </>
                    )}
                </Card>

                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-2 border-b border-border px-4 py-4 sm:px-5">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold text-ink">Sudah ditetapkan</h2>
                            <p className="mt-0.5 text-xs text-muted">
                                Status berlaku pada versi sah terakhir. Revisi menulis status baru tanpa menghapus riwayat.
                            </p>
                        </div>
                        <Badge variant="muted" size="sm">{pagination_status.total} pengukuran</Badge>
                    </div>

                    {sudah_ditetapkan.length === 0 ? (
                        <p role="status" className="px-5 py-10 text-center text-sm text-muted">
                            Belum ada status capaian yang ditetapkan pada versi pengukuran yang disahkan.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {sudah_ditetapkan.map((baris) => (
                                <li key={baris.id} className="p-4 sm:p-5">
                                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div className="min-w-0 space-y-1">
                                            <p className="text-xs font-semibold text-primary">
                                                {baris.indikator.kode ?? '—'} · {periode(baris)}
                                            </p>
                                            <h3 className="break-words text-sm font-semibold leading-snug text-ink">{baris.indikator.nama ?? '—'}</h3>
                                            <p className="text-xs text-muted">
                                                {baris.unit.nama ?? '—'} · {versi(baris)}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                                {baris.status_berlaku && <StatusCapaianBadge kode={baris.status_berlaku.status} size="sm" />}
                                                <span className="text-xs text-muted">
                                                    {baris.status_berlaku?.penetap ?? 'Penetap tidak tercatat'}
                                                    {' · '}
                                                    {formatTanggal(baris.status_berlaku?.ditetapkan_pada, { withTime: true })}
                                                </span>
                                            </div>
                                        </div>
                                        {can.tetapkan && baris.can.revisi && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="self-start"
                                                onClick={() => setDialog({ baris, mode: 'revisi' })}
                                                aria-label={`Revisi status capaian ${baris.indikator.kode ?? baris.indikator.nama ?? ''}`}
                                            >
                                                <PenLine className="h-3.5 w-3.5" aria-hidden="true" />
                                                Revisi
                                            </Button>
                                        )}
                                    </div>

                                    <dl className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <Data label="Realisasi">{angka(baris, baris.nilai)}</Data>
                                        <Data label="Target periode">{angka(baris, baris.target)}</Data>
                                        <Data label="Capaian">{capaian(baris)}</Data>
                                        <Data label="Selisih terhadap target PK">
                                            <NilaiSelisihPk selisih={baris.selisih_target_pk} desimal={baris.indikator.desimal_tampilan ?? 0} satuan={baris.indikator.satuan} />
                                        </Data>
                                    </dl>

                                    <details className="mt-3 rounded-lg border border-border bg-soft/40 p-3">
                                        <summary className="cursor-pointer rounded text-xs font-semibold text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25">
                                            Riwayat status per versi ({baris.riwayat.length})
                                            <span className="sr-only"> untuk {baris.indikator.kode ?? baris.indikator.nama ?? 'indikator'}</span>
                                        </summary>
                                        {baris.riwayat.length === 0 ? (
                                            <p className="mt-2 text-xs text-muted">Belum ada riwayat status tercatat.</p>
                                        ) : (
                                            <ul className="mt-2 divide-y divide-border">
                                                {baris.riwayat.map((item) => (
                                                    <li key={item.id} className="flex flex-wrap items-center gap-2 py-2 text-xs">
                                                        <StatusCapaianBadge kode={item.status} size="sm" />
                                                        {item.id === baris.status_berlaku?.id && <Badge variant="primary" size="sm">Berlaku</Badge>}
                                                        <span className="text-muted">
                                                            Versi {item.versi_nomor ?? '—'} · {item.penetap ?? 'Penetap tidak tercatat'} · {formatTanggal(item.ditetapkan_pada, { withTime: true })}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </details>
                                </li>
                            ))}
                        </ul>
                    )}

                    {pagination_status.last_page > 1 && (
                        <nav aria-label="Halaman status capaian yang sudah ditetapkan" className="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3">
                            {pagination_status.prev_page_url
                                ? <Link href={pagination_status.prev_page_url} preserveScroll className={tautanPaginasi}>Sebelumnya</Link>
                                : <span aria-hidden="true" />}
                            <p className="text-xs text-muted">Halaman {pagination_status.current_page} dari {pagination_status.last_page} · {pagination_status.total} baris</p>
                            {pagination_status.next_page_url
                                ? <Link href={pagination_status.next_page_url} preserveScroll className={tautanPaginasi}>Berikutnya</Link>
                                : <span aria-hidden="true" />}
                        </nav>
                    )}
                </Card>
            </div>

            {dialog && (
                <DialogStatusCapaian
                    key={`${dialog.mode}-${dialog.baris.id}-${dialog.baris.versi}`}
                    baris={dialog.baris}
                    mode={dialog.mode}
                    onClose={() => setDialog(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
