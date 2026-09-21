import AuthShell, { loginLink } from './AuthShell';

export default function Error() {
    return (
        <AuthShell title="Tidak dapat masuk">
            <p className="mb-6 mt-3 text-sm leading-relaxed text-muted" role="alert">
                Proses masuk SSO belum berhasil. Coba masuk kembali. Jika masalah berulang, hubungi pengelola SAKIP.
            </p>
            <a href="/login" className={loginLink}>Coba masuk kembali</a>
        </AuthShell>
    );
}
