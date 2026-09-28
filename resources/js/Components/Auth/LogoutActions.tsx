import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/Components/Button';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';

export function LogoutActions({ sidebar = false, showLocal = true }: {
    sidebar?: boolean;
    showLocal?: boolean;
}) {
    const recovery = useAuthRecovery();
    const [intent, setIntent] = useState<'/logout' | '/logout/sso' | null>(null);
    const [error, setError] = useState('');
    const [confirmSso, setConfirmSso] = useState(false);
    const busy = useRef(false);
    const dialog = useRef<HTMLDialogElement>(null);
    const ssoButton = useRef<HTMLButtonElement>(null);
    const id = useId();
    const disabled = Boolean(intent || error || recovery.recovery);

    useEffect(() => {
        if (!confirmSso) return;
        const element = dialog.current;
        element?.showModal();
        element?.querySelector<HTMLButtonElement>('button')?.focus();
        return () => {
            element?.close();
            ssoButton.current?.focus();
        };
    }, [confirmSso]);

    const leave = (path: '/logout' | '/logout/sso') => {
        if (busy.current || error || recovery.recovery) return;
        busy.current = true;
        setConfirmSso(false);
        setIntent(path);
        const uncertain = () => {
            setError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.');
        };
        router.post(path, {}, {
            // Hapus riwayat terlindungi sebelum berpindah ke provider, termasuk jika dibatalkan di sana.
            onStart: () => router.clearHistory(),
            onFinish: () => {
                busy.current = false;
                setIntent(null);
            },
            onHttpException: (response) => {
                setIntent(null);
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) uncertain();
                return false;
            },
            onError: uncertain,
            onCancel: uncertain,
            onNetworkError: () => { uncertain(); return false; },
        });
    };

    return (
        <div className="mt-4 space-y-3" aria-busy={Boolean(intent)}>
            {recovery.recovery && (
                <div className="flow-root rounded-lg bg-surface px-3 text-ink">
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={Boolean(intent)} logout />
                </div>
            )}
            {error && <p role="alert" className="rounded-lg bg-surface p-3 text-sm text-danger">{error}</p>}
            {showLocal && (
                <Button
                    type="button"
                    className={sidebar ? 'w-full bg-surface text-primary hover:bg-soft' : undefined}
                    isLoading={intent === '/logout'}
                    disabled={disabled}
                    onClick={() => leave('/logout')}
                >
                    Keluar dari SAKIP
                </Button>
            )}
            <div className={sidebar ? 'border-t border-white/20 pt-3' : 'border-t border-border pt-3'}>
                <Button
                    type="button"
                    variant="outline"
                    className={sidebar ? 'w-full border-white/40 bg-transparent text-white hover:bg-white/10 focus:ring-white' : undefined}
                    aria-haspopup="dialog"
                    isLoading={intent === '/logout/sso'}
                    disabled={disabled}
                    onClick={(event) => {
                        ssoButton.current = event.currentTarget;
                        setConfirmSso(true);
                    }}
                >
                    Keluar dari layanan terhubung
                </Button>
            </div>
            {confirmSso && createPortal(
                <dialog
                    ref={dialog}
                    aria-labelledby={`${id}-title`}
                    aria-describedby={`${id}-description`}
                    onCancel={(event) => {
                        event.preventDefault();
                        setConfirmSso(false);
                    }}
                    // Dialog mengelola keyboard sendiri, bukan focus trap drawer di belakangnya.
                    onKeyDown={(event) => event.stopPropagation()}
                    className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-border bg-surface p-6 text-ink shadow-xl backdrop:bg-ink/55"
                >
                    <h2 id={`${id}-title`} className="text-lg font-semibold">Keluar dari layanan terhubung?</h2>
                    <p id={`${id}-description`} className="mt-3 text-sm leading-6 text-muted">
                        Sesi masuk bersama akan diakhiri. Anda mungkin perlu masuk kembali saat membuka layanan lain, termasuk SIMPEG.
                    </p>
                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <Button type="button" variant="outline" onClick={() => setConfirmSso(false)}>
                            Batal
                        </Button>
                        <Button type="button" disabled={disabled} onClick={() => leave('/logout/sso')}>
                            Keluar dari layanan terhubung
                        </Button>
                    </div>
                </dialog>,
                document.body,
            )}
        </div>
    );
}
