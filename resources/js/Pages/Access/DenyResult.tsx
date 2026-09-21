import type { DenyResultProps } from '@/types/deny';
import { Head, Link } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton } from '@/Pages/Auth/AuthShell';

export default function DenyResult({ status, canReturn }: DenyResultProps) {
    const message = status === 'created' ? 'Deny berhasil ditambahkan.' : status === 'revoked' ? 'Deny berhasil dicabut.' : 'Tidak ada hasil pembatasan izin untuk ditampilkan.';
    return <AuthenticatedLayout title="Hasil pembatasan izin"><Head title="Hasil pembatasan izin" /><section className="max-w-2xl rounded-xl border border-border bg-surface p-6"><h1 role="status" className="text-lg font-semibold">{message}</h1>
        {canReturn ? <Link href="/akses/deny" className={`${primaryButton} mt-6 inline-flex rounded-lg px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-primary`}>Kembali ke pembatasan izin</Link> : <div className="mt-5 rounded-lg bg-soft p-4 text-sm"><p className="font-semibold">Akses pengelolaan izin tidak tersedia untuk akun Anda.</p><p className="mt-2 text-muted">Hubungi pengelola lain yang masih berizin untuk memeriksa pembatasan akun Anda. Menu mengikuti izin saat ini; Anda tetap dapat keluar dari sistem melalui sidebar.</p></div>}
    </section></AuthenticatedLayout>;
}
