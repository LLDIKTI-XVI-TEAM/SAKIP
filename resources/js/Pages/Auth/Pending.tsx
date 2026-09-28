import { LogoutActions } from '@/Components/Auth/LogoutActions';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/Button';
import AuthShell, { secondaryButton } from './AuthShell';

interface PendingProps {
    pendingReason: 'activation' | 'role';
    auth: { user: { id: string; nama: string; email: string; status: 'aktif' | 'nonaktif' } };
}

export default function Pending({ auth, pendingReason }: PendingProps) {
    const { flash } = usePage();
    const [copyMessage, setCopyMessage] = useState('');

    const copyAccountId = async () => {
        try {
            await navigator.clipboard.writeText(auth.user.id);
            setCopyMessage('ID akun tersalin.');
        } catch {
            setCopyMessage('Gagal menyalin. Pilih dan salin ID akun secara manual.');
        }
    };

    return (
        <AuthShell title={pendingReason === 'role' ? 'Menunggu penetapan peran' : 'Menunggu aktivasi akun'}>
            <p className="mt-3 text-sm leading-relaxed text-muted">
                {pendingReason === 'role'
                    ? 'Akun Anda sudah aktif, tetapi belum memiliki peran yang aktif. Sampaikan ID akun kepada pengelola akses SAKIP untuk penetapan peran.'
                    : 'Identitas SSO Anda sudah terhubung. Sampaikan ID akun di bawah kepada pengelola akses SAKIP untuk aktivasi.'}
            </p>
            {flash.authRecoveryNotice === 'no_replay' && <p role="status" className="my-4 rounded-lg bg-soft p-4 text-sm">Tidak ada formulir yang dikirim ulang otomatis. Periksa hasil tindakan sebelumnya sebelum mencoba kembali setelah akses tersedia.</p>}
            <dl className="my-6 space-y-3 rounded-lg bg-soft p-4 text-sm">
                <div><dt className="text-muted">Nama</dt><dd className="break-words font-medium">{auth.user.nama}</dd></div>
                <div><dt className="text-muted">Email</dt><dd className="break-all">{auth.user.email}</dd></div>
                <div>
                    <dt className="text-muted">ID akun SAKIP</dt>
                    <dd className="mt-1 flex flex-wrap items-center gap-2">
                        <span className="select-all break-all font-mono text-xs font-medium">{auth.user.id}</span>
                        <Button type="button" variant="outline" size="sm" className={secondaryButton} onClick={copyAccountId}>Salin ID akun</Button>
                    </dd>
                    <p role="status" aria-live="polite" className="mt-1 text-xs text-muted">{copyMessage}</p>
                </div>
                <div><dt className="text-muted">Status</dt><dd className="font-medium">{pendingReason === 'role' ? 'Aktif — belum memiliki peran' : 'Belum aktif'}</dd></div>
            </dl>
            <Link href="/auth/pending" className="inline-flex rounded-lg border border-border px-4 py-2 text-sm font-semibold text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">Periksa status</Link>
            <LogoutActions />
        </AuthShell>
    );
}
