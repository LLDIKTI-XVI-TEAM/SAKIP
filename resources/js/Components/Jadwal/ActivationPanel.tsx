import { useEffect, useRef, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { Modal } from '@/Components/Modal';
import { Textarea } from '@/Components/Textarea';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { JadwalActivationOutcome, JadwalActivationReadiness, JadwalStatus } from '@/types/periode-jadwal';

interface ActivationPanelProps {
    jadwalId: string;
    tahun: number;
    /** Revisi kalender yang sedang tampil dan tersimpan; satu-satunya sumber expected_revisi. */
    revisi: number;
    status: JadwalStatus;
    /** Form kalender punya perubahan belum disimpan; aktivasi selalu memakai versi tersimpan. */
    dirtyCalendar: boolean;
    periodeNames: Record<string, string>;
}

const GATE_LABELS = { G1: 'Perjanjian Kinerja', G2: 'Target tahunan', G3: 'Tahun dalam Renstra', G4: 'Lampiran PK' } as const;
const GATE_STATUS = {
    lolos: { text: 'Lolos', icon: '✓', color: 'text-success-dark' },
    pengecualian: { text: 'Pengecualian', icon: '!', color: 'text-warning-dark' },
    gagal: { text: 'Gagal', icon: '✕', color: 'text-danger' },
} as const;
const UNKNOWN = 'Hasil aktivasi belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.';
const linkClass = 'text-sm font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/30 rounded';

/** Sukses hanya bila flash berbentuk outcome lengkap untuk operasi dan jadwal request ini. */
function matchingOutcome(value: unknown, operationId: string, jadwalId: string): JadwalActivationOutcome | null {
    if (typeof value !== 'object' || value === null) return null;
    const outcome = value as Partial<JadwalActivationOutcome>;
    return outcome.operation_id === operationId && outcome.jadwal_id === jadwalId && outcome.status === 'aktif'
        && typeof outcome.changed === 'boolean' && typeof outcome.revisi === 'number' && typeof outcome.activated_at === 'string'
        ? (outcome as JadwalActivationOutcome)
        : null;
}

export function ActivationPanel({ jadwalId, tahun, revisi, status, dirtyCalendar, periodeNames }: ActivationPanelProps) {
    const [readiness, setReadiness] = useState<JadwalActivationReadiness | null>(null);
    const [loadFailed, setLoadFailed] = useState(false);
    const [check, setCheck] = useState(0);
    const [open, setOpen] = useState(false);
    const [notice, setNotice] = useState<{ kind: 'success' | 'unknown'; text: string } | null>(null);
    const latest = useRef(0);
    const submitting = useRef(false);
    const recovery = useAuthRecovery();
    const form = useForm({ alasan: '' });

    useEffect(() => {
        if (status !== 'draft') return;
        // Hanya respons permintaan terbaru yang dipakai; respons lama/out-of-order dibuang.
        const sequence = ++latest.current;
        const controller = new AbortController();
        setLoadFailed(false);
        void fetch(`/jadwal/${jadwalId}/kesiapan-aktivasi`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(async (response) => {
                if (!response.ok) throw new Error(String(response.status));
                const next: JadwalActivationReadiness = await response.json();
                if (sequence !== latest.current) return;
                setReadiness(next);
                // Jadwal ternyata sudah aktif: muat ulang detail kanonis agar editor ikut read-only.
                if (next.blockers.some((blocker) => blocker.code === 'sudah_aktif')) router.reload();
            })
            .catch(() => {
                if (sequence === latest.current && !controller.signal.aborted) {
                    setReadiness(null);
                    setLoadFailed(true);
                }
            });
        return () => controller.abort();
    }, [jadwalId, revisi, status, check]);

    const markUnknown = () => {
        setOpen(false);
        setNotice({ kind: 'unknown', text: UNKNOWN });
    };

    // Pratinjau milik kalender lain tidak boleh dipakai; revisi baru hanya diambil lewat muat ulang detail penuh.
    const stale = readiness !== null && (readiness.jadwal_id !== jadwalId || readiness.checked_revisi !== revisi);
    const canSubmit = readiness?.allowed === true && !stale && !dirtyCalendar && notice?.kind !== 'unknown' && !recovery.recovery;

    const confirm = () => {
        if (!canSubmit || submitting.current || form.data.alasan.trim() === '') return;
        submitting.current = true;
        const operationId = crypto.randomUUID();
        form.transform((data) => ({ expected_revisi: revisi, operation_id: operationId, alasan: data.alasan }));
        form.post(`/jadwal/${jadwalId}/aktivasi`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const outcome = matchingOutcome(page.flash.jadwal_aktivasi, operationId, jadwalId);
                if (!outcome) return markUnknown();
                setOpen(false);
                setNotice({ kind: 'success', text: outcome.changed ? 'Jadwal berhasil diaktifkan.' : 'Jadwal sudah aktif.' });
            },
            onHttpException: (response) => {
                // Status akhir bisa berasal dari GET redirect sesudah commit; jangan anggap gagal dan jangan ulang otomatis.
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/jadwal/${jadwalId}/aktivasi`, mutation: true })) markUnknown();
                else setOpen(false);
                return false;
            },
            onNetworkError: () => {
                markUnknown();
                return false;
            },
            onCancel: markUnknown,
            // Penolakan server bisa berarti sumber berubah; pratinjau lama tidak boleh tetap tampil siap.
            onError: () => setCheck((value) => value + 1),
            onFinish: () => {
                submitting.current = false;
            },
        });
    };

    if (status !== 'draft' && notice === null) return null;
    const errors = form.errors as Record<string, string | undefined>;
    const serverErrors = Object.entries(errors).filter(([field]) => field !== 'alasan');
    const pastNames = (readiness?.periode_lampau_ids ?? []).map((id) => periodeNames[id]).filter(Boolean);

    return (
        <Card>
            <CardHeader>
                <h2 className="font-semibold">
                    Kesiapan aktivasi
                </h2>
                {readiness && status === 'draft' && (
                    <span className={`text-sm font-medium ${canSubmit ? 'text-success-dark' : 'text-danger'}`}>{canSubmit ? 'Siap diaktifkan' : 'Belum siap'}</span>
                )}
            </CardHeader>
            <CardContent className="space-y-4 p-4 sm:p-5">
                {notice?.kind === 'success' && (
                    <p role="status" className="rounded-lg border border-success/30 bg-success/10 p-3 text-sm">
                        {notice.text}
                    </p>
                )}
                {notice?.kind === 'unknown' && (
                    <div role="alert" className="space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                        <p>{notice.text}</p>
                        <Link href={`/jadwal/${jadwalId}`} className={linkClass}>
                            Periksa jadwal terbaru
                        </Link>
                    </div>
                )}
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {/* Modal tertutup dan submit terkunci saat hasil ambigu; alasan tetap terlihat agar dapat disalin, tanpa jalur kirim ulang. */}
                {(notice?.kind === 'unknown' || recovery.recovery) && form.data.alasan.trim() !== '' && (
                    <Textarea label="Alasan belum terkonfirmasi" readOnly value={form.data.alasan} rows={2} />
                )}
                {status === 'draft' && (
                    <>
                        {loadFailed && <p className="text-sm text-danger">Kesiapan belum dapat dimuat. Coba periksa ulang.</p>}
                        {!readiness && !loadFailed && (
                            <p role="status" className="text-sm text-muted">
                                Memeriksa kesiapan…
                            </p>
                        )}
                        {stale && (
                            <div role="alert" className="space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                                <p>Kalender sudah berubah sejak halaman dibuka. Muat ulang detail sebelum mengaktifkan.</p>
                                <Link href={`/jadwal/${jadwalId}`} className={linkClass}>
                                    Muat ulang detail
                                </Link>
                            </div>
                        )}
                        {dirtyCalendar && (
                            <p className="rounded-lg border border-border bg-soft p-3 text-sm">
                                Simpan atau batalkan perubahan kalender sebelum mengaktifkan; aktivasi memakai kalender tersimpan.
                            </p>
                        )}
                        {readiness && (
                            <>
                        {readiness.blockers.length > 0 && (
                            <div className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                                <p className="font-semibold">Perlu diselesaikan</p>
                                <ul className="mt-1 list-inside list-disc space-y-1">
                                    {readiness.blockers.map((blocker) => (
                                        <li key={blocker.code}>{blocker.message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                            <ul className="divide-y divide-border">
                                {readiness.gates.map((gate) => {
                                    const tone = GATE_STATUS[gate.status];
                                    return (
                                        <li key={gate.key} className="grid grid-cols-[28px_1fr_auto] items-start gap-2 py-2.5">
                                            <span aria-hidden="true" className={`grid h-6 w-6 place-items-center text-sm font-semibold ${tone.color}`}>
                                                {tone.icon}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="text-sm">{GATE_LABELS[gate.key]}</p>
                                                <p className="text-xs text-muted">{gate.message}</p>
                                            </div>
                                            <span className={`text-xs font-medium ${tone.color}`}>{tone.text}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                            <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                                <span><b className="font-mono text-ink">{readiness.counts.indikator_berlaku}</b> indikator</span>
                                <span><b className="font-mono text-ink">{readiness.counts.snapshot_baru}</b> snapshot baru</span>
                                <span><b className="font-mono text-ink">{readiness.counts.komponen_baru}</b> komponen</span>
                                {readiness.counts.snapshot_existing > 0 && (
                                    <span><b className="font-mono text-ink">{readiness.counts.snapshot_existing}</b> sudah dibekukan sebelumnya</span>
                                )}
                            </p>
                            </>
                        )}
                        {pastNames.length > 0 && (
                            <p className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                                Pengisian {pastNames.join(', ')} sudah lewat. Periode tersebut tetap dibekukan.
                            </p>
                        )}
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" className={linkClass} onClick={() => setCheck((value) => value + 1)}>
                                Periksa ulang
                            </button>
                            <Button type="button" disabled={!canSubmit} onClick={() => setOpen(true)}>
                                Aktifkan jadwal
                            </Button>
                        </div>
                    </>
                )}
            </CardContent>
            <Modal
                isOpen={open}
                onClose={() => !form.processing && setOpen(false)}
                title={`Aktifkan jadwal ${tahun}`}
                footer={
                    <>
                        <Button type="button" variant="outline" disabled={form.processing} onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="button" isLoading={form.processing} disabled={!canSubmit || form.data.alasan.trim() === ''} onClick={confirm}>
                            Aktifkan
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <p className="text-sm">Target dan formula untuk pengukuran dikunci. Data yang sudah dikunci tetap digunakan.</p>
                    {serverErrors.length > 0 && (
                        <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                            {serverErrors.map(([field, message]) => (
                                <p key={field}>{message}</p>
                            ))}
                        </div>
                    )}
                    <Textarea
                        label="Alasan"
                        name="alasan"
                        required
                        maxLength={1000}
                        placeholder="Contoh: PK sudah ditandatangani"
                        value={form.data.alasan}
                        error={errors.alasan}
                        disabled={form.processing}
                        onChange={(event) => form.setData('alasan', event.target.value)}
                    />
                </div>
            </Modal>
        </Card>
    );
}
