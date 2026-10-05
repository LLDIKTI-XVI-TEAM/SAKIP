import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { BackButton } from '@/Components/BackButton';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import type { RegulasiOption, RenstraFormData } from '@/types/renstra';

interface CreateRenstraProps {
    regulasiPilihan: RegulasiOption[];
    can?: {
        uploadAttachment?: boolean;
        readRegulasi?: boolean;
    };
}

export default function CreateRenstra({ regulasiPilihan, can }: CreateRenstraProps) {
    const currentYear = new Date().getFullYear();
    const form = useForm<RenstraFormData>({
        nama: '',
        kode: '',
        tahun_mulai: String(currentYear),
        tahun_selesai: String(currentYear + 4),
        deskripsi: '',
        dasar_hukum: '',
        regulasi_id: '',
        alasan: '',
        lampiran: [],
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
        form.post('/renstra', {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            title="Tambah Renstra"
            breadcrumbs={[{ label: 'Master Renstra', href: '/renstra' }, { label: 'Tambah Baru' }]}
        >
            <Head title="Tambah Master Renstra" />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <BackButton href="/renstra" label="Kembali ke Master Renstra" />
                        <h1 className="mt-2 text-xl font-bold tracking-tight text-ink">Tambah Master Renstra Baru</h1>
                    </div>
                </div>

                <form onSubmit={submit} noValidate>
                    <Card>
                        <CardContent className="p-6">
                            <RenstraFormFields
                                data={form.data}
                                errors={form.errors as Record<string, string | undefined>}
                                regulasiOptions={regulasiPilihan}
                                disabled={form.processing}
                                canReadRegulasi={can?.readRegulasi === true}
                                canUploadAttachment={can?.uploadAttachment}
                                setField={(field, value) => form.setData((prev) => ({ ...prev, [field]: value }))}
                                setLampiran={(updater) => form.setData((prev) => ({ ...prev, lampiran: updater(prev.lampiran) }))}
                            />
                        </CardContent>

                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Button
                                href="/renstra"
                                variant="outline"
                            >
                                Batal
                            </Button>
                            <Button type="submit" variant="primary" isLoading={form.processing}>
                                Simpan Renstra
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
