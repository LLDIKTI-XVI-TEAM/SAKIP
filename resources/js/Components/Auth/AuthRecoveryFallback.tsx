import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { classifyRecovery, type RecoveryState } from '@/lib/authRecovery';
import { AuthRecoveryNotice } from './AuthRecoveryNotice';
import { Button } from '@/Components/Button';

export function AuthRecoveryFallback() {
    const [recovery, setRecovery] = useState<RecoveryState | null>(null);
    const dialog = useRef<HTMLDialogElement>(null);
    const previous = useRef<HTMLElement | null>(null);
    useEffect(() => {
        const element = dialog.current;
        const dispose = router.on('httpException', (event) => {
            const next = classifyRecovery(event.detail.response);
            if (!next) return;
            event.preventDefault();
            setRecovery(next);
        });
        return () => { dispose(); element?.close(); };
    }, []);
    useEffect(() => {
        if (!recovery || !dialog.current || dialog.current.open) return;
        previous.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        dialog.current.showModal();
        dialog.current.querySelector<HTMLElement>('h2')?.focus();
    }, [recovery]);
    const dismiss = () => {
        dialog.current?.close();
        setRecovery(null);
        const origin = previous.current;
        if (origin?.isConnected && !origin.matches(':disabled, [inert] *')) origin.focus();
        if (document.activeElement !== origin || !origin?.isConnected) {
            const heading = document.querySelector<HTMLElement>('main h1') ?? document.querySelector<HTMLElement>('main');
            if (heading) { heading.tabIndex = -1; heading.focus(); }
        }
    };
    return <dialog ref={dialog} aria-labelledby="auth-recovery-title" aria-describedby="auth-recovery-description"
        onCancel={(event) => { event.preventDefault(); dismiss(); }}
        className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-border bg-surface p-5 text-ink shadow-xl backdrop:bg-ink/50">
        <p id="auth-recovery-description" className="text-sm text-muted">Halaman tetap tersedia sampai Anda memilih untuk meninggalkannya.</p>
        <AuthRecoveryNotice recovery={recovery} focus={false} headingId="auth-recovery-title" />
        <Button type="button" variant="outline" onClick={dismiss}>Tetap di halaman</Button>
    </dialog>;
}
