import { usePage } from '@inertiajs/react';
import { LogoutActions } from '@/Components/Auth/LogoutActions';
import type { SharedPageProps } from '@/types/auth';
import AuthShell, { loginLink } from './AuthShell';

export default function LoggedOut() {
    const { props: { auth }, flash } = usePage<SharedPageProps>();
    const authenticated = Boolean(auth.user);

    return (
        <AuthShell title={authenticated ? 'Sesi SAKIP masih aktif' : 'Anda telah keluar'}>
            <p className="mt-3 text-sm leading-relaxed text-muted">
                {authenticated
                    ? 'Gunakan tombol Keluar dari SAKIP untuk mengakhiri sesi di perangkat ini.'
                    : 'Sesi SAKIP di perangkat ini sudah berakhir. Sesi SSO mungkin masih aktif; masuk kembali dapat berlangsung tanpa memasukkan kata sandi.'}
            </p>
            {flash.logoutNotice === 'sso_unavailable' && (
                <p role="alert" className="mt-4 rounded-lg bg-warning/10 p-3 text-sm text-warning-dark">
                    Logout SSO belum terkonfirmasi. Sesi lokal SAKIP sudah diakhiri.
                </p>
            )}
            {!authenticated && <a href="/login" className={`${loginLink} mt-6`}>Masuk kembali melalui SSO</a>}
            <LogoutActions showLocal={authenticated} />
        </AuthShell>
    );
}
