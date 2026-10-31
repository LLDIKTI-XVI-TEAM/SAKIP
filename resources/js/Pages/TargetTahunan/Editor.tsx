import { useCallback, useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { createPortal } from 'react-dom';
import type { PendingVisit } from '@inertiajs/core';
import { Link, router, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { anjuranTargetRenstra, labelDeviasi } from './deviasi';
import type { SaranBaseline, TargetEditorProps } from './types';

/** Kalimat sumber saran baseline; tetap benar walau pengguna mengubah angkanya. */
function keteranganSaran(saran: SaranBaseline, tahun: number, formatAngka: (nilai: string) => string): string {
    switch (saran.status) {
        // Guard respons editor memastikan status disahkan selalu membawa nilai dan periode.
        case 'disahkan': return `Saran dari realisasi akhir ${tahun} (${saran.periode}) yang disahkan: ${formatAngka(saran.nilai!)}.`;
        case 'belum_disahkan': return `Realisasi akhir ${tahun} belum disahkan`;
        case 'tidak_dapat_dihitung': return `Realisasi akhir ${tahun} tidak dapat dihitung`;
        case 'negatif': return `Realisasi akhir ${tahun} negatif, tidak dapat menjadi baseline`;
        case 'belum_ada': return `Belum ada realisasi ${tahun} di SAKIP`;
    }
}

/** Satu form memiliki satu pasangan nilai dan versi. Refresh eksplisit mengganti keduanya bersama. */
export function Editor({ editor, path, loading = false, loadError, retryYear, loadRecovery, onClose, onLoad, onSaved }: TargetEditorProps) {
    // Saran baseline menjadi nilai awal, bukan perubahan: menutup tanpa mengubah apa pun tidak meminta konfirmasi,
    // dan saran baru tersimpan bila pengguna menekan Simpan.
    const saran = editor.saran_baseline;
    const initialValues = { baseline: editor.baseline ?? saran?.nilai ?? '', target_tahunan: editor.target_tahunan ?? '',
        alasan: '', rujukan_sumber: '', expected_state: editor.expected_state };
    const form = useForm(initialValues);
    // Penolakan deviasi memakai key sendiri agar hanya galat itu yang membawa tautan ke target Renstra.
    const errors = form.errors as Record<string, string | undefined>;
    const recovery = useAuthRecovery();
    const formatNilai = useFormatNilai();
    const submitting = useRef(false);
    const summary = useRef<HTMLDivElement>(null);
    const dialog = useRef<HTMLDialogElement>(null);
    const opener = useRef<HTMLElement | null>(null);
    const pendingDiscard = useRef<(() => void) | null>(null);
    const approvedVisit = useRef<PendingVisit | null>(null);
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const id = useId();
    const [failure, setFailure] = useState('');
    const busy = loading || form.processing;
    const blocked = Boolean(failure || loadError || recovery.recovery || form.errors.expected_state);
    const editable = editor.can.update && !editor.read_only_reason;
    const requestDiscard = useCallback((action: () => void) => {
        if (busy || submitting.current || pendingDiscard.current) return;
        if (!form.isDirty) { action(); return; }
        pendingDiscard.current = action;
        opener.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        setConfirmDiscard(true);
    }, [busy, form.isDirty]);
    const close = () => requestDiscard(onClose);
    const load = (year: number) => requestDiscard(() => onLoad(year));
    const dismissDiscard = () => { pendingDiscard.current = null; setConfirmDiscard(false); };
    const acceptDiscard = () => {
        if (busy || submitting.current || !pendingDiscard.current) return;
        const action = pendingDiscard.current;
        dismissDiscard();
        action();
    };
    const unknown = () => setFailure('Hasil simpan belum dapat dipastikan. Muat data terbaru sebelum mencoba lagi.');

    useEffect(() => {
        if (form.hasErrors || failure) summary.current?.focus();
    }, [form.hasErrors, form.errors, failure]);

    useEffect(() => {
        if (!confirmDiscard) return;
        const element = dialog.current;
        element?.showModal();
        element?.querySelector<HTMLButtonElement>('button')?.focus();
        return () => { element?.close(); opener.current?.focus(); };
    }, [confirmDiscard]);

    useEffect(() => {
        const unload = (event: BeforeUnloadEvent) => {
            if (form.isDirty || submitting.current) { event.preventDefault(); event.returnValue = ''; }
        };
        window.addEventListener('beforeunload', unload);
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (visit.prefetch) return;
            if (visit.method === 'put' && visit.url.pathname === path) return;
            if (approvedVisit.current?.url.href === visit.url.href && approvedVisit.current.method === visit.method) {
                approvedVisit.current = null;
                return;
            }
            if (busy || submitting.current || pendingDiscard.current) { event.preventDefault(); return; }
            if (!form.isDirty) return;
            event.preventDefault();
            requestDiscard(() => {
                // Visit dibatalkan sebelum request dikirim; lanjutkan intent beserta opsi/callback aslinya sekali.
                approvedVisit.current = visit;
                try { router.visit(visit.url, { ...visit }); }
                finally { approvedVisit.current = null; }
            });
        });
        return () => { window.removeEventListener('beforeunload', unload); off(); };
    }, [busy, form.isDirty, path, requestDiscard]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!editable || busy || blocked || submitting.current || pendingDiscard.current) return;
        submitting.current = true;
        const operationId = crypto.randomUUID();
        // Nilai tetap string: normalisasi desimal/null dan aturan perubahan dimiliki server.
        form.transform((data) => ({ ...data, operation_id: operationId }));
        form.put(path, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const result: unknown = page.flash.target_tahunan;
                // Flash dari tab/operasi lain bukan bukti bahwa pasangan ini sudah tersimpan.
                if (typeof result !== 'object' || result === null || !('operation_id' in result) || result.operation_id !== operationId
                    || !('indikator_id' in result) || result.indikator_id !== editor.indikator_id
                    || !('tahun' in result) || result.tahun !== editor.tahun
                    || !('changed' in result) || typeof result.changed !== 'boolean'
                    || !('target_id' in result) || (result.target_id !== null && (typeof result.target_id !== 'string' || !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(result.target_id)))
                    || (result.changed && result.target_id === null)) {
                    // useForm otomatis menjadikan data sebagai default setelah onSuccess selesai.
                    // Hasil yang belum pasti harus tetap dibandingkan dengan nilai server awal.
                    form.setDefaults(initialValues);
                    unknown();
                    return;
                }
                onSaved(result.changed ? 'Baseline dan target tersimpan.' : 'Tidak ada perubahan.');
                onClose();
            },
            onHttpException: (response) => {
                if (response.status === 403) setFailure('Akses ditolak. Hasil simpan belum dapat dipastikan. Periksa data setelah akses pulih.');
                else if (!recovery.handleHttpException(response, { effectiveMethod: 'put', path, mutation: true })) unknown();
                return false;
            },
            onNetworkError: () => { unknown(); return false; },
            onCancel: unknown,
            onFinish: () => { submitting.current = false; },
        });
    };

    return <><Modal isOpen onClose={close} title="Baseline & target tahunan" description={`${editor.indikator.kode} · ${editor.indikator.nama}`} size="xl" bodyClassName="p-4 sm:p-6" scrollable>
        <form onSubmit={submit} noValidate className="space-y-5" aria-busy={busy}>
            <div className="rounded-lg border border-border bg-soft/50 p-4">
                <dl className="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-x-6 text-sm">
                    <div className="min-w-0">
                        <dt className="text-xs font-semibold text-muted">Renstra</dt>
                        <dd className="truncate text-ink" title={editor.renstra.nama}>{editor.renstra.nama}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-semibold text-muted">Tahun PK</dt>
                        <dd className="font-mono text-ink">{editor.tahun}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-semibold text-muted">Satuan</dt>
                        <dd className="font-mono text-ink">{editor.indikator.satuan}</dd>
                    </div>
                </dl>
            </div>
            {!editable && <p role="status" className="rounded-lg bg-soft p-3 text-sm text-ink">{editor.read_only_reason ?? 'Anda tidak memiliki izin mengubah target.'}</p>}
            <AuthRecoveryNotice recovery={recovery.recovery ?? loadRecovery ?? null} pending={busy} />
            {(form.hasErrors || failure || (loadError && !loadRecovery)) && <div ref={summary} tabIndex={-1} role="alert" className="space-y-2 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger focus:outline-none focus:ring-2 focus:ring-danger/30">
                <p className="font-semibold">{failure || loadError || 'Periksa isian berikut.'}</p>
                {form.hasErrors && <ul className="list-inside list-disc">{Object.entries(form.errors).map(([field, error]) => <li key={field}>{error}
                    {/* Hanya membuka tab Target Renstra; mode revisi dimulai pengguna sendiri di sana. Guard "Buang perubahan?" tetap berlaku. */}
                    {field === 'deviasi_renstra' && editor.tautan_target_renstra && <> <Link href={editor.tautan_target_renstra} className="rounded font-semibold text-primary underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">Buka target Renstra</Link></>}
                </li>)}</ul>}
                {blocked && !recovery.recovery && <><p className="text-ink">Salin isian yang ingin dipertahankan sebelum memuat ulang.</p><Button type="button" variant="outline" size="sm" onClick={() => load(loadError ? retryYear ?? editor.tahun : editor.tahun)} disabled={busy}>{loadError ? 'Coba lagi' : 'Muat data terbaru'}</Button></>}
            </div>}
            {loading && <p role="status" className="rounded-lg bg-soft p-3 text-sm text-muted">Memuat data…</p>}
            <div className="space-y-2">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="min-w-0">
                        <Input label={`Baseline ${editor.baseline_year}`} name="baseline" inputMode="decimal" autoComplete="off" value={form.data.baseline} onChange={(event) => form.setData('baseline', event.target.value)} readOnly={!editable || busy} error={form.errors.baseline} helperText="Nilai acuan tahun sebelumnya; data pendukung, bukan isi dokumen PK." aria-describedby={saran ? `${id}-saran-baseline` : undefined} className="min-w-0 font-mono tabular-nums" />
                        {saran && <p id={`${id}-saran-baseline`} className="mt-1 text-xs text-muted">{keteranganSaran(saran, editor.baseline_year, (nilai) => formatNilai(nilai, editor.indikator.desimal_tampilan))}</p>}
                    </div>
                    <Input label={`Target ${editor.tahun}`} name="target_tahunan" inputMode="decimal" autoComplete="off" value={form.data.target_tahunan} onChange={(event) => form.setData('target_tahunan', event.target.value)} readOnly={!editable || busy} error={form.errors.target_tahunan ?? errors.deviasi_renstra} helperText={editable ? `Maksimal ${editor.indikator.presisi} desimal untuk nilai baru.` : undefined} className="min-w-0 font-mono tabular-nums" />
                </div>
                {editable && <p className="text-xs text-muted">Kosong berarti belum diisi; 0 tetap dihitung sebagai nilai.</p>}
            </div>
            <section aria-labelledby={`${id}-renstra`} className="space-y-3 rounded-lg border border-border p-4">
                <h3 id={`${id}-renstra`} className="sr-only">Pembanding target Renstra</h3>
                {editor.target_renstra !== null ? <>
                    <dl className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <dt className="text-xs font-semibold text-muted">Target Renstra {editor.tahun}</dt>
                        <dd className="font-mono tabular-nums text-ink">{formatNilai(editor.target_renstra, editor.indikator.desimal_tampilan)}</dd>
                        {editor.status_deviasi === 'menyimpang' && <dd><Badge variant="warning">{labelDeviasi(editor.indikator.arah)}</Badge></dd>}
                    </dl>
                    <p className="text-xs text-muted">{anjuranTargetRenstra(editor.indikator.arah)}</p>
                </> : <p className="text-sm text-muted">Target Renstra belum ada</p>}
            </section>
            {editor.has_snapshot && <p className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-ink">
                Perubahan ini tidak mengubah target yang dipakai Rencana Aksi dan pengukuran tahun {editor.tahun}. Perubahan resmi yang mengubah target menunggu revisi PK berversi atau, sebelum itu tersedia, keputusan PM.
            </p>}
            {/* Tampil selama alasan dapat diwajibkan (target sudah tersimpan) dan setelah snapshot untuk rujukan sumber.
                Wajib-tidaknya alasan hanya dinilai server (Q72 §72.2 butir 2, §72.4 butir 4). */}
            {editable && (editor.target_tahunan !== null || editor.has_snapshot) && <section aria-labelledby={`${id}-dasar`} className="space-y-4 border-t border-border pt-5">
                <div>
                    <h3 id={`${id}-dasar`} className="text-sm font-semibold text-ink">Dasar perubahan</h3>
                    <p className="mt-0.5 text-xs text-muted">Alasan wajib bila target diubah; rujukan sumber opsional. Keduanya tercatat di riwayat perubahan.</p>
                </div>
                <Textarea label="Alasan perubahan target (wajib bila target diubah)" name="alasan" autoComplete="off" rows={3} value={form.data.alasan} onChange={(event) => form.setData('alasan', event.target.value)} readOnly={busy} error={form.errors.alasan} maxLength={1000} />
                <Input label="Rujukan sumber (opsional)" name="rujukan_sumber" autoComplete="off" value={form.data.rujukan_sumber} onChange={(event) => form.setData('rujukan_sumber', event.target.value)} readOnly={busy} error={form.errors.rujukan_sumber} maxLength={1000} placeholder="Contoh: Nomor surat keputusan atau dokumen PK" />
            </section>}
            <div className="flex justify-end gap-3 border-t border-border pt-4">
                <Button type="button" variant="outline" disabled={busy} onClick={close}>{editable ? 'Batal' : 'Tutup'}</Button>
                {editable && <Button type="submit" disabled={busy || blocked} isLoading={form.processing}>Simpan</Button>}
            </div>
        </form>
    </Modal>
        {confirmDiscard && createPortal(
            <dialog ref={dialog} aria-labelledby={`${id}-discard-title`} aria-describedby={`${id}-discard-description`}
                onCancel={(event) => { event.preventDefault(); dismissDiscard(); }}
                // Dialog mengelola keyboard sendiri, bukan focus trap editor di belakangnya.
                onKeyDown={(event) => event.stopPropagation()}
                className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-xl border border-border bg-surface p-6 text-ink shadow-xl backdrop:bg-ink/55">
                <h2 id={`${id}-discard-title`} className="text-lg font-semibold">Buang perubahan?</h2>
                <p id={`${id}-discard-description`} className="mt-3 text-sm leading-6 text-muted">Perubahan yang belum disimpan akan hilang.</p>
                <div className="mt-6 flex flex-wrap justify-end gap-3">
                    <Button type="button" variant="outline" onClick={dismissDiscard}>Tetap mengedit</Button>
                    <Button type="button" variant="danger" disabled={busy} onClick={acceptDiscard}>Buang perubahan</Button>
                </div>
            </dialog>, document.body,
        )}
    </>;
}
