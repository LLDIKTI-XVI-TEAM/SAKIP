import { Head, Link, usePage } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton } from '@/Pages/Auth/AuthShell';
import type { SharedPageProps } from '@/types/auth';

export default function RoleAssignmentResult({ status, canReturn }: { status: 'assigned' | 'changed' | 'unchanged' | null; canReturn: boolean }) {
    const { props: { auth } } = usePage<SharedPageProps>();
    const message = status === 'assigned' ? 'Peran berhasil ditetapkan.' : status === 'changed' ? 'Peran berhasil diubah.' : status === 'unchanged' ? 'Peran tidak berubah.' : 'Tidak ada hasil penetapan peran untuk ditampilkan.';
    return <AuthenticatedLayout title="Hasil penetapan peran" hasCustomHeading>
        <Head title="Hasil penetapan peran" />
        <section className="max-w-2xl rounded-xl border border-border bg-surface p-6">
            <h1 className="text-lg font-semibold" role="status">{message}</h1>
            <p className="mt-3 text-sm text-muted">Perubahan peran tidak otomatis mengubah grant izin atau penugasan.</p>
            {canReturn ? <Link href="/akses/peran" className={`${primaryButton} mt-6 inline-flex rounded-lg px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-primary`}>Kembali ke penetapan peran</Link> : <div className="mt-5 rounded-lg bg-soft p-4 text-sm">
                <p className="font-semibold">Akses pengelolaan peran tidak tersedia untuk akun Anda.</p>
                {auth.user?.role && <p className="mt-2">Peran aktif: <strong>{auth.user.role.toUpperCase()}</strong>.</p>}
                <p className="mt-2 text-muted">Menu mengikuti izin peran Anda saat ini. Jika tidak ada menu yang tersedia, hubungi pengelola SAKIP untuk memeriksa akses akun. Anda tetap dapat keluar dari sistem melalui sidebar.</p>
            </div>}
        </section>
    </AuthenticatedLayout>;
}
