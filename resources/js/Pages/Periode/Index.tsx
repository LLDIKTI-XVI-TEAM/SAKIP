import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeftRight, Eye, Layers, LockKeyhole, Pencil, Plus, RotateCcw, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Tooltip } from '@/Components/Tooltip';
import { FinalPeriodeReplacement } from '@/Components/Periode/FinalPeriodeReplacement';
import { PeriodeFields, type PeriodeFormValues } from '@/Components/Periode/PeriodeFields';
import { Select } from '@/Components/Select';
import { Table } from '@/Components/Table';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { PeriodeIndexProps, PeriodeSummary } from '@/types/periode-jadwal';

interface PeriodeForm extends PeriodeFormValues {
    revisi?: number;
}

export default function PeriodeIndex({ periode, current_final, filters, can }: PeriodeIndexProps) {
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status ?? '');
    const [editing, setEditing] = useState<PeriodeSummary | null>(null);
    const [detail, setDetail] = useState<PeriodeSummary | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [swap, setSwap] = useState<{
        current: NonNullable<PeriodeIndexProps['current_final']>;
        candidates: PeriodeSummary[];
    } | null>(null);
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const submitting = useRef(false);
    const summaryRef = useRef<HTMLDivElement>(null);
    const form = useForm<PeriodeForm>({ nama: '', urutan: '', aktif: true, is_nilai_akhir: false });
    const swapForm = useForm({ periode_lama_id: '', revisi_lama: 0, periode_pengganti_id: '', revisi_pengganti: 0 });
    const busy = form.processing || swapForm.processing;
    const blocked = Boolean(failure || recovery.recovery);
    const errors: Record<string, string | undefined> = formOpen ? form.errors : swapForm.errors;
    const hasErrors = formOpen ? form.hasErrors : swapForm.hasErrors;
    const hasActiveFilters = Boolean(query.trim() || status || filters.q || filters.status);

    useEffect(() => {
        if (hasErrors) summaryRef.current?.focus();
    }, [hasErrors, errors]);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/periode',
            { q: query, status, sort: filters.sort },
            { preserveState: false, replace: true },
        );
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('');
        router.get(
            '/periode',
            { q: '', status: '', sort: filters.sort },
            { preserveState: false, replace: true },
        );
    };

    const openForm = (item: PeriodeSummary | null) => {
        if (busy || blocked) return;
        setEditing(item);
        form.setData(
            item
                ? {
                      nama: item.nama,
                      urutan: String(item.urutan),
                      aktif: item.aktif,
                      is_nilai_akhir: item.is_nilai_akhir,
                      revisi: item.revisi,
                  }
                : { nama: '', urutan: '', aktif: true, is_nilai_akhir: current_final === null },
        );
        form.clearErrors();
        setFormOpen(true);
    };
    const openSwap = () => {
        if (!current_final || busy || blocked) return;
        const candidates = periode.data.filter((item) => item.id !== current_final.id && !item.metadata_locked);
        setSwap({ current: current_final, candidates });
        swapForm.clearErrors();
        swapForm.setData({
            periode_lama_id: current_final.id,
            revisi_lama: current_final.revisi,
            periode_pengganti_id: candidates[0]?.id ?? '',
            revisi_pengganti: candidates[0]?.revisi ?? 0,
        });
    };
    const close = () => {
        if (busy || submitting.current) return;
        setFormOpen(false);
        setSwap(null);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (busy || submitting.current || blocked) return;
        submitting.current = true;
        const path = swap ? '/periode/ganti-nilai-akhir' : editing ? `/periode/${editing.id}` : '/periode';
        const method = !swap && editing ? 'put' : 'post';
        const activeForm = swap ? swapForm : form;
        activeForm.submit(method, path, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.flash.success) {
                    const savedFilters = page.props.filters as PeriodeIndexProps['filters'];
                    setQuery(savedFilters.q);
                    setStatus(savedFilters.status ?? '');
                    setFormOpen(false);
                    setSwap(null);
                } else setFailure(page.flash.error || 'Hasil penyimpanan belum terkonfirmasi. Periksa master terbaru.');
            },
            onHttpException: (response) => {
                // Status akhir dapat berasal dari GET redirect setelah mutasi sudah commit.
                if (!recovery.handleHttpException(response, { effectiveMethod: method, path, mutation: true }))
                    setFailure(
                        response.status === 403
                            ? 'Hasil penyimpanan belum terkonfirmasi karena akses ditolak. Periksa izin dan master terbaru sebelum mencoba kembali.'
                            : 'Hasil penyimpanan belum terkonfirmasi. Periksa master terbaru.',
                    );
                return false;
            },
            onNetworkError: () => {
                setFailure('Hasil penyimpanan belum terkonfirmasi. Periksa master terbaru sebelum mencoba kembali.');
                return false;
            },
            onCancel: () =>
                setFailure('Hasil penyimpanan belum terkonfirmasi. Periksa master terbaru sebelum mencoba kembali.'),
            onFinish: () => {
                submitting.current = false;
            },
        });
    };
    const feedback = (
        <>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={busy} />
            {failure && !recovery.recovery && (
                <div role="alert" className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
                    <p>{failure}</p>
                    <p>Salin input yang ingin dipertahankan sebelum memuat data terbaru.</p>
                    <Link
                        href="/periode"
                        className="inline-block rounded text-primary underline focus:ring-2 focus:ring-primary"
                    >
                        Periksa master terbaru
                    </Link>
                </div>
            )}
            {hasErrors && (
                <div
                    ref={summaryRef}
                    role="alert"
                    tabIndex={-1}
                    className="rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm focus:outline-none focus:ring-2 focus:ring-danger/30"
                >
                    <p className="font-semibold">Periksa perubahan periode</p>
                    <ul className="mt-2 list-inside list-disc">
                        {Object.entries(errors).map(([field, message]) => (
                            <li key={field}>{message}</li>
                        ))}
                    </ul>
                    <Link href="/periode" className="mt-2 inline-block text-primary underline">
                        Muat master terbaru jika data sudah berubah
                    </Link>
                </div>
            )}
        </>
    );

    return (
        <AuthenticatedLayout title="Master periode" breadcrumbs={[{ label: 'Master periode' }]}>
            <Head title="Master periode" />
            <div className="space-y-5">
                {!formOpen && !swap && feedback}

                <Card className="overflow-hidden border border-border bg-surface shadow-xs">
                    {/* Unified Header & Action Bar */}
                    <div className="flex flex-col gap-3.5 border-b border-border/80 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-4">
                        <div className="min-w-0 space-y-1.5">
                            <p className="text-sm text-muted">
                                Periode pelaporan global lintas Renstra dan tahun. Satu periode aktif ditetapkan sebagai
                                nilai akhir.
                            </p>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted">
                                <div className="flex items-center gap-1.5">
                                    <Layers className="h-3.5 w-3.5 text-primary" aria-hidden="true" />
                                    <span className="font-semibold text-ink">Periode Nilai Akhir:</span>
                                    {current_final ? (
                                        <Badge variant="success" size="sm" dot>
                                            {`Aktif (${current_final.nama})`}
                                        </Badge>
                                    ) : (
                                        <Badge variant="muted" size="sm">
                                            Belum ditetapkan
                                        </Badge>
                                    )}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-ink">Total Periode:</span>
                                    <span>{periode.total} Periode</span>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5 shrink-0">
                            {can.replaceFinal && current_final && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={busy || blocked}
                                    onClick={openSwap}
                                    className="h-9 gap-1.5 px-3.5 text-xs font-semibold shadow-xs"
                                >
                                    <ArrowLeftRight className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                    Ganti periode nilai akhir
                                </Button>
                            )}
                            {can.create && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    size="sm"
                                    disabled={busy || blocked}
                                    onClick={() => openForm(null)}
                                    className="h-9 gap-1.5 px-3.5 text-xs font-semibold shadow-xs"
                                >
                                    <Plus className="h-4 w-4 shrink-0" aria-hidden="true" />
                                    Tambah periode
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Unified Filter Toolbar */}
                    <div className="bg-soft/30 p-4 sm:px-5 sm:py-4">
                        <form
                            onSubmit={applyFilters}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <div className="min-w-0 flex-1">
                                <Input
                                    label="Cari periode"
                                    placeholder="Cari nama periode pelaporan..."
                                    maxLength={100}
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    autoComplete="off"
                                />
                            </div>
                            <div className="w-full sm:w-52 shrink-0">
                                <Select
                                    label="Status"
                                    value={status}
                                    onChange={(event) => setStatus(event.target.value)}
                                    options={[
                                        { value: '', label: 'Semua status' },
                                        { value: 'aktif', label: 'Aktif' },
                                        { value: 'nonaktif', label: 'Nonaktif' },
                                    ]}
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-2 shrink-0">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="md"
                                    className="h-[42px] gap-1.5 px-4 shrink-0"
                                >
                                    <Search className="h-4 w-4" aria-hidden="true" />
                                    Terapkan filter
                                </Button>
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="md"
                                        onClick={resetFilters}
                                        className="h-[42px] gap-1.5 px-3.5 text-muted hover:text-ink shrink-0"
                                    >
                                        <RotateCcw className="h-4 w-4" aria-hidden="true" />
                                        Reset filter
                                    </Button>
                                )}
                            </div>
                        </form>
                    </div>
                </Card>
                <Card>
                    <Table wrapperClassName="overflow-x-visible" className="block w-full text-left text-sm md:table">
                        <caption className="sr-only">Master periode ringkas</caption>
                        <thead className="hidden bg-soft text-xs text-muted md:table-header-group">
                            <tr>
                                {['Nama periode', 'Urutan', 'Status', 'Aksi'].map((label) => (
                                    <th
                                        key={label}
                                        scope="col"
                                        className={`px-4 py-3 font-medium ${label === 'Aksi' ? 'text-right' : ''}`}
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="block divide-y divide-border md:table-row-group">
                            {periode.data.length === 0 && (
                                <tr className="block md:table-row">
                                    <td colSpan={4} className="block px-4 py-10 text-center text-muted md:table-cell">
                                        Tidak ada periode ditemukan.
                                    </td>
                                </tr>
                            )}
                            {periode.data.map((item) => (
                                <tr key={item.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                    <td className="col-span-2 min-w-0 break-words font-semibold md:max-w-64 md:px-4 md:py-4">
                                        {item.nama}
                                    </td>
                                    <td className="min-w-0 break-words md:px-4 md:py-4">
                                        <span className="mr-2 text-xs text-muted md:hidden">Urutan</span>
                                        {item.urutan}
                                    </td>
                                    <td className="min-w-0 md:px-4 md:py-4">
                                        <Badge variant={item.aktif ? 'success' : 'warning'}>
                                            {item.aktif ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td className="col-span-2 min-w-0 md:px-4 md:py-4 md:text-right">
                                        <div className="flex min-w-0 flex-wrap items-center justify-end gap-1.5">
                                            <Tooltip content="Lihat detail" align="right">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setDetail(item)}
                                                    aria-label={`Lihat detail ${item.nama}`}
                                                    className="h-8 w-8 p-0"
                                                >
                                                    <Eye className="h-4 w-4" aria-hidden="true" />
                                                </Button>
                                            </Tooltip>
                                            {can.update && (
                                                <Tooltip
                                                    content={item.metadata_locked ? 'Koreksi nama' : 'Edit'}
                                                    align="right"
                                                >
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={blocked || busy}
                                                        onClick={() => openForm(item)}
                                                        aria-label={`${item.metadata_locked ? 'Koreksi nama' : 'Edit'} ${item.nama}`}
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Pencil className="h-4 w-4" aria-hidden="true" />
                                                    </Button>
                                                </Tooltip>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                    {periode.last_page > 1 && (
                        <nav
                            aria-label="Halaman master periode"
                            className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-xs text-muted"
                        >
                            <span>
                                {periode.from ?? 0}–{periode.to ?? 0} dari {periode.total} periode
                            </span>
                            <div className="flex flex-wrap gap-1">
                                {periode.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            aria-current={link.active ? 'page' : undefined}
                                            className={`rounded px-3 py-2 focus:ring-2 focus:ring-primary ${link.active ? 'bg-primary text-white' : 'hover:bg-soft'}`}
                                        >
                                            {link.label
                                                .replace('&laquo;', '‹')
                                                .replace('&raquo;', '›')
                                                .replace('Previous', 'Sebelumnya')
                                                .replace('Next', 'Berikutnya')}
                                        </Link>
                                    ) : null,
                                )}
                            </div>
                        </nav>
                    )}
                </Card>
                <p className="text-xs leading-relaxed text-muted">
                    Nama dapat dikoreksi dengan audit. Urutan dan penanda nilai akhir dikunci setelah dipakai jadwal
                    aktif/ditutup. Nilai akhir diisi manual, bukan agregasi otomatis.
                </p>
                <p className="text-xs leading-relaxed text-muted">
                    Periode nonaktif tidak tersedia untuk penambahan baru. Referensi pada draft tetap terlihat dan perlu
                    diganti/dihapus sebelum aktivasi; kewajiban dan riwayat jadwal aktif/ditutup tetap dipertahankan.
                </p>
            </div>
            <Modal
                isOpen={formOpen || Boolean(swap)}
                onClose={close}
                title={
                    swap
                        ? 'Ganti periode nilai akhir'
                        : editing?.metadata_locked
                          ? 'Koreksi nama periode'
                          : editing
                            ? 'Edit periode'
                            : 'Tambah periode'
                }
                size="lg"
                footer={
                    <>
                        <Button type="button" variant="outline" onClick={close} disabled={busy}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="periode-form"
                            disabled={busy || blocked || Boolean(swap && !swapForm.data.periode_pengganti_id)}
                            isLoading={busy}
                        >
                            {swap ? 'Simpan pergantian' : editing ? 'Simpan perubahan' : 'Simpan periode'}
                        </Button>
                    </>
                }
            >
                <form id="periode-form" onSubmit={submit} noValidate className="space-y-4">
                    {feedback}
                    {swap ? (
                        <FinalPeriodeReplacement
                            current={swap.current}
                            candidates={swap.candidates}
                            value={swapForm.data.periode_pengganti_id}
                            errors={errors}
                            disabled={busy || blocked}
                            onChange={(periodeId) => {
                                const item = swap.candidates.find((candidate) => candidate.id === periodeId);
                                if (item)
                                    swapForm.setData((data) => ({
                                        ...data,
                                        periode_pengganti_id: item.id,
                                        revisi_pengganti: item.revisi,
                                    }));
                            }}
                        />
                    ) : (
                        <PeriodeFields
                            values={form.data}
                            errors={errors}
                            editing={editing}
                            currentFinal={current_final}
                            disabled={busy || blocked}
                            onChange={(field, value) => form.setData((data) => ({ ...data, [field]: value }))}
                        />
                    )}
                </form>
            </Modal>
            <Modal
                isOpen={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={detail ? `Detail periode ${detail.nama}` : 'Detail periode'}
                size="md"
                footer={
                    <Button type="button" variant="outline" onClick={() => setDetail(null)}>
                        Tutup
                    </Button>
                }
            >
                {detail && (
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold text-muted">Nama periode</dt>
                            <dd className="mt-1 text-sm font-semibold text-ink">{detail.nama}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold text-muted">Urutan</dt>
                            <dd className="mt-1 text-sm text-ink">{detail.urutan}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold text-muted">Status aktif</dt>
                            <dd className="mt-1">
                                <Badge variant={detail.aktif ? 'success' : 'warning'}>
                                    {detail.aktif ? 'Aktif' : 'Nonaktif'}
                                </Badge>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold text-muted">Nilai akhir · Diisi manual</dt>
                            <dd className="mt-1">
                                {detail.is_nilai_akhir ? (
                                    <Badge variant="secondary">
                                        {detail.aktif ? 'Ya · Manual' : 'Ya · Riwayat'}
                                    </Badge>
                                ) : (
                                    <span className="text-sm text-muted">—</span>
                                )}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs font-semibold text-muted">Metadata</dt>
                            <dd className="mt-1">
                                {detail.metadata_locked ? (
                                    <>
                                        <Badge>
                                            <LockKeyhole className="h-3 w-3" aria-hidden="true" />
                                            Terkunci
                                        </Badge>
                                        <p className="mt-1 text-xs leading-relaxed text-muted">
                                            {detail.metadata_locked_reason}
                                        </p>
                                    </>
                                ) : (
                                    <Badge>Dapat diubah</Badge>
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold text-muted">Revisi</dt>
                            <dd className="mt-1 text-sm text-ink">{detail.revisi}</dd>
                        </div>
                    </dl>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
