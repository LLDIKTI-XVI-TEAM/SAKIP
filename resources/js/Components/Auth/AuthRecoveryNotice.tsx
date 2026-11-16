import type { RecoveryState } from '@/lib/authRecovery';
import { useEffect, useId, useRef } from 'react';
import { Button } from '@/Components/Button';
import { loginLink } from '@/Pages/Auth/AuthShell';

export function AuthRecoveryNotice({ recovery, pending = false, focus = true, logout = false, headingId }: {
    recovery: RecoveryState | null; pending?: boolean; focus?: boolean; logout?: boolean; headingId?: string;
}) {
    const title = useRef<HTMLHeadingElement>(null);
    const id = useId();
    const reason = recovery?.reason;
    const outcome = recovery?.outcome;
    useEffect(() => { if (reason && !pending && focus) title.current?.focus(); }, [reason, outcome, pending, focus]);
    if (!recovery) return null;
    const auth = recovery.reason === 'authentication_required';
    const message = logout ? 'Keluar belum terkonfirmasi. Periksa sesi setelah memulihkan halaman.'
        : recovery.outcome === 'not-a-mutation'
            ? auth ? 'Pembacaan data memerlukan sesi aktif. Input masih tersedia di halaman ini.' : 'Verifikasi keamanan pembacaan gagal. Input masih tersedia di halaman ini.'
            : recovery.outcome === 'rejected'
                ? auth ? 'Permintaan perubahan ini ditolak karena sesi Anda berakhir. Input masih tersedia di halaman ini.' : 'Permintaan perubahan ini ditolak karena verifikasi keamanan formulir tidak cocok. Input masih tersedia di halaman ini.'
                : auth ? 'Sesi Anda berakhir. Hasil tindakan sebelumnya belum dapat dipastikan. Periksa data setelah masuk kembali.' : 'Verifikasi permintaan gagal. Hasil tindakan sebelumnya belum dapat dipastikan. Periksa data setelah memuat ulang.';
    return <section role="alert" aria-labelledby={headingId ?? id} className="my-4 space-y-3 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
        <h2 ref={title} id={headingId ?? id} tabIndex={-1} className="font-semibold focus:outline-none">{auth ? 'Autentikasi diperlukan' : 'Verifikasi keamanan diperlukan'}</h2>
        <p>{message}</p>
        <p>Salin input yang ingin dipertahankan sebelum melanjutkan. Masuk ulang atau muat ulang akan mengosongkan formulir; berkas perlu dipilih kembali.</p>
        {auth ? <a href="/login?recovery=1" className={loginLink}>Masuk ulang</a> : <Button type="button" onClick={() => window.location.reload()}>Muat ulang halaman</Button>}
    </section>;
}
