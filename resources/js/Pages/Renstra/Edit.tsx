import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import type { RegulasiOption, RenstraDetail, RenstraFormData } from '@/types/renstra';

interface EditRenstraProps {
    renstra: RenstraDetail;
    regulasiPilihan: RegulasiOption[];
    can?: {
        uploadAttachment?: boolean;
    };
}

export default function EditRenstra({ renstra, regulasiPilihan, can }: EditRenstraProps) {
    const isAktif = renstra.status === 'aktif' || renstra.is_aktif;
    const form = useForm<RenstraFormData>({
        nama: renstra.nama,
        kode: renstra.kode,
        tahun_mulai: String(renstra.tahun_mulai),
        tahun_selesai: String(renstra.tahun_selesai),
        deskripsi: renstra.deskripsi ?? '',
        dasar_hukum: renstra.dasar_hukum ?? '',
        regulasi_id: renstra.regulasi_id ? String(renstra.regulasi_id) : '',
        alasan: '',
        lampiran: [],
        _method: 'put',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (form.processing) return;
        form.post(`/renstra/${renstra.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            title={`Edit Renstra: ${renstra.kode}`}
            breadcrumbs={[
                { label: 'Master Renstra', href: '/renstra' },
                { label: renstra.kode, href: `/renstra/${renstra.id}` },
                { label: 'Edit' },
            ]}
        >
            <Head title={`Edit Renstra: ${renstra.kode}`} />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <Link
                            href={`/renstra/${renstra.id}`}
                            className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                            Kembali ke Detail Renstra
                        </Link>
                        <h1 className="mt-2 text-xl font-bold tracking-tight text-ink">
                            Edit Rencana Strategis ({renstra.kode})
                        </h1>
                    </div>
                </div>

                {isAktif && (
                    <div className="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 text-xs text-ink leading-relaxed">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-warning-dark" aria-hidden="true" />
                        <div>
                            <span className="font-semibold text-warning-dark">Perhatian Dokumen Aktif:</span> Renstra ini berstatus aktif. Setiap pembaruan data wajib menyertakan alasan perubahan yang jelas dan akan dicatat secara permanen pada audit log.
                        </div>
                    </div>
                )}

                <form onSubmit={submit} noValidate>
                    <Card>
                        <CardContent className="p-6">
                            <RenstraFormFields
                                data={form.data}
                                errors={form.errors as Record<string, string | undefined>}
                                regulasiOptions={regulasiPilihan}
                                disabled={form.processing}
                                isEdit={true}
                                requireReason={isAktif}
                                canUploadAttachment={can?.uploadAttachment}
                                setField={(field, value) => form.setData({ ...form.data, [field]: value })}
                            />
                        </CardContent>

                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Link
                                href={`/renstra/${renstra.id}`}
                                className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                                Batal
                            </Link>
                            <Button type="submit" variant="primary" isLoading={form.processing}>
                                Simpan Perubahan
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
