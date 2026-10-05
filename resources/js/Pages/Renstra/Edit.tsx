import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { BackButton } from '@/Components/BackButton';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import type { RegulasiOption, RenstraDetail, RenstraFormData } from '@/types/renstra';

interface EditRenstraProps {
    renstra: RenstraDetail;
    regulasiPilihan: RegulasiOption[];
    can?: {
        uploadAttachment?: boolean;
        readRegulasi?: boolean;
    };
}

export default function EditRenstra({ renstra, regulasiPilihan, can }: EditRenstraProps) {
    const isAktif = renstra.status === 'aktif' || Boolean(renstra.is_aktif);
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
        form.transform((data) => {
            if (can?.readRegulasi === true) return data;

            const payload: Partial<RenstraFormData> = { ...data };
            delete payload.regulasi_id;

            return payload;
        });
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
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-xl font-bold tracking-tight text-ink">
                        Edit Rencana Strategis ({renstra.kode})
                    </h1>

                    <BackButton href={`/renstra/${renstra.id}`} label="Kembali" />
                </div>

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
                                canReadRegulasi={can?.readRegulasi === true}
                                canUploadAttachment={can?.uploadAttachment}
                                attachmentLocked={renstra.status !== 'draft'}
                                setField={(field, value) => form.setData((prev) => ({ ...prev, [field]: value }))}
                                setLampiran={(updater) => form.setData((prev) => ({ ...prev, lampiran: updater(prev.lampiran) }))}
                            />
                        </CardContent>

                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Button
                                href={`/renstra/${renstra.id}`}
                                variant="outline"
                            >
                                Batal
                            </Button>
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
