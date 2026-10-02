import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Check, Info, Plus, Search, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Table } from '@/Components/Table';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { SharedPageProps } from '@/types/auth';
import type {
    JadwalEditorProps,
    JadwalPeriode,
    JadwalPeriodeInput,
    JadwalRenstraOption,
    PeriodeOption,
} from '@/types/periode-jadwal';

interface JadwalForm {
    renstra_id: string;
    tahun: string;
    rencana_aksi_mulai: string;
    rencana_aksi_selesai: string;
    penutupan: string;
    revisi?: number;
    periode: JadwalPeriode[];
}

const windowFields = [
    { key: 'pengisian_mulai', label: 'Mulai pengisian' },
    { key: 'pengisian_selesai', label: 'Selesai pengisian' },
    { key: 'reviu_mulai', label: 'Mulai Review' },
    { key: 'reviu_selesai', label: 'Target Selesai Review' },
] as const;

const linkClass =
    'rounded-lg px-3 py-2 text-sm font-medium text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/30';

function OptionPicker<T extends { id: string; nama: string }>({
    kind,
    onSelect,
    onClose,
    describe,
    selectedIds = [],
}: {
    kind: 'renstra' | 'periode';
    onSelect: (option: T) => void;
    onClose: () => void;
    describe: (option: T) => string;
    selectedIds?: string[];
}) {
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<{ data: T[]; has_more: boolean }>({ data: [], has_more: false });
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState('');
    const [retry, setRetry] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setFailure('');
        const params = new URLSearchParams({ q: search, page: String(page) });
        void fetch(`/jadwal/opsi/${kind}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    if (!controller.signal.aborted)
                        setFailure(
                            response.status === 401 || response.status === 419
                                ? 'Sesi perlu dipulihkan. Tutup pilihan dan muat ulang halaman setelah menyalin input.'
                                : 'Pilihan belum dapat dimuat. Coba lagi.',
                        );
                    return;
                }
                const next: { data: T[]; has_more: boolean } = await response.json();
                if (!controller.signal.aborted) setResult(next);
            })
            .catch(() => {
                if (!controller.signal.aborted) setFailure('Pilihan belum dapat dimuat. Coba lagi.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [kind, search, page, retry]);

    const title = kind === 'renstra' ? 'Pilih Renstra' : 'Tambah periode';
    return (
        <Modal isOpen onClose={onClose} title={title} size="lg">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    setSearch(query);
                    setPage(1);
                    setRetry((value) => value + 1);
                }}
                className="flex items-end gap-2"
            >
                <Input
                    label={kind === 'renstra' ? 'Cari Renstra' : 'Cari periode'}
                    value={query}
                    maxLength={100}
                    autoComplete="off"
                    onChange={(event) => setQuery(event.target.value)}
                />
                <Button type="submit" variant="outline" aria-label="Cari pilihan">
                    <Search className="h-4 w-4" aria-hidden="true" />
                </Button>
            </form>
            <div className="mt-4 space-y-2" aria-live="polite" aria-busy={loading}>
                {loading ? (
                    <p className="py-4 text-sm text-muted">Memuat pilihan…</p>
                ) : failure ? (
                    <p role="alert" className="text-sm text-danger">
                        {failure}
                    </p>
                ) : result.data.length === 0 ? (
                    <p className="py-4 text-sm text-muted">Tidak ada pilihan yang sesuai.</p>
                ) : (
                    result.data.map((option) => (
                        <button
                            type="button"
                            key={option.id}
                            disabled={selectedIds.includes(option.id)}
                            onClick={() => onSelect(option)}
                            className="block w-full rounded-lg border border-border p-3 text-left text-sm transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span className="block break-words font-semibold">
                                {option.nama}
                                {selectedIds.includes(option.id) ? ' · Sudah dipilih' : ''}
                            </span>
                            <span className="mt-1 block text-xs text-muted">{describe(option)}</span>
                        </button>
                    ))
                )}
            </div>
            <div className="mt-4 flex items-center justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={loading || page === 1}
                    onClick={() => setPage((value) => value - 1)}
                >
                    Sebelumnya
                </Button>
                <span className="text-xs text-muted">Halaman {page}</span>
                <Button
                    type="button"
                    variant="outline"
                    disabled={loading || Boolean(failure) || !result.has_more}
                    onClick={() => setPage((value) => value + 1)}
                >
                    Berikutnya
                </Button>
            </div>
        </Modal>
    );
}

export default function Editor(props: JadwalEditorProps) {
    return <JadwalEditor key={props.jadwal?.id ?? 'create'} {...props} />;
}

function JadwalEditor({ jadwal, can, read_only_reason }: JadwalEditorProps) {
    const { auth } = usePage<SharedPageProps>().props;
    const form = useForm<JadwalForm>({
        renstra_id: jadwal?.renstra_id ?? '',
        tahun: jadwal ? String(jadwal.tahun) : '',
        rencana_aksi_mulai: jadwal?.rencana_aksi_mulai ?? '',
        rencana_aksi_selesai: jadwal?.rencana_aksi_selesai ?? '',
        penutupan: jadwal?.penutupan ?? '',
        ...(jadwal ? { revisi: jadwal.revisi } : {}),
        periode: jadwal?.periode ?? [],
    });
    const [renstra, setRenstra] = useState(jadwal?.renstra ?? null);
    const [picker, setPicker] = useState<'renstra' | 'periode' | null>(null);
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const submitting = useRef(false);
    const summaryRef = useRef<HTMLDivElement>(null);
    const errors: Record<string, string | undefined> = form.errors;
    const editable = !read_only_reason && (jadwal ? can.update : can.create);
    const blocked = Boolean(failure || recovery.recovery);
    const disabled = !editable || form.processing || blocked;
    const title = jadwal ? `Jadwal tahunan ${jadwal.tahun}` : 'Susun jadwal tahunan';
    const authoritativeUrl = jadwal
        ? `/jadwal/${jadwal.id}`
        : `/jadwal?${new URLSearchParams({ renstra_id: form.data.renstra_id, tahun: form.data.tahun })}`;

    useEffect(() => {
        if (form.hasErrors) summaryRef.current?.focus();
    }, [form.hasErrors, form.errors]);

    const unknown = () =>
        setFailure('Hasil penyimpanan belum terkonfirmasi. Periksa kalender terbaru sebelum menyimpan kembali.');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (disabled || submitting.current) return;
        submitting.current = true;
        form.transform((data) => ({
            renstra_id: data.renstra_id,
            tahun: data.tahun,
            rencana_aksi_mulai: data.rencana_aksi_mulai,
            rencana_aksi_selesai: data.rencana_aksi_selesai,
            penutupan: data.penutupan,
            ...(jadwal ? { revisi: data.revisi } : {}),
            periode: data.periode.map(
                ({
                    periode_id,
                    periode_revisi,
                    pengisian_mulai,
                    pengisian_selesai,
                    reviu_mulai,
                    reviu_selesai,
                }): JadwalPeriodeInput => ({
                    periode_id,
                    periode_revisi,
                    pengisian_mulai,
                    pengisian_selesai,
                    reviu_mulai,
                    reviu_selesai,
                }),
            ),
        }));
        const path = jadwal ? `/jadwal/${jadwal.id}` : '/jadwal';
        form.submit(jadwal ? 'put' : 'post', path, {
            preserveScroll: true,
            // Hanya hasil commit terkonfirmasi yang mengganti seluruh baseline beserta revisinya.
            preserveState: (page) => !page.flash.success,
            onSuccess: (page) => {
                if (!page.flash.success)
                    setFailure(page.flash.error || 'Hasil penyimpanan belum terkonfirmasi. Periksa kalender terbaru.');
            },
            onHttpException: (response) => {
                // Status akhir dapat berasal dari GET redirect setelah mutasi sudah commit.
                if (
                    !recovery.handleHttpException(response, {
                        effectiveMethod: jadwal ? 'put' : 'post',
                        path,
                        mutation: true,
                    })
                ) {
                    setFailure(
                        response.status === 403
                            ? 'Hasil penyimpanan belum terkonfirmasi karena akses ditolak. Periksa izin dan kalender terbaru sebelum mencoba kembali.'
                            : 'Hasil penyimpanan belum terkonfirmasi. Periksa kalender terbaru.',
                    );
                }
                return false;
            },
            onNetworkError: () => {
                unknown();
                return false;
            },
            onCancel: unknown,
            onFinish: () => {
                submitting.current = false;
            },
        });
    };

    const addPeriode = (option: PeriodeOption) => {
        form.clearErrors();
        form.setData((data) => ({
            ...data,
            periode: [
                ...data.periode,
                {
                    periode_id: option.id,
                    periode_revisi: option.revisi,
                    nama: option.nama,
                    urutan: option.urutan,
                    aktif: option.aktif,
                    is_nilai_akhir: option.is_nilai_akhir,
                    pengisian_mulai: '',
                    pengisian_selesai: '',
                    reviu_mulai: '',
                    reviu_selesai: '',
                },
            ].sort((a, b) => a.urutan - b.urutan || a.periode_id.localeCompare(b.periode_id)),
        }));
        setPicker(null);
    };
    const removePeriode = (id: string) => {
        const index = form.data.periode.findIndex((item) => item.periode_id === id);
        const next = form.data.periode[index + 1] ?? form.data.periode[index - 1];
        form.clearErrors();
        form.setData((data) => ({ ...data, periode: data.periode.filter((item) => item.periode_id !== id) }));
        document.getElementById(next ? `periode-${next.periode_id}-pengisian_mulai` : 'tambah-periode')?.focus();
    };

    return (
        <AuthenticatedLayout
            title={title}
            breadcrumbs={[
                { label: 'Jadwal Tahunan', href: '/jadwal' },
                { label: jadwal ? `Tahun ${jadwal.tahun}` : 'Susun jadwal' },
            ]}
        >
            <Head title={title} />
            <p className="mb-5 text-sm text-muted">Tetapkan kalender pengisian dan review untuk tahun pelaporan.</p>
            <nav aria-label="Pengelolaan kalender" className="mb-6 flex gap-6 border-b border-border text-sm">
                <Link href="/jadwal" className="border-b-2 border-primary pb-3 font-semibold text-primary">
                    Jadwal tahunan
                </Link>
                {auth.can.periode && (
                    <Link href="/periode" className="pb-3 text-muted hover:text-primary">
                        Master periode
                    </Link>
                )}
            </nav>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {failure && !recovery.recovery && (
                <div
                    role="alert"
                    className="mb-5 space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm"
                >
                    <p>{failure}</p>
                    <p>Salin input yang ingin dipertahankan sebelum memuat data terbaru.</p>
                    <Link href={authoritativeUrl} className={linkClass}>
                        Periksa kalender terbaru
                    </Link>
                </div>
            )}
            {read_only_reason && (
                <div className="mb-5 rounded-lg border border-border bg-soft p-4 text-sm">
                    <p className="font-semibold">Kalender hanya dapat dibaca</p>
                    <p className="mt-1">{read_only_reason}</p>
                </div>
            )}
            {jadwal?.status === 'draft' && form.data.periode.some((item) => !item.aktif) && (
                <div className="mb-5 rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm">
                    <p className="font-semibold">Ada periode nonaktif pada draft</p>
                    <p className="mt-1">
                        Referensi tetap ditampilkan. Ganti atau hapus periode nonaktif sebelum aktivasi; kewajiban dan
                        riwayat jadwal aktif/ditutup tetap dipertahankan.
                    </p>
                </div>
            )}
            <form onSubmit={submit} noValidate className="space-y-5">
                {form.hasErrors && (
                    <div
                        ref={summaryRef}
                        tabIndex={-1}
                        role="alert"
                        className="rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm focus:outline-none focus:ring-2 focus:ring-danger/30"
                    >
                        <p className="font-semibold">Periksa data kalender</p>
                        <ul className="mt-2 list-inside list-disc space-y-1">
                            {Object.entries(errors).map(([field, message]) => (
                                <li key={field}>{message}</li>
                            ))}
                        </ul>
                        <Link href={authoritativeUrl} className="mt-2 inline-block text-primary underline">
                            Periksa jadwal yang sudah tersimpan
                        </Link>
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <div>
                            <h2 className="font-semibold">Informasi tahunan</h2>
                            <p className="mt-1 text-xs text-muted">
                                Kalender resmi untuk satu Renstra dan tahun pelaporan.
                            </p>
                        </div>
                        <Badge variant={jadwal?.status === 'aktif' ? 'success' : 'muted'}>
                            {jadwal?.status === 'aktif' ? 'Aktif' : jadwal?.status === 'ditutup' ? 'Ditutup' : 'Draft'}
                        </Badge>
                    </CardHeader>
                    <CardContent className="p-4 sm:p-6">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(90px,.4fr)_minmax(0,1fr)]">
                            <div className="col-span-2 min-w-0 lg:col-span-1">
                                <label htmlFor="pilih-renstra" className="mb-1.5 block text-sm font-medium">
                                    Renstra{' '}
                                    <span className="text-danger" aria-hidden="true">
                                        *
                                    </span>
                                </label>
                                <button
                                    id="pilih-renstra"
                                    type="button"
                                    aria-label={
                                        renstra ? `Renstra: ${renstra.nama} · ${renstra.status}` : 'Pilih Renstra'
                                    }
                                    disabled={disabled}
                                    onClick={() => setPicker('renstra')}
                                    aria-invalid={Boolean(errors.renstra_id)}
                                    aria-describedby={
                                        errors.renstra_id ? 'renstra-error' : renstra ? 'renstra-context' : undefined
                                    }
                                    className="min-h-[42px] w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-left text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:bg-soft"
                                >
                                    {renstra ? `${renstra.nama} · ${renstra.status}` : 'Pilih Renstra'}
                                </button>
                                {errors.renstra_id ? (
                                    <p id="renstra-error" className="mt-1 text-xs text-danger">
                                        {errors.renstra_id}
                                    </p>
                                ) : (
                                    renstra && (
                                        <p id="renstra-context" className="mt-1 text-xs text-muted">
                                            Rentang Renstra {renstra.tahun_mulai}–{renstra.tahun_selesai}
                                        </p>
                                    )
                                )}
                                {!jadwal && renstra && form.data.tahun && (
                                    <Link
                                        href={authoritativeUrl}
                                        className="mt-2 inline-block rounded text-xs text-primary underline focus:ring-2 focus:ring-primary/30"
                                    >
                                        Periksa jadwal Renstra dan tahun ini
                                    </Link>
                                )}
                            </div>
                            <Input
                                name="tahun"
                                label="Tahun"
                                type="number"
                                required
                                autoComplete="off"
                                value={form.data.tahun}
                                onChange={(event) => form.setData('tahun', event.target.value)}
                                error={errors.tahun}
                                disabled={disabled}
                            />
                            <Input
                                name="penutupan"
                                label="Penutupan"
                                type="date"
                                required
                                className="min-w-0 px-2 text-xs sm:text-sm"
                                value={form.data.penutupan}
                                onChange={(event) => form.setData('penutupan', event.target.value)}
                                error={errors.penutupan}
                                disabled={disabled}
                            />
                        </div>
                        <div className="mt-5 grid grid-cols-2 items-end gap-4 border-t border-border pt-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.4fr)]">
                            <Input
                                name="rencana_aksi_mulai"
                                label="Rencana Aksi mulai"
                                type="date"
                                required
                                className="min-w-0 px-2 text-xs sm:text-sm"
                                value={form.data.rencana_aksi_mulai}
                                onChange={(event) => form.setData('rencana_aksi_mulai', event.target.value)}
                                error={errors.rencana_aksi_mulai}
                                disabled={disabled}
                            />
                            <Input
                                name="rencana_aksi_selesai"
                                label="Rencana Aksi selesai"
                                type="date"
                                required
                                className="min-w-0 px-2 text-xs sm:text-sm"
                                value={form.data.rencana_aksi_selesai}
                                onChange={(event) => form.setData('rencana_aksi_selesai', event.target.value)}
                                error={errors.rencana_aksi_selesai}
                                disabled={disabled}
                            />
                            <p className="col-span-2 text-xs leading-relaxed text-muted lg:col-span-1">
                                <strong className="font-medium text-ink">Lengkapi kalender sebelum menyimpan.</strong>
                                <br />
                                RA selesai sebelum pengisian pertama. Penutupan setelah seluruh jendela.
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <div>
                            <h2 className="font-semibold">Jendela periode</h2>
                            <p className="mt-1 text-xs text-muted">
                                Periksa pengisian dan review seluruh periode dalam satu tampilan.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Badge>{form.data.periode.length} periode</Badge>
                            {editable && (
                                <Button
                                    id="tambah-periode"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={disabled}
                                    onClick={() => setPicker('periode')}
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Tambah periode
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <Table className="block w-full text-left text-sm lg:table lg:table-fixed">
                        <caption className="sr-only">Jendela pengisian dan review setiap periode</caption>
                        <thead className="hidden text-xs text-muted lg:table-header-group">
                            <tr className="bg-soft">
                                <th scope="col" rowSpan={2} className="w-[19%] px-5 py-3 font-medium">
                                    Periode
                                </th>
                                <th scope="colgroup" colSpan={2} className="px-3 py-3 font-medium">
                                    Pengisian
                                </th>
                                <th scope="colgroup" colSpan={2} className="px-3 py-3 font-medium">
                                    Jendela Review
                                </th>
                            </tr>
                            <tr className="border-b border-border">
                                <th scope="col" className="px-3 py-2 font-medium">
                                    Mulai
                                </th>
                                <th scope="col" className="px-3 py-2 font-medium">
                                    Selesai
                                </th>
                                <th scope="col" className="px-3 py-2 font-medium">
                                    Mulai Review
                                </th>
                                <th scope="col" className="px-3 py-2 font-medium">
                                    Target Selesai Review
                                </th>
                            </tr>
                        </thead>
                        <tbody className="block divide-y divide-border lg:table-row-group">
                            {form.data.periode.length === 0 && (
                                <tr className="block lg:table-row">
                                    <td colSpan={5} className="block p-6 text-center text-muted lg:table-cell">
                                        Belum ada periode dipilih.
                                        {editable && ' Tambahkan periode untuk menyusun jendelanya.'}
                                    </td>
                                </tr>
                            )}
                            {form.data.periode.map((item, index) => (
                                <tr
                                    key={item.periode_id}
                                    className={`grid grid-cols-2 gap-3 p-4 lg:table-row ${item.is_nilai_akhir ? 'bg-secondary/5' : ''}`}
                                >
                                    <td className="col-span-2 min-w-0 lg:px-5 lg:py-4">
                                        <span className="block break-words font-semibold">{item.nama}</span>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {item.is_nilai_akhir && (
                                                <Badge variant="secondary" size="sm">
                                                    Nilai akhir
                                                </Badge>
                                            )}
                                            {!item.aktif && (
                                                <Badge variant="warning" size="sm">
                                                    Nonaktif
                                                </Badge>
                                            )}
                                        </div>
                                        {editable && (
                                            <button
                                                type="button"
                                                aria-label={`Hapus ${item.nama} dari draft`}
                                                disabled={disabled}
                                                onClick={() => removePeriode(item.periode_id)}
                                                className="mt-2 inline-flex items-center gap-1 rounded py-1 text-xs text-danger hover:underline focus:outline-none focus:ring-2 focus:ring-danger/30 disabled:opacity-50"
                                            >
                                                <Trash2 className="h-3 w-3" aria-hidden="true" />
                                                Hapus dari draft
                                            </button>
                                        )}
                                    </td>
                                    {windowFields.map(({ key, label }) => (
                                        <td key={key} className="min-w-0 lg:px-2 lg:py-4">
                                            <Input
                                                id={`periode-${item.periode_id}-${key}`}
                                                name={`periode.${index}.${key}`}
                                                type="date"
                                                label={label}
                                                labelClassName="text-xs lg:sr-only"
                                                aria-label={`${item.nama}: ${label}`}
                                                required
                                                className="min-w-0 px-2 text-xs xl:text-sm"
                                                value={item[key]}
                                                disabled={disabled}
                                                error={errors[`periode.${index}.${key}`]}
                                                onChange={(event) =>
                                                    form.setData((data) => ({
                                                        ...data,
                                                        periode: data.periode.map((period) =>
                                                            period.periode_id === item.periode_id
                                                                ? { ...period, [key]: event.target.value }
                                                                : period,
                                                        ),
                                                    }))
                                                }
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                    <div className="flex items-start gap-2 border-t border-border px-4 py-4 text-xs leading-relaxed text-muted sm:px-6">
                        <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                        <p>
                            Hanya periode terakhir boleh masuk tahun berikutnya. Akhir review adalah target; Perencanaan
                            dapat menyelesaikan review sampai penutupan.
                        </p>
                    </div>
                </Card>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-muted">Seluruh periode yang dipilih harus lengkap.</p>
                    <div className="flex items-center justify-end gap-3">
                        <Link href="/jadwal" className={linkClass}>
                            Kembali
                        </Link>
                        {editable && (
                            <Button type="submit" disabled={disabled} isLoading={form.processing}>
                                <Check className="h-4 w-4" aria-hidden="true" />
                                Simpan draft
                            </Button>
                        )}
                    </div>
                </div>
            </form>
            {picker === 'renstra' && (
                <OptionPicker<JadwalRenstraOption>
                    kind="renstra"
                    onClose={() => setPicker(null)}
                    describe={(option) => `${option.tahun_mulai}–${option.tahun_selesai} · ${option.status}`}
                    onSelect={(option) => {
                        setRenstra(option);
                        form.setData('renstra_id', option.id);
                        setPicker(null);
                    }}
                />
            )}
            {picker === 'periode' && (
                <OptionPicker<PeriodeOption>
                    kind="periode"
                    onClose={() => setPicker(null)}
                    describe={(option) =>
                        `Urutan ${option.urutan}${option.is_nilai_akhir ? ' · Nilai akhir, diisi manual' : ''}`
                    }
                    selectedIds={form.data.periode.map((item) => item.periode_id)}
                    onSelect={addPeriode}
                />
            )}
        </AuthenticatedLayout>
    );
}
