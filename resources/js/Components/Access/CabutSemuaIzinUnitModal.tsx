import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { belumDiketahui, failureClass, pesanGagal } from '@/Components/Access/izinUnitFeedback';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { KelompokIzinUnit } from '@/types/izinUnit';

/**
 * Cabut seluruh izin satu pengguna pada satu unit lewat endpoint Kelola dengan daftar akhir kosong (Q62).
 * Tetap terpasang agar penutupan dialog native mengembalikan fokus ke tombol pemicu.
 */
export function CabutSemuaIzinUnitModal({ kelompok, onClose }: { kelompok: KelompokIzinUnit | null; onClose: () => void }) {
    const recovery = useAuthRecovery();
    const [alasan, setAlasan] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [failure, setFailure] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [terakhir, setTerakhir] = useState<string | null>(null);

    // Formulir dikosongkan setiap kali dibuka, termasuk dibuka ulang untuk kelompok yang sama setelah ditutup.
    if (kelompok && kelompok.key !== terakhir) {
        setTerakhir(kelompok.key);
        setAlasan('');
        setError(undefined);
        setFailure(null);
    } else if (!kelompok && terakhir !== null) {
        setTerakhir(null);
    }

    const close = () => { if (!busy) onClose(); };
    const submit = () => {
        if (!kelompok || busy || recovery.recovery) return;
        setBusy(true);
        setError(undefined);
        setFailure(null);
        router.put('/akses/grant/kelola', {
            user_id: kelompok.user_id,
            unit_id: kelompok.unit_id,
            permission_ids: [],
            expected_permission_ids: kelompok.izin.map((izin) => izin.permission_id),
            alasan,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => {
                if (errors.alasan) setError(errors.alasan);
                else setFailure(Object.values(errors)[0] ?? 'Pencabutan izin unit gagal.');
            },
            onCancel: () => setFailure(belumDiketahui('pencabutan', 'Permintaan dibatalkan.')),
            onNetworkError: () => {
                setFailure(belumDiketahui('pencabutan', 'Koneksi terputus.'));
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'put', path: '/akses/grant/kelola', mutation: true })) return false;
                setFailure(pesanGagal(response));
                return false;
            },
            onFinish: () => setBusy(false),
        });
    };

    return <AuditReasonModal
        open={kelompok !== null}
        title={`Cabut semua izin unit ${kelompok?.user_name ?? ''}?`}
        description={kelompok ? `${kelompok.izin.length} izin pada ${kelompok.unit_nama ?? 'unit ini'} akan dicabut. Peran utama pengguna tidak berubah.` : ''}
        reason={alasan}
        error={error}
        busy={busy}
        submitDisabled={alasan.trim() === '' || Boolean(recovery.recovery)}
        notice={<>
            {failure && <p role="alert" className={`mb-4 ${failureClass}`}>{failure}</p>}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={busy} />
        </>}
        placeholder="Contoh: penugasan pengisian triwulan telah selesai."
        confirmLabel="Cabut semua"
        destructive
        onReasonChange={setAlasan}
        onClose={close}
        onConfirm={submit}
    />;
}
