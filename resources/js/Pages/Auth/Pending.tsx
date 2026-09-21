import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/Button';
import AuthShell, { secondaryButton } from './AuthShell';

interface PendingProps {
    auth: { user: { id: string; nama: string; email: string; is_active: boolean } };
}

export default function Pending({ auth }: PendingProps) {
    const [leaving, setLeaving] = useState(false);

    return (
        <AuthShell title="Menunggu aktivasi akun">
            <p className="mt-3 text-sm leading-relaxed text-muted">
                Identitas SSO Anda sudah terhubung. Hubungi pengelola akses SAKIP untuk mengaktifkan akun sebelum menggunakan aplikasi.
            </p>
            <dl className="my-6 space-y-3 rounded-lg bg-soft p-4 text-sm">
                <div><dt className="text-muted">Nama</dt><dd className="break-words font-medium">{auth.user.nama}</dd></div>
                <div><dt className="text-muted">Email</dt><dd className="break-all">{auth.user.email}</dd></div>
                <div><dt className="text-muted">Status</dt><dd className="font-medium">Belum aktif</dd></div>
            </dl>
            <div className="flex flex-wrap items-center gap-3">
                <Link href="/auth/pending" className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">Periksa status</Link>
                <Button type="button" variant="outline" className={secondaryButton} isLoading={leaving} onClick={() => {
                    setLeaving(true);
                    router.post('/logout', {}, { onStart: () => router.clearHistory(), onFinish: () => setLeaving(false) });
                }}>Keluar</Button>
            </div>
        </AuthShell>
    );
}
