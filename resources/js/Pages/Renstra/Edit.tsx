import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { SharedPageProps } from '@/types/auth';
import type { RegulasiOption, RenstraDetail, RenstraEditFormData } from '@/types/renstra';

interface EditRenstraProps {
    renstra: RenstraDetail;
    expected_state: string;
    regulasiPilihan: RegulasiOption[];
    can?: { uploadAttachment?: boolean; readRegulasi?: boolean };
}

export default function EditRenstra(props: EditRenstraProps) {
    // Berpindah master membuang state form lama, termasuk File dan token state.
    return <EditRenstraForm key={props.renstra.id} {...props} />;
}

function EditRenstraForm({ renstra, expected_state, regulasiPilihan, can }: EditRenstraProps) {
    const isAktif = renstra.status === 'aktif';
    const [auditOpen, setAuditOpen] = useState(false);
    const [failure, setFailure] = useState('');
    const submitting = useRef(false);
    const recovery = useAuthRecovery();
    const form = useForm<RenstraEditFormData>({
        nama: renstra.nama, kode: renstra.kode,
        tahun_mulai: String(renstra.tahun_mulai), tahun_selesai: String(renstra.tahun_selesai),
        deskripsi: renstra.deskripsi ?? '', dasar_hukum: renstra.dasar_hukum ?? '',
        regulasi_id: renstra.regulasi_id ?? '', alasan: '',
        nomor_kebijakan: '', tanggal_kebijakan: '', expected_state,
        lampiran: [], _method: 'put',
    });
    const errors: Record<string, string | undefined> = form.errors;
    useEffect(() => {
        if (Object.keys(form.errors).length === 0) return;
        const container = document.querySelector(auditOpen ? 'dialog[open]' : 'main form');
        container?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    }, [form.errors, auditOpen]);
    const blocked = Boolean(recovery.recovery || failure || errors.expected_state);
    const unknownOutcome = () => setFailure('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
    const notice = <>
        <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
        {!recovery.recovery && (failure || errors.expected_state) && <div role="alert" className="mb-4 space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
            <p>{failure || errors.expected_state}</p>
            <p>Salin input terlebih dahulu. Memuat data terbaru akan mengosongkan formulir; berkas perlu dipilih kembali.</p>
            <Button type="button" variant="outline" disabled={form.processing} onClick={() => router.get(`/renstra/${renstra.id}/edit`, {}, { preserveState: false })}>Muat data terbaru</Button>
        </div>}
    </>;

    const save = () => {
        if (submitting.current || form.processing || blocked) return;
        submitting.current = true;
        form.transform((data) => {
            if (can?.readRegulasi === true) return data;
            const payload: Partial<RenstraEditFormData> = { ...data };
            delete payload.regulasi_id;
            return payload;
        });
        form.post(`/renstra/${renstra.id}`, {
            forceFormData: true,
            onSuccess: (page) => {
                const flash = page.props.flash as SharedPageProps['flash'];
                const error = page.flash.error ?? flash?.error;
                if (error) { setFailure(error); return; }
                setAuditOpen(false);
            },
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'put', path: `/renstra/${renstra.id}`, mutation: true })) {
                    setFailure(response.status === 403 ? 'Akses perubahan ditolak. Periksa akses dan data terbaru.' : 'Perubahan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
            onNetworkError: () => { unknownOutcome(); return false; },
            onCancel: unknownOutcome,
            onFinish: () => { submitting.current = false; },
        });
    };
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting.current || form.processing || blocked) return;
        if (isAktif) setAuditOpen(true);
        else save();
    };

    return (
        <AuthenticatedLayout hasCustomHeading title={`Edit Renstra: ${renstra.kode}`} breadcrumbs={[
            { label: 'Master Renstra', href: '/renstra' },
            { label: renstra.kode, href: `/renstra/${renstra.id}` }, { label: 'Edit' },
        ]}>
            <Head title={`Edit Renstra: ${renstra.kode}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-xl font-bold tracking-tight text-ink">Edit Rencana Strategis ({renstra.kode})</h1>
                    <Link href={`/renstra/${renstra.id}`} className="inline-flex shrink-0 items-center rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-ink shadow-xs hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20">
                        Kembali
                    </Link>
                </div>
                {isAktif && <div className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-ink">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-warning-dark" aria-hidden="true" />
                    <p>Revisi Renstra aktif memerlukan alasan, nomor, dan tanggal kebijakan/Kepmen. Jadwal serta data historis tetap utuh.</p>
                </div>}
                {!auditOpen && notice}
                {errors.renstra && !auditOpen && <p role="alert" className="text-sm text-danger">{errors.renstra}</p>}
                <form onSubmit={submit} noValidate>
                    <Card>
                        <CardContent className="p-5 sm:p-6">
                            <RenstraFormFields data={form.data} errors={errors} regulasiOptions={regulasiPilihan}
                                disabled={form.processing || Boolean(recovery.recovery)} isEdit={true}
                                canReadRegulasi={can?.readRegulasi === true} canUploadAttachment={can?.uploadAttachment}
                                attachmentLocked={renstra.status !== 'draft'}
                                setField={(field, value) => form.setData((prev) => ({ ...prev, [field]: value }))}
                                setLampiran={(updater) => form.setData((prev) => ({ ...prev, lampiran: updater(prev.lampiran) }))} />
                        </CardContent>
                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Link href={`/renstra/${renstra.id}`} className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-ink hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20">Batal</Link>
                            <Button type="submit" variant="primary" isLoading={form.processing} disabled={blocked}>Simpan Perubahan</Button>
                        </div>
                    </Card>
                </form>
            </div>
            <AuditReasonModal open={auditOpen} title="Konfirmasi Revisi Renstra"
                description="Jelaskan dasar revisi resmi sebelum menyimpan perubahan master."
                reason={form.data.alasan} error={errors.alasan} busy={form.processing} submitDisabled={blocked}
                confirmLabel="Simpan Revisi" onReasonChange={(value) => form.setData('alasan', value)}
                onClose={() => { if (!form.processing) setAuditOpen(false); }} onConfirm={save}
                notice={<div className="mb-4 space-y-4">
                    {notice}
                    {errors.renstra && <p role="alert" className="text-sm text-danger">{errors.renstra}</p>}
                    {Object.entries(errors).filter(([field]) => !['alasan', 'nomor_kebijakan', 'tanggal_kebijakan', 'renstra', 'expected_state'].includes(field)).map(([field, error]) => <p role="alert" key={field} className="text-sm text-danger">{error}</p>)}
                    <Input id="nomor_kebijakan" label="Nomor kebijakan/Kepmen" required maxLength={255}
                        value={form.data.nomor_kebijakan} error={errors.nomor_kebijakan} aria-invalid={Boolean(errors.nomor_kebijakan)}
                        disabled={form.processing} onChange={(event) => form.setData('nomor_kebijakan', event.target.value)} />
                    <Input id="tanggal_kebijakan" label="Tanggal kebijakan/Kepmen" type="date" required
                        value={form.data.tanggal_kebijakan} error={errors.tanggal_kebijakan} aria-invalid={Boolean(errors.tanggal_kebijakan)}
                        disabled={form.processing} onChange={(event) => form.setData('tanggal_kebijakan', event.target.value)} />
                </div>} />
        </AuthenticatedLayout>
    );
}
