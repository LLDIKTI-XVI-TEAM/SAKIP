import { Head, Link, usePage } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import type { SharedPageProps } from '@/types/auth';
import { loginLink } from './AuthShell';

export default function Recovered() {
    const { auth } = usePage<SharedPageProps>().props;
    return <AuthenticatedLayout title="Sesi aktif">
        <Head title="Sesi aktif" />
        <section className="max-w-2xl space-y-4 rounded-xl border border-border bg-surface p-6">
            <h1 className="text-xl font-semibold">Sesi Anda aktif</h1>
            <p className="text-sm leading-relaxed">Tidak ada formulir yang dikirim ulang otomatis. Periksa hasil tindakan sebelumnya sebelum mengirim perubahan lagi.</p>
            <p className="text-sm text-muted">Formulir sebelumnya tidak dipulihkan. Buka halaman yang tersedia sesuai akses Anda untuk memeriksa data terbaru.</p>
            {auth.can.dashboard && <Link href="/dashboard" className={loginLink}>Buka dashboard</Link>}
        </section>
    </AuthenticatedLayout>;
}
