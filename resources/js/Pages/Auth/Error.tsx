import AuthShell, { loginLink } from './AuthShell';

export default function Error({ recoveryRetry = false }: { recoveryRetry?: boolean }) {
    return (
        <AuthShell title="Tidak dapat masuk">
            <p className="mb-6 mt-3 text-sm leading-relaxed text-muted" role="alert">
                Proses masuk SSO belum berhasil. Coba masuk kembali. Jika masalah berulang, hubungi pengelola SAKIP.
            </p>
            <a href={recoveryRetry ? '/login?recovery=1' : '/login'} className={loginLink}>Coba masuk kembali</a>
        </AuthShell>
    );
}
