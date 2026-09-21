import AuthShell, { loginLink } from './AuthShell';

export default function LoggedOut() {
    return (
        <AuthShell title="Anda telah keluar">
            <p className="mt-3 text-sm leading-relaxed text-muted">Sesi SAKIP di perangkat ini sudah berakhir.</p>
            <p className="mb-6 mt-3 text-sm leading-relaxed text-muted">
                Keluar dari SSO dapat memengaruhi sesi bersama, termasuk SIMPEG. Sesi aplikasi lain mengikuti pengaturan masing-masing.
            </p>
            <a href="/login" className={loginLink}>Masuk kembali melalui SSO</a>
        </AuthShell>
    );
}
