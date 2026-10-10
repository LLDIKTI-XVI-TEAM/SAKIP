import { useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { ArrowLeft } from 'lucide-react';
import { Input } from '@/Components/Input';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { GrantUserAutocomplete } from '@/Components/Access/GrantUserAutocomplete';
import { WorkReadinessList } from '@/Components/Access/WorkReadinessList';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { AssignmentDetailProps, WorkReadiness } from '@/types/penanggung-jawab';

function validReadiness(value: unknown): value is WorkReadiness & { user_id: string; unit_id: string } {
    if (!value || typeof value !== 'object') return false;
    const row = value as Record<string, unknown>;
    return typeof row.user_id === 'string' && typeof row.unit_id === 'string' && typeof row.complete === 'boolean'
        && Array.isArray(row.missing) && row.missing.every((item) => typeof item === 'string')
        && Array.isArray(row.available) && row.available.every((item) => typeof item === 'string')
        && Array.isArray(row.permissions) && row.permissions.length === 7 && row.permissions.every((item: unknown) => {
            if (!item || typeof item !== 'object') return false;
            const permission = item as Record<string, unknown>;
            return typeof permission.permission === 'string' && typeof permission.label === 'string'
                && typeof permission.allowed === 'boolean' && typeof permission.reason === 'string';
        });
}
export default function Show(props: AssignmentDetailProps) {
    return <AssignmentDetail key={props.indicator.id} {...props} />;
}
function AssignmentDetail({ indicator, unit, renstra, effective, readiness, history, has_history, expected_state, tanggal_acuan, today, blocked_reason, can }: AssignmentDetailProps) {
    const formatTanggal = useFormatTanggal();
    // Halaman ini cukup penanggung_jawab:update; daftar & detail indikator butuh indikator:read (bisa dicabut eksplisit).
    const bolehBacaIndikator = usePage<SharedPageProps>().props.auth.can.sasaranIndikator === true;
    const base = `/perencanaan/indikator/${indicator.id}/penanggung-jawab`;
    const form = useForm({ user_id: '', tanggal_mulai_berlaku: today, alasan: '', expected_state });
    const [reference, setReference] = useState(tanggal_acuan);
    const [modal, setModal] = useState(false);
    const [preview, setPreview] = useState<WorkReadiness | null>(null);
    const [previewStatus, setPreviewStatus] = useState('');
    const [failure, setFailure] = useState('');
    const [unknownOutcome, setUnknownOutcome] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const recovery = useAuthRecovery();
    const selectedUserId = form.data.user_id;
    const unitId = unit?.id;
    useEffect(() => {
        setPreview(null);
        if (!selectedUserId || !unitId) { setPreviewStatus(''); return; }
        const controller = new AbortController();
        setPreviewStatus('Memeriksa izin kerja…');
        void fetch(`${base}/hak-kerja?user_id=${encodeURIComponent(selectedUserId)}`, { signal: controller.signal, headers: { Accept: 'application/json' } })
            .then(async (response) => {
                if (!response.ok) throw new Error('Izin kerja belum dapat diperiksa. Penugasan tetap dapat disimpan.');
                const data: unknown = await response.json();
                if (controller.signal.aborted) return;
                if (!validReadiness(data) || data.user_id !== selectedUserId || data.unit_id !== unitId) throw new Error('Hasil pemeriksaan izin tidak sesuai. Pilih ulang pengguna.');
                setPreview(data);
                setPreviewStatus('');
            })
            .catch((error: unknown) => { if (!controller.signal.aborted) setPreviewStatus(error instanceof Error ? error.message : 'Izin kerja belum dapat diperiksa.'); });
        return () => controller.abort();
    }, [base, selectedUserId, unitId]);
    const submit = () => {
        if (form.processing || unknownOutcome || recovery.recovery || !can.assign || refreshing) return;
        const path = has_history ? `${base}/pergantian` : base;
        setFailure('');
        form.post(path, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.component !== 'PenanggungJawab/Show' || typeof page.props.saved_assignment_id !== 'string' || typeof page.props.expected_state !== 'string') {
                    setFailure('Penyimpanan belum terkonfirmasi. Muat data terbaru sebelum mengulangi.');
                    setUnknownOutcome(true);
                    return;
                }
                form.setData({ user_id: '', tanggal_mulai_berlaku: String(page.props.today), alasan: '', expected_state: page.props.expected_state });
                form.clearErrors();
                setModal(false);
            },
            onError: (errors) => { if (typeof errors.indikator === 'string') setFailure(errors.indikator); },
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) {
                    setFailure(response.status === 403 ? 'Akses telah berubah. Input tetap tersimpan; periksa akses sebelum melanjutkan.' : 'Penyimpanan belum terkonfirmasi. Muat data terbaru sebelum mengulangi.');
                    setUnknownOutcome(true);
                }
                return false;
            },
            onNetworkError: () => {
                setFailure('Koneksi terputus. Hasil penyimpanan belum diketahui. Muat data terbaru dan periksa histori sebelum mengulangi.');
                setUnknownOutcome(true);
                return false;
            },
        });
    };
    const refresh = () => {
        setRefreshing(true);
        router.reload({ only: ['indicator', 'unit', 'renstra', 'effective', 'readiness', 'history', 'has_history', 'expected_state', 'blocked_reason', 'can'],
            onSuccess: (page) => {
                if (typeof page.props.expected_state === 'string') {
                    form.setData('expected_state', page.props.expected_state);
                    form.clearErrors('expected_state');
                    setUnknownOutcome(false);
                    setFailure('Data terbaru dimuat. Periksa PJ efektif dan histori, lalu kirim kembali bila masih diperlukan.');
                }
            },
            onFinish: () => setRefreshing(false),
        });
    };
    const notice = <><AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
        {(failure || form.errors.expected_state) && <div role="alert" className="space-y-3 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
            <p>{form.errors.expected_state ?? failure}</p>
            {!recovery.recovery && <Button type="button" variant="outline" isLoading={refreshing} onClick={refresh} disabled={form.processing}>Muat data terbaru</Button>}
        </div>}</>;
    const disabled = form.processing || refreshing || unknownOutcome || Boolean(recovery.recovery);
    const detailHref = `/perencanaan/indikator/${indicator.id}`;
    const daftarHref = renstra ? `/perencanaan/sasaran-indikator?renstra_id=${renstra.id}` : '/perencanaan/sasaran-indikator';
    const pagerLink = 'inline-flex h-9 items-center justify-center rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';
    return <AuthenticatedLayout title="Penanggung Jawab Indikator" breadcrumbs={[
        { label: 'Perencanaan' },
        { label: 'Sasaran & Indikator', href: bolehBacaIndikator ? daftarHref : undefined },
        { label: indicator.kode, href: bolehBacaIndikator ? detailHref : undefined },
        { label: 'Penanggung Jawab' },
    ]}>
        <Head title={`Penanggung Jawab · ${indicator.kode}`} />
        <div className="mx-auto max-w-5xl space-y-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {bolehBacaIndikator && <Link href={detailHref} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke detail indikator
                </Link>}
                <Link href="/penanggung-jawab" className={`sm:ml-auto ${pagerLink}`}>Monitoring izin kerja</Link>
            </div>

            <Card>
                <CardHeader className="items-start gap-4">
                    <div className="min-w-0">
                        <CardTitle>{indicator.nama}</CardTitle>
                        <p className="mt-1 text-sm text-muted"><span className="font-mono">{indicator.kode}</span> · {unit?.nama ?? 'Unit tidak tersedia'}</p>
                    </div>
                    <Badge variant={indicator.status === 'aktif' ? 'success' : 'muted'} dot className="shrink-0">
                        {indicator.status === 'aktif' ? 'Aktif' : 'Arsip'}
                    </Badge>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold text-muted">PJ efektif per {formatTanggal(tanggal_acuan)}</p>
                            <p className="mt-1 flex flex-wrap items-center gap-2 text-base font-semibold text-ink">
                                {effective?.pic?.nama ?? 'Belum ada PJ efektif'}
                                {effective?.pic && effective.pic.status !== 'aktif' && <Badge variant="muted" size="sm">Nonaktif</Badge>}
                            </p>
                            {effective && <p className="mt-0.5 text-sm text-muted">Mulai berlaku {formatTanggal(effective.tanggal_mulai_berlaku)}</p>}
                        </div>
                        <form className="flex items-end gap-2" onSubmit={(event) => {
                            event.preventDefault(); router.get(base, { tanggal_acuan: reference }, { preserveState: true, preserveScroll: true });
                        }}>
                            <Input label="PJ efektif pada tanggal" type="date" value={reference} required onChange={(event) => setReference(event.target.value)} />
                            <Button type="submit" variant="outline" className="h-[42px] shrink-0">Tampilkan</Button>
                        </form>
                    </div>
                    {readiness && <details className="border-t border-border pt-4">
                        <summary className="cursor-pointer text-sm font-medium text-ink">Izin kerja saat ini: {readiness.available.length}/7 tersedia</summary>
                        <WorkReadinessList readiness={readiness} />
                    </details>}
                </CardContent>
            </Card>

            {blocked_reason && <p role="status" className="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-ink">{blocked_reason} Histori penugasan tetap dapat dibaca.</p>}

            {can.assign && <Card>
                <CardHeader>
                    <CardTitle>{has_history ? 'Ganti penanggung jawab' : 'Tetapkan penanggung jawab'}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); if (has_history) setModal(true); else submit(); }}>
                        <p className="text-sm text-muted">Penugasan tidak otomatis memberikan izin kerja.</p>
                        <div className="grid gap-4 md:grid-cols-2">
                            <GrantUserAutocomplete id="pj-user" label="Penanggung jawab" endpoint="/penanggung-jawab/opsi/pengguna"
                                value={form.data.user_id} onChange={(id) => form.setData('user_id', id)} error={form.errors.user_id} disabled={disabled} />
                            <Input label="Tanggal mulai berlaku" type="date" required value={form.data.tanggal_mulai_berlaku} onChange={(event) => form.setData('tanggal_mulai_berlaku', event.target.value)} error={form.errors.tanggal_mulai_berlaku} disabled={disabled} />
                        </div>
                        {previewStatus && <p role="status" className="text-sm text-muted">{previewStatus}</p>}
                        {preview && <details open className="rounded-lg border border-border bg-soft p-4">
                            <summary className="cursor-pointer text-sm font-semibold text-ink">{preview.complete ? 'Izin kerja lengkap' : `${preview.missing.length} izin kerja belum tersedia`}</summary>
                            <WorkReadinessList readiness={preview} />
                            {!preview.complete && <p className="mt-2 text-sm text-muted">Penetapan tetap diperbolehkan. Perencanaan perlu mengevaluasi hak akses pengguna secara terpisah.</p>}
                        </details>}
                        {!modal && notice}
                        <div className="flex justify-end">
                            <Button type="submit" isLoading={form.processing} disabled={disabled || !form.data.user_id}>
                                {has_history ? 'Lanjutkan pergantian' : 'Tetapkan PJ'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>}

            <Card>
                <CardHeader>
                    <CardTitle>Histori penugasan</CardTitle>
                    <span className="text-xs text-muted">Status per {formatTanggal(tanggal_acuan)}</span>
                </CardHeader>
                <CardContent>
                    {history.data.length === 0 ? <p className="text-sm text-muted">Belum ada penugasan untuk indikator ini.</p> :
                        <ol className="-my-4 divide-y divide-border">{history.data.map((row) => <li key={row.id} className="py-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm font-semibold text-ink">{row.pic?.nama ?? 'Pengguna tidak tersedia'}</p>
                                <Badge size="sm" variant={row.state === 'Efektif' ? 'success' : row.state === 'Terjadwal' ? 'info' : 'muted'}>{row.state}</Badge>
                            </div>
                            <p className="mt-1 text-xs text-muted">Berlaku {formatTanggal(row.tanggal_mulai_berlaku)} · Ditetapkan oleh {row.ditetapkan_oleh?.nama ?? 'Tidak tersedia'}</p>
                            <p className="mt-0.5 text-xs text-muted">Dicatat {row.created_at ? formatTanggal(row.created_at, { withTime: true }) : 'Tidak tersedia'}</p>
                            <p className="mt-2 whitespace-pre-wrap break-words text-sm text-ink">{row.alasan ?? 'Penetapan awal'}</p>
                        </li>)}</ol>}
                    {(history.prev_page_url || history.next_page_url) && <nav aria-label="Halaman histori" className="mt-6 flex justify-between gap-3 border-t border-border pt-4">
                        {history.prev_page_url && <Link href={history.prev_page_url} preserveState preserveScroll className={pagerLink}>‹ Sebelumnya</Link>}
                        {history.next_page_url && <Link href={history.next_page_url} preserveState preserveScroll className={`ml-auto ${pagerLink}`}>Berikutnya ›</Link>}
                    </nav>}
                </CardContent>
            </Card>
        </div>
        <AuditReasonModal open={modal} title="Konfirmasi pergantian PJ" description={`Pergantian untuk ${indicator.kode} berlaku ${formatTanggal(form.data.tanggal_mulai_berlaku)}. Penugasan lama tetap tersimpan.`}
            reason={form.data.alasan} error={form.errors.alasan ?? form.errors.user_id ?? form.errors.tanggal_mulai_berlaku}
            onReasonChange={(reason) => form.setData('alasan', reason)} busy={form.processing} submitDisabled={disabled || !form.data.alasan.trim()}
            notice={notice} confirmLabel="Simpan pergantian" onConfirm={submit} onClose={() => { if (!form.processing) setModal(false); }} />
    </AuthenticatedLayout>;
}
