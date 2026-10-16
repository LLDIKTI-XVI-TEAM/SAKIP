import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { JendelaPeriode } from '@/Components/Jadwal/JendelaPeriode';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { JadwalPeriode } from '@/types/periode-jadwal';

const tanggal = ['pengisian_mulai', 'pengisian_selesai', 'reviu_mulai', 'reviu_selesai'] as const;
const linkClass = 'text-sm font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/30 rounded';

export function RevisiJendelaPanel({ tahun, onStart }: { tahun: number; onStart: () => void }) {
    return (
        <Card>
            <CardHeader>
                <h2 className="font-semibold">Revisi jendela PIC</h2>
            </CardHeader>
            <CardContent className="space-y-3 p-4 sm:p-5">
                <p className="text-sm">
                    Perpanjang atau buka ulang jendela pengisian dan review periode tahun {tahun} secara resmi. Revisi wajib
                    beralasan dan tercatat di jejak audit.
                </p>
                <div className="flex justify-end">
                    <Button id="revisi-jendela-mulai" type="button" onClick={onStart}>
                        Revisi Jendela PIC
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

interface RevisiJendelaProps {
    jadwalId: string;
    tahun: number;
    /** Revisi jadwal yang sedang tampil; satu-satunya sumber expected_revisi. */
    revisi: number;
    periode: JadwalPeriode[];
    onCancel: () => void;
}

export function RevisiJendela({ jadwalId, tahun, revisi, periode, onCancel }: RevisiJendelaProps) {
    const [open, setOpen] = useState(false);
    const [unknown, setUnknown] = useState(false);
    const recovery = useAuthRecovery();
    const form = useForm({ alasan: '', periode });
    const errors = form.errors as Record<string, string | undefined>;
    // Galat per kolom tampil di tabel; sisanya (penutupan, token, daftar periode) diringkas di atas tombol.
    const umum = Object.entries(errors).filter(([field]) => field !== 'alasan' && !field.startsWith('periode.'));
    const berubah = form.data.periode.some((item, index) => tanggal.some((key) => item[key] !== periode[index]?.[key]));
    const blocked = unknown || Boolean(recovery.recovery);
    const path = `/jadwal/${jadwalId}/revisi-jendela`;

    const markUnknown = () => {
        setOpen(false);
        setUnknown(true);
    };

    const confirm = () => {
        if (form.processing || form.data.alasan.trim() === '') return;
        form.transform((data) => ({
            expected_revisi: revisi,
            alasan: data.alasan,
            periode: data.periode.map(({ periode_id, pengisian_mulai, pengisian_selesai, reviu_mulai, reviu_selesai }) => ({
                periode_id,
                pengisian_mulai,
                pengisian_selesai,
                reviu_mulai,
                reviu_selesai,
            })),
        }));
        form.post(path, {
            preserveScroll: true,
            // Hanya revisi terkonfirmasi yang memuat ulang editor beserta revisi jadwal terbaru.
            preserveState: (page) => !page.flash.success,
            onSuccess: (page) => {
                if (!page.flash.success) markUnknown();
            },
            onError: (validation) => {
                if (!validation.alasan) setOpen(false);
            },
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
        <div className="space-y-3">
            <JendelaPeriode
                periode={form.data.periode}
                errors={errors}
                editable={false}
                disabled={form.processing || blocked}
                options={[]}
                emptyMessage=""
                onAdd={() => undefined}
                onRemove={() => undefined}
                onChange={(periodeId, field, value) =>
                    form.setData(
                        'periode',
                        form.data.periode.map((item) => (item.periode_id === periodeId ? { ...item, [field]: value } : item)),
                    )
                }
            />
            <Card>
                <CardContent className="space-y-3 p-4 sm:p-5">
                    <p className="text-sm">
                        Ubah tanggal yang perlu direvisi. Batas baru langsung berlaku bagi PIC; penugasan, izin unit, dan
                        status data tetap diperiksa.
                    </p>
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                    {unknown && (
                        <div role="alert" className="space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                            <p>Hasil revisi belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.</p>
                            <Link href={`/jadwal/${jadwalId}`} className={linkClass}>
                                Periksa jadwal terbaru
                            </Link>
                        </div>
                    )}
                    {umum.length > 0 && (
                        <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                            {umum.map(([field, message]) => (
                                <p key={field}>{message}</p>
                            ))}
                        </div>
                    )}
                    <div className="flex flex-wrap justify-end gap-3">
                        <Button type="button" variant="outline" disabled={form.processing} onClick={onCancel}>
                            Batal
                        </Button>
                        <Button type="button" disabled={!berubah || blocked || form.processing} onClick={() => setOpen(true)}>
                            Simpan revisi
                        </Button>
                    </div>
                </CardContent>
            </Card>
            <AuditReasonModal
                open={open}
                title={`Revisi jendela PIC ${tahun}`}
                description="Jendela baru langsung berlaku bagi PIC dan tercatat di jejak audit beserta batas lamanya."
                reason={form.data.alasan}
                error={errors.alasan}
                busy={form.processing}
                submitDisabled={form.data.alasan.trim() === ''}
                confirmLabel="Simpan revisi"
                onReasonChange={(value) => form.setData('alasan', value)}
                onClose={() => !form.processing && setOpen(false)}
                onConfirm={confirm}
            />
        </div>
    );
}
