import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { RentangPeriode } from '@/Components/Periode/RentangPeriode';
import { Select } from '@/Components/Select';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { IndikatorBelumTerjadwal, JadwalPeriode } from '@/types/periode-jadwal';

interface TambahIndikatorPanelProps {
    jadwalId: string;
    tahun: number;
    /** Jendela jadwal yang sedang tampil; satu-satunya sumber pilihan periode mulai. */
    periode: JadwalPeriode[];
    kandidat: IndikatorBelumTerjadwal[];
    periodeMulaiDefault: string | null;
    perjanjianKinerjaId: string | null;
}

const linkClass = 'text-sm font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/30 rounded';

/** Menambah indikator resmi ke jadwal aktif; kelayakan, izin, dan isi snapshot tetap dinilai ulang server. */
export function TambahIndikatorPanel({ jadwalId, tahun, periode, kandidat, periodeMulaiDefault, perjanjianKinerjaId }: TambahIndikatorPanelProps) {
    const [pilihan, setPilihan] = useState<IndikatorBelumTerjadwal | null>(null);
    const [unknown, setUnknown] = useState(false);
    const recovery = useAuthRecovery();
    const form = useForm({ indikator_id: '', periode_mulai_id: '', alasan: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const serverErrors = Object.entries(errors).filter(([field]) => field !== 'alasan' && field !== 'periode_mulai_id');
    const urut = [...periode].sort((a, b) => a.urutan - b.urutan);
    const terpilih = urut.find((item) => item.periode_id === form.data.periode_mulai_id);
    const tidakDapatDikirim = form.data.alasan.trim() === '' || form.data.periode_mulai_id === '' || Boolean(recovery.recovery);
    const path = `/jadwal/${jadwalId}/indikator`;

    // Satu form untuk seluruh baris: alasan dan error baris sebelumnya tidak boleh terbawa. Diisi eksplisit, bukan
    // reset(), karena setelah sukses useForm menjadikan data terkirim sebagai default baru.
    const buka = (indikator: IndikatorBelumTerjadwal) => {
        form.setData({ indikator_id: indikator.id, periode_mulai_id: periodeMulaiDefault ?? '', alasan: '' });
        form.clearErrors();
        setPilihan(indikator);
    };

    const markUnknown = () => {
        setPilihan(null);
        setUnknown(true);
    };

    const confirm = () => {
        if (form.processing || tidakDapatDikirim) return;
        form.post(path, {
            preserveScroll: true,
            onSuccess: () => setPilihan(null),
            // Hasil ambigu tidak diulang otomatis: penambahan yang sudah tersimpan akan ditolak sebagai duplikat.
            // Pemulihan sesi menjaga modal terbuka agar alasan dan periode dapat disalin sebelum masuk ulang.
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) markUnknown();
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
                <h2 className="font-semibold">Indikator belum masuk jadwal</h2>
            </CardHeader>
            <CardContent className="space-y-3 p-4 sm:p-5">
                {pilihan === null && <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />}
                {unknown && (
                    <div role="alert" className="space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                        <p>Hasil penambahan belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.</p>
                        <Link href={`/jadwal/${jadwalId}`} className={linkClass}>
                            Periksa jadwal terbaru
                        </Link>
                    </div>
                )}
                <ul className="divide-y divide-border">
                    {kandidat.map((indikator) => (
                        <li key={indikator.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <p className="text-sm font-semibold">{indikator.kode}</p>
                                <p className="text-sm text-muted">{indikator.nama}</p>
                            </div>
                            {!indikator.target_terisi ? (
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    <span className="text-muted">Target PK belum diisi</span>
                                    {perjanjianKinerjaId && (
                                        <Link href={`/perjanjian-kinerja/${perjanjianKinerjaId}`} className={linkClass}>
                                            Isi target
                                        </Link>
                                    )}
                                </div>
                            ) : !indikator.dapat_dibekukan ? (
                                <span className="text-sm text-muted">Perbaiki data indikator di Sasaran & Indikator</span>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    aria-label={`Tambahkan ${indikator.kode}`}
                                    disabled={unknown || Boolean(recovery.recovery)}
                                    onClick={() => buka(indikator)}
                                >
                                    Tambahkan
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            </CardContent>
            <AuditReasonModal
                open={pilihan !== null}
                title={pilihan ? `Tambahkan ${pilihan.kode} ke jadwal ${tahun}` : ''}
                reason={form.data.alasan}
                error={errors.alasan}
                busy={form.processing}
                submitDisabled={tidakDapatDikirim}
                notice={
                    <div className="mb-4 space-y-3">
                        {pilihan !== null && <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />}
                        {serverErrors.length > 0 && (
                            <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                                {serverErrors.map(([field, message]) => (
                                    <p key={field}>{message}</p>
                                ))}
                            </div>
                        )}
                        <div>
                            <Select
                                id="tambah-indikator-periode-mulai"
                                label="Periode mulai berlaku"
                                required
                                value={form.data.periode_mulai_id}
                                error={errors.periode_mulai_id}
                                onChange={(event) => form.setData('periode_mulai_id', event.target.value)}
                                options={[{ value: '', label: 'Pilih periode', disabled: true }, ...urut.map((item) => ({ value: item.periode_id, label: item.nama }))]}
                            />
                            <RentangPeriode tanggal={terpilih ?? null} tahun={tahun} className="mt-1 block text-xs text-muted" />
                        </div>
                        {terpilih && (
                            <p className="text-sm">
                                Periode sebelum {terpilih.nama} tampil Tidak berlaku. Pilihan tidak dapat diubah setelah disimpan. Jendela pengisian PIC tidak berubah.
                            </p>
                        )}
                    </div>
                }
                reasonRequired
                confirmLabel="Tambahkan"
                onReasonChange={(value) => form.setData('alasan', value)}
                onClose={() => !form.processing && setPilihan(null)}
                onConfirm={confirm}
            />
        </Card>
    );
}
