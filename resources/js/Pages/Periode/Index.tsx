import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LockKeyhole, Plus } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { FinalPeriodeReplacement } from '@/Components/Periode/FinalPeriodeReplacement';
import { PeriodeFields, type PeriodeFormValues } from '@/Components/Periode/PeriodeFields';
import { Select } from '@/Components/Select';
import { Table } from '@/Components/Table';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { SharedPageProps } from '@/types/auth';
import type { PeriodeIndexProps, PeriodeSummary } from '@/types/periode-jadwal';

interface PeriodeForm extends PeriodeFormValues {
    revisi?: number;
}

export default function PeriodeIndex({ periode, current_final, filters, can }: PeriodeIndexProps) {
    const { auth } = usePage<SharedPageProps>().props;
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status ?? '');
    const [editing, setEditing] = useState<PeriodeSummary | null>(null);
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

    useEffect(() => {
        if (hasErrors) summaryRef.current?.focus();
    }, [hasErrors, errors]);

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
            <nav aria-label="Pengelolaan kalender" className="mb-6 flex gap-6 border-b border-border text-sm">
                {auth.can.jadwal && (
                    <Link href="/jadwal" className="pb-3 text-muted hover:text-primary">
                        Jadwal tahunan
                    </Link>
                )}
                <Link
                    href="/periode"
                    aria-current="page"
                    className="border-b-2 border-primary pb-3 font-semibold text-primary"
                >
                    Master periode
                </Link>
            </nav>
            <div className="space-y-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h2 className="font-semibold">Periode pelaporan global</h2>
                        <p className="mt-1 text-sm text-muted">
                            Digunakan lintas Renstra dan tahun. Satu periode aktif ditetapkan sebagai nilai akhir.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.replaceFinal && current_final && (
                            <Button type="button" variant="outline" disabled={busy || blocked} onClick={openSwap}>
                                Ganti periode nilai akhir
                            </Button>
                        )}
                        {can.create && (
                            <Button type="button" disabled={busy || blocked} onClick={() => openForm(null)}>
                                <Plus className="h-4 w-4" aria-hidden="true" />
                                Tambah periode
                            </Button>
                        )}
                    </div>
                </div>
                {!formOpen && !swap && feedback}
                <Card>
                    <CardContent className="p-4">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    '/periode',
                                    { q: query, status, sort: filters.sort },
                                    { preserveState: false, replace: true },
                                );
                            }}
                            className="flex flex-col items-end gap-3 sm:flex-row"
                        >
                            <Input
                                label="Cari periode"
                                maxLength={100}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                autoComplete="off"
                            />
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
                            <Button type="submit" variant="outline" className="shrink-0">
                                Terapkan filter
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <Table className="block w-full text-left text-sm md:table">
                        <caption className="sr-only">Master periode dan status penguncian metadata</caption>
                        <thead className="hidden bg-soft text-xs text-muted md:table-header-group">
                            <tr>
                                {['Nama periode', 'Urutan', 'Status', 'Nilai akhir', 'Metadata', 'Aksi'].map(
                                    (label) => (
                                        <th key={label} scope="col" className="px-4 py-3 font-medium">
                                            {label}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody className="block divide-y divide-border md:table-row-group">
                            {periode.data.length === 0 && (
                                <tr className="block md:table-row">
                                    <td colSpan={6} className="block px-4 py-10 text-center text-muted md:table-cell">
                                        Tidak ada periode ditemukan.
                                    </td>
                                </tr>
                            )}
                            {periode.data.map((item) => (
                                <tr key={item.id} className="grid grid-cols-2 gap-3 p-4 md:table-row">
                                    <td className="col-span-2 break-words font-semibold md:max-w-64 md:px-4 md:py-4">
                                        {item.nama}
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        <span className="mr-2 text-xs text-muted md:hidden">Urutan</span>
                                        {item.urutan}
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        <Badge variant={item.aktif ? 'success' : 'warning'}>
                                            {item.aktif ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        {item.is_nilai_akhir ? (
                                            <Badge variant="secondary">
                                                {item.aktif ? 'Ya · Manual' : 'Ya · Riwayat'}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted">
                                                <span className="md:hidden">Nilai akhir: </span>—
                                            </span>
                                        )}
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        {item.metadata_locked ? (
                                            <>
                                                <Badge>
                                                    <LockKeyhole className="h-3 w-3" aria-hidden="true" />
                                                    Terkunci
                                                </Badge>
                                                <p className="mt-1 text-xs text-muted">{item.metadata_locked_reason}</p>
                                            </>
                                        ) : (
                                            <Badge>Dapat diubah</Badge>
                                        )}
                                    </td>
                                    <td className="col-span-2 md:px-4 md:py-4">
                                        {can.update && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={blocked || busy}
                                                onClick={() => openForm(item)}
                                                aria-label={`${item.metadata_locked ? 'Koreksi nama' : 'Edit'} ${item.nama}`}
                                            >
                                                {item.metadata_locked ? 'Koreksi nama' : 'Edit'}
                                            </Button>
                                        )}
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
        </AuthenticatedLayout>
    );
}
