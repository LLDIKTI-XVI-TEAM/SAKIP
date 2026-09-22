import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/Button';
import AuthShell, { secondaryButton } from './AuthShell';

interface PendingProps {
    auth: { user: { id: string; nama: string; email: string; is_active: boolean } };
}

export default function Pending({ auth }: PendingProps) {
    const { flash } = usePage();
    const recovery = useAuthRecovery();
    const [logoutError, setLogoutError] = useState('');
    const [leaving, setLeaving] = useState(false);
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
        <AuthShell title="Menunggu aktivasi akun">
            <p className="mt-3 text-sm leading-relaxed text-muted">
                Identitas SSO Anda sudah terhubung. Sampaikan ID akun di bawah kepada pengelola akses SAKIP untuk aktivasi.
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
                <div><dt className="text-muted">Status</dt><dd className="font-medium">Belum aktif</dd></div>
            </dl>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={leaving} logout />
            {logoutError && <p role="alert" className="my-3 text-sm text-danger">{logoutError}</p>}
            <div className="flex flex-wrap items-center gap-3">
                <Link href="/auth/pending" className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">Periksa status</Link>
                <Button type="button" variant="outline" className={secondaryButton} isLoading={leaving} disabled={Boolean(recovery.recovery || logoutError)} onClick={() => {
                    if (leaving || recovery.recovery || logoutError) return;
                    setLeaving(true);
                    router.post('/logout', {}, {
                        onStart: () => router.clearHistory(), onFinish: () => setLeaving(false),
                        onHttpException: (response) => {
                            setLeaving(false);
                            if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/logout', mutation: true })) setLogoutError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.');
                            return false;
                        },
                        onCancel: () => { setLeaving(false); setLogoutError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.'); },
                        onNetworkError: () => { setLeaving(false); setLogoutError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.'); return false; },
                    });
                }}>Keluar</Button>
            </div>
        </AuthShell>
    );
}
