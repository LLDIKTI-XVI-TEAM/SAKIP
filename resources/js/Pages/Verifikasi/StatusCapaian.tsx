import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ClipboardCheck, History, PenLine, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Modal } from '@/Components/Modal';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';
import { TabTautan } from '@/Components/TabTautan';
import {
    Table, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive,
} from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { Tooltip } from '@/Components/Tooltip';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type {
    BarisStatusCapaian,
    FormStatusCapaian,
    ModeStatusCapaian,
    StatusCapaianProps,
} from '@/types/statusCapaian';

const sel = `${tableCellResponsive} block align-top md:table-cell`;
const selKanan = `${sel} md:text-right`;
const barisKartu = 'block min-w-0 space-y-2 p-4 md:table-row md:space-y-0 md:p-0';
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
    const { url } = usePage();
    const [dialog, setDialog] = useState<{ baris: BarisStatusCapaian; mode: ModeStatusCapaian } | null>(null);
    const [riwayat, setRiwayat] = useState<BarisStatusCapaian | null>(null);

    // Tab aktif dibaca dari URL agar bertahan saat pindah halaman dan setelah penetapan disimpan.
    const query = new URLSearchParams(url.split('?')[1] ?? '');
    const tabDitetapkan = query.get('tab') === 'ditetapkan';
    const tautanTab = (ditetapkan: boolean): string => {
        const params = new URLSearchParams(query);
        if (ditetapkan) params.set('tab', 'ditetapkan');
        else params.delete('tab');
        const teks = params.toString();

        return `/verifikasi/status-capaian${teks ? `?${teks}` : ''}`;
    };

    const angka = (baris: BarisStatusCapaian, nilai: string | null) => {
        if (nilai === null) return '-';
        const desimal = baris.indikator.desimal_tampilan ?? 0;
        return `${formatNilai(nilai, desimal)}${baris.indikator.satuan ? ` ${baris.indikator.satuan}` : ''}`;
    };
    const capaian = (baris: BarisStatusCapaian) => baris.capaian_persen === null ? '-' : `${formatNilai(baris.capaian_persen, 2)}%`;
    const periode = (baris: BarisStatusCapaian) => `${baris.periode.nama ?? '-'} ${baris.tahun}`;
    const versi = (baris: BarisStatusCapaian) => baris.versi_pengukuran === null ? 'Belum ada versi sah' : `Versi ${baris.versi_pengukuran.nomor}`;
    const sebutan = (baris: BarisStatusCapaian) => baris.indikator.kode ?? baris.indikator.nama ?? '';

    // Tiga kolom pertama sama di kedua daftar; rincian lain dibaca di dialog penetapan dan jendela riwayat.
    const selIdentitas = (baris: BarisStatusCapaian) => (
        <>
            <TableCell className={sel}>
                <p className="text-xs font-bold text-ink">{baris.indikator.kode ?? '-'}</p>
                <p title={baris.indikator.nama ?? undefined} className="mt-0.5 line-clamp-2 whitespace-normal break-words text-xs leading-relaxed text-muted">{baris.indikator.nama ?? '-'}</p>
            </TableCell>
            <TableCell className={sel}>
                <TableCellLabel>{labelUnit}</TableCellLabel>
                <span className="break-words text-xs text-ink">{baris.unit.nama ?? '-'}</span>
            </TableCell>
            <TableCell className={sel}>
                <TableCellLabel>Periode</TableCellLabel>
                <span className="text-xs text-ink md:whitespace-nowrap">{periode(baris)}</span>
                <span className="block text-xs text-muted">{versi(baris)}</span>
            </TableCell>
        </>
    );
    const selCapaian = (baris: BarisStatusCapaian) => (
        <TableCell className={selKanan}>
            <TableCellLabel>Capaian</TableCellLabel>
            <span className="font-semibold tabular-nums text-ink">{capaian(baris)}</span>
        </TableCell>
    );

    return (
        <AuthenticatedLayout
            title="Status Capaian"
            breadcrumbs={[{ label: 'Verifikasi Kinerja', href: '/verifikasi' }, { label: 'Status Capaian' }]}
        >
            <Head title="Status Capaian" />

            <div className="min-w-0 space-y-5">
                <p className="text-sm text-muted">
                    Tetapkan status capaian pada versi pengukuran yang telah disahkan. Penetapan melekat pada versi tersebut;
                    perubahan berikutnya dicatat sebagai revisi dan seluruh riwayat tetap tersimpan.
                </p>

                {!can.tetapkan && (
                    <div role="status" className="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
                        Anda tidak memiliki izin menetapkan status capaian. Halaman ini hanya dapat dibaca.
                    </div>
                )}

                <Card>
                    <TabTautan
                        label="Daftar status capaian"
                        satuan="pengukuran"
                        items={[
                            { href: tautanTab(false), label: 'Belum ditetapkan', jumlah: pagination.total, aktif: !tabDitetapkan },
                            { href: tautanTab(true), label: 'Sudah ditetapkan', jumlah: pagination_status.total, aktif: tabDitetapkan },
                        ]}
                    />

                    {tabDitetapkan ? (
                        <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Status capaian yang sudah ditetapkan">
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead className="whitespace-normal">KODE &amp; INDIKATOR</TableHead>
                                    <TableHead>{labelUnit.toUpperCase()}</TableHead>
                                    <TableHead>PERIODE</TableHead>
                                    <TableHead className="text-right">CAPAIAN</TableHead>
                                    <TableHead>STATUS</TableHead>
                                    <TableHead className="text-right">AKSI</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {sudah_ditetapkan.length === 0 ? (
                                    <TableEmptyRow
                                        colSpan={6}
                                        icon={Search}
                                        title="Belum ada status capaian yang ditetapkan"
                                        description="Status yang ditetapkan pada versi pengukuran yang disahkan tampil di sini, beserta riwayat revisinya."
                                    />
                                ) : sudah_ditetapkan.map((baris) => (
                                    <TableRow key={baris.id} className={barisKartu}>
                                        {selIdentitas(baris)}
                                        {selCapaian(baris)}
                                        <TableCell className={sel}>
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 md:flex-col md:items-start md:whitespace-nowrap">
                                                {baris.status_berlaku && <StatusCapaianBadge kode={baris.status_berlaku.status} size="sm" />}
                                                <span className="text-xs text-muted">{formatTanggal(baris.status_berlaku?.ditetapkan_pada)}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className={selKanan}>
                                            <div className="inline-flex items-center gap-2">
                                                <Tooltip content="Riwayat status" align="right">
                                                    <Button type="button" size="sm" variant="outline" className="h-9 w-9 px-0" onClick={() => setRiwayat(baris)} aria-label={`Riwayat status capaian ${sebutan(baris)}`}>
                                                        <History className="h-4 w-4" aria-hidden="true" />
                                                    </Button>
                                                </Tooltip>
                                                {can.tetapkan && baris.can.revisi && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => setDialog({ baris, mode: 'revisi' })} aria-label={`Revisi status capaian ${sebutan(baris)}`}>
                                                        <PenLine className="h-3.5 w-3.5" aria-hidden="true" />
                                                        Revisi
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Kandidat penetapan status capaian">
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead className="whitespace-normal">KODE &amp; INDIKATOR</TableHead>
                                    <TableHead>{labelUnit.toUpperCase()}</TableHead>
                                    <TableHead>PERIODE</TableHead>
                                    <TableHead className="text-right">REALISASI</TableHead>
                                    <TableHead className="text-right">CAPAIAN</TableHead>
                                    <TableHead className="text-right">AKSI</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {kandidat.length === 0 ? (
                                    <TableEmptyRow
                                        colSpan={6}
                                        icon={Search}
                                        title={pagination.total === 0 ? 'Tidak ada pengukuran yang menunggu penetapan' : 'Tidak ada kandidat di halaman ini'}
                                        description={pagination.total === 0
                                            ? 'Semua versi pengukuran yang disahkan sudah memiliki status capaian, atau belum ada versi yang memenuhi syarat penetapan.'
                                            : `Halaman ini tidak memuat kandidat, tetapi masih ada ${pagination.total} pengukuran yang menunggu penetapan pada halaman lain. Gunakan tautan Sebelumnya di bawah untuk kembali ke halaman berisi kandidat.`}
                                    />
                                ) : kandidat.map((baris) => (
                                    <TableRow key={baris.id} className={barisKartu}>
                                        {selIdentitas(baris)}
                                        <TableCell className={selKanan}>
                                            <TableCellLabel>Realisasi</TableCellLabel>
                                            <span className="font-semibold tabular-nums text-ink md:whitespace-nowrap">{angka(baris, baris.nilai)}</span>
                                            <span className="block text-xs tabular-nums text-muted md:whitespace-nowrap">Target {angka(baris, baris.target)}</span>
                                        </TableCell>
                                        {selCapaian(baris)}
                                        <TableCell className={selKanan}>
                                            {can.tetapkan && baris.can.tetapkan ? (
                                                <Button type="button" size="sm" onClick={() => setDialog({ baris, mode: 'tetapkan' })} aria-label={`Tetapkan status capaian ${sebutan(baris)}`}>
                                                    <ClipboardCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                                    Tetapkan
                                                </Button>
                                            ) : (
                                                <span className="text-xs text-muted md:whitespace-nowrap">{can.tetapkan ? 'Tidak memenuhi syarat' : 'Tanpa izin'}</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <Pagination pagination={tabDitetapkan ? pagination_status : pagination} />
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

            {riwayat && (
                <Modal
                    isOpen
                    onClose={() => setRiwayat(null)}
                    size="md"
                    title="Riwayat status capaian"
                    description={`${riwayat.indikator.kode ?? 'Indikator'} · ${riwayat.indikator.nama ?? 'Tanpa nama'} · ${periode(riwayat)}`}
                    footer={(
                        <div className="flex justify-end">
                            <Button type="button" variant="outline" onClick={() => setRiwayat(null)}>Tutup</Button>
                        </div>
                    )}
                >
                    {riwayat.riwayat.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada riwayat status tercatat.</p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {riwayat.riwayat.map((item) => (
                                <li key={item.id} className="flex flex-wrap items-center gap-2 py-2 text-xs">
                                    <StatusCapaianBadge kode={item.status} size="sm" />
                                    {item.id === riwayat.status_berlaku?.id && <Badge variant="primary" size="sm">Berlaku</Badge>}
                                    <span className="text-muted">
                                        Versi {item.versi_nomor ?? '-'} · {item.penetap ?? 'Penetap tidak tercatat'} · {formatTanggal(item.ditetapkan_pada, { withTime: true })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Modal>
            )}
        </AuthenticatedLayout>
    );
}
