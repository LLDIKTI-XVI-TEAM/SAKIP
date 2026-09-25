import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import type { RegulasiOption, RenstraFormData } from '@/types/renstra';

interface CreateRenstraProps {
    regulasiPilihan: RegulasiOption[];
}

export default function CreateRenstra({ regulasiPilihan }: CreateRenstraProps) {
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
                        <Link
                            href="/renstra"
                            className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                            Kembali ke Master Renstra
                        </Link>
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
                                setField={(field, value) => form.setData({ ...form.data, [field]: value })}
                            />
                        </CardContent>

                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Link
                                href="/renstra"
                                className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                                Batal
                            </Link>
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
