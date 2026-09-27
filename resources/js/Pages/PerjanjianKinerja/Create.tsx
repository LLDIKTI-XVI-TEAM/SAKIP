import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { PerjanjianKinerjaFormFields } from '@/Components/PerjanjianKinerjaFormFields';
import type {
    PerjanjianKinerjaFormData,
    RenstraSummary,
    StorageSettings,
} from '@/types/perjanjian-kinerja';

interface CreateProps {
    renstras: RenstraSummary[];
    storageSettings: StorageSettings;
}

export default function Create({ renstras, storageSettings }: CreateProps) {
    const { data, setData, post, processing, errors } = useForm<PerjanjianKinerjaFormData>({
        renstra_id: renstras[0]?.id ?? '',
        tahun: renstras[0]?.tahun_mulai ?? new Date().getFullYear(),
        nomor_pk: '',
        tanggal_pk: new Date().toISOString().split('T')[0],
        lampiran: [],
    });

    const setField = <K extends keyof PerjanjianKinerjaFormData>(
        field: K,
        value: PerjanjianKinerjaFormData[K]
    ) => {
        setData((prev) => ({ ...prev, [field]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/perjanjian-kinerja', {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            title="Catat Perjanjian Kinerja Baru"
            breadcrumbs={[
                { label: 'Perjanjian Kinerja', href: '/perjanjian-kinerja' },
                { label: 'Pencatatan Baru' },
            ]}
        >
            <Head title="Catat Perjanjian Kinerja Baru" />

            <div className="mx-auto max-w-4xl space-y-6">
                <Link
                    href="/perjanjian-kinerja"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
                >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke Daftar PK
                </Link>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Formulir Pencatatan Perjanjian Kinerja</CardTitle>
                            <p className="mt-1 text-sm text-muted">
                                Masukkan rincian dokumen legal formal komitmen kinerja dan lampirkan naskah pendukung.
                            </p>
                        </CardHeader>

                        <CardContent className="space-y-6 pt-4">
                            <PerjanjianKinerjaFormFields
                                data={data}
                                errors={errors as Record<string, string | undefined>}
                                renstras={renstras}
                                storageSettings={storageSettings}
                                disabled={processing}
                                setField={setField}
                            />
                        </CardContent>

                        <div className="flex items-center justify-end gap-3 border-t border-border bg-soft/40 px-6 py-4">
                            <Link href="/perjanjian-kinerja">
                                <Button type="button" variant="outline" disabled={processing}>
                                    Batal
                                </Button>
                            </Link>
                            <Button type="submit" variant="primary" disabled={processing}>
                                <Save className="h-4 w-4" aria-hidden="true" />
                                {processing ? 'Menyimpan...' : 'Simpan Perjanjian Kinerja'}
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
