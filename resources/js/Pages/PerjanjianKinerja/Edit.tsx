import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, ShieldCheck } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { PerjanjianKinerjaFormFields } from '@/Components/PerjanjianKinerjaFormFields';
import type {
    PerjanjianKinerjaFormData,
    RenstraPkSummary,
    StorageSettings,
} from '@/types/perjanjian-kinerja';

interface EditProps {
    pk: RenstraPkSummary;
    is_jadwal_aktif: boolean;
    storageSettings: StorageSettings;
}

export default function Edit({ pk, is_jadwal_aktif, storageSettings }: EditProps) {
    const { data, setData, post, processing, errors } = useForm<PerjanjianKinerjaFormData>({
        renstra_id: pk.renstra_id,
        tahun: pk.tahun,
        nomor_pk: pk.nomor_pk,
        tanggal_pk: pk.tanggal_pk ? pk.tanggal_pk.split('T')[0] : '',
        alasan: '',
        lampiran: [],
        _method: 'put',
    });

    const setField = <K extends keyof PerjanjianKinerjaFormData>(
        field: K,
        value: PerjanjianKinerjaFormData[K]
    ) => {
        setData((prev) => ({ ...prev, [field]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/perjanjian-kinerja/${pk.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AuthenticatedLayout
            title={`Edit PK Tahun ${pk.tahun} - ${pk.nomor_pk}`}
            breadcrumbs={[
                { label: 'Perjanjian Kinerja', href: '/perjanjian-kinerja' },
                { label: `Tahun ${pk.tahun}`, href: `/perjanjian-kinerja/${pk.id}` },
                { label: 'Edit' },
            ]}
        >
            <Head title={`Edit Perjanjian Kinerja Tahun ${pk.tahun}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <Link
                    href={`/perjanjian-kinerja/${pk.id}`}
                    className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
                >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke Detail PK
                </Link>

                {is_jadwal_aktif && (
                    <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-950 dark:text-emerald-200">
                        <div className="flex items-start gap-3">
                            <ShieldCheck className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" aria-hidden="true" />
                            <div>
                                <h3 className="text-sm font-bold">Jadwal Tahunan Telah Aktif</h3>
                                <p className="mt-1 text-xs leading-relaxed text-emerald-900/80 dark:text-emerald-300">
                                    Pembaruan metadata atau penambahan lampiran baru tetap diizinkan. Namun, lampiran yang telah ada tidak dapat dihapus demi kepatuhan audit legal formal.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Data Perjanjian Kinerja</CardTitle>
                            <p className="mt-1 text-sm text-muted">
                                Perbarui nomor surat, tanggal penandatanganan, atau tambahkan naskah lampiran baru. Seluruh perubahan dicatat dalam jejak audit beralasan.
                            </p>
                        </CardHeader>

                        <CardContent className="space-y-6 pt-4">
                            <PerjanjianKinerjaFormFields
                                data={data}
                                errors={errors as Record<string, string | undefined>}
                                renstras={[pk.renstra]}
                                storageSettings={storageSettings}
                                isEdit={true}
                                disabled={processing}
                                setField={setField}
                            />
                        </CardContent>

                        <div className="flex items-center justify-end gap-3 border-t border-border bg-soft/40 px-6 py-4">
                            <Link href={`/perjanjian-kinerja/${pk.id}`}>
                                <Button type="button" variant="outline" disabled={processing}>
                                    Batal
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={processing || !data.alasan?.trim()}
                            >
                                <Save className="h-4 w-4" aria-hidden="true" />
                                {processing ? 'Menyimpan...' : 'Perbarui Perjanjian Kinerja'}
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
