import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';

interface PenutupanPanelProps {
    jadwalId: string;
    tahun: number;
    /** Revisi jadwal yang sedang tampil; satu-satunya sumber expected_revisi. */
    revisi: number;
}

const linkClass = 'text-sm font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/30 rounded';

export function PenutupanPanel({ jadwalId, tahun, revisi }: PenutupanPanelProps) {
    const [open, setOpen] = useState(false);
    const [unknown, setUnknown] = useState(false);
    const recovery = useAuthRecovery();
    const form = useForm({ alasan: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const serverErrors = Object.entries(errors).filter(([field]) => field !== 'alasan');
    const path = `/jadwal/${jadwalId}/tutup`;

    const markUnknown = () => {
        setOpen(false);
        setUnknown(true);
    };

    const confirm = () => {
        if (form.processing || form.data.alasan.trim() === '') return;
        form.transform((data) => ({ expected_revisi: revisi, alasan: data.alasan }));
        form.post(path, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            // Tutup ulang aman (server menolak jadwal yang sudah ditutup), tetapi hasil ambigu tetap tidak diulang otomatis.
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) markUnknown();
                else setOpen(false);
                return false;
            },
            onNetworkError: () => {
                markUnknown();
                return false;
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <h2 className="font-semibold">Penutupan jadwal</h2>
            </CardHeader>
            <CardContent className="space-y-3 p-4 sm:p-5">
                <p className="text-sm">
                    Setelah ditutup, Rencana Aksi, bukti, pengukuran, dan kegiatan tahun {tahun} tidak dapat diubah melalui pengisian biasa. Data tetap dapat dibaca.
                </p>
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {unknown && (
                    <div role="alert" className="space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                        <p>Hasil penutupan belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.</p>
                        <Link href={`/jadwal/${jadwalId}`} className={linkClass}>
                            Periksa jadwal terbaru
                        </Link>
                    </div>
                )}
                <div className="flex justify-end">
                    <Button type="button" variant="danger" disabled={unknown || Boolean(recovery.recovery)} onClick={() => setOpen(true)}>
                        Tutup Jadwal
                    </Button>
                </div>
            </CardContent>
            <AuditReasonModal
                open={open}
                title={`Tutup jadwal ${tahun}`}
                description="Mutasi normal data kinerja tahun ini dihentikan. Perubahan setelahnya hanya melalui pembukaan kembali jadwal."
                reason={form.data.alasan}
                error={errors.alasan}
                busy={form.processing}
                submitDisabled={form.data.alasan.trim() === ''}
                notice={serverErrors.length > 0 && (
                    <div role="alert" className="mb-4 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                        {serverErrors.map(([field, message]) => (
                            <p key={field}>{message}</p>
                        ))}
                    </div>
                )}
                confirmLabel="Tutup Jadwal"
                destructive
                onReasonChange={(value) => form.setData('alasan', value)}
                onClose={() => !form.processing && setOpen(false)}
                onConfirm={confirm}
            />
        </Card>
    );
}
