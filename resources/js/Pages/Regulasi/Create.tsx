import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { RegulasiFormFields } from '@/Components/RegulasiFormFields';
import type { RegulasiFormData } from '@/types/regulasi';

export default function CreateRegulasi() {
    const form = useForm<RegulasiFormData>({
        jenis: 'kepmen',
        nomor: '',
        tahun: String(new Date().getFullYear()),
        tentang: '',
        tanggal: '',
        tautan_sumber: '',
        catatan: '',
        aktif: true,
        alasan: '',
        lampiran: [],
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/regulasi', { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            title="Tambah Dasar Aturan"
            breadcrumbs={[{ label: 'Dasar Aturan', href: '/regulasi' }, { label: 'Tambah' }]}
        >
            <Head title="Tambah Dasar Aturan" />

            <div className="mx-auto max-w-5xl">
                <div className="mb-5">
                    <Link href="/regulasi" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar
                    </Link>
                </div>

                <form onSubmit={submit} noValidate>
                    <Card>
                        <CardContent>
                            <RegulasiFormFields
                                data={form.data}
                                errors={form.errors as Record<string, string | undefined>}
                                disabled={form.processing}
                                setField={(field, value) => form.setData({ ...form.data, [field]: value })}
                            />
                        </CardContent>
                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Link
                                href="/regulasi"
                                className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                                Batal
                            </Link>
                            <Button type="submit" isLoading={form.processing}>
                                <Save className="h-4 w-4" aria-hidden="true" />
                                Simpan dasar aturan
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
