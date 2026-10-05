import { useCallback, useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { createPortal } from 'react-dom';
import type { PendingVisit } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Button } from '@/Components/Button';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { TargetEditorProps } from './types';

/** Satu form memiliki satu pasangan nilai dan versi. Refresh eksplisit mengganti keduanya bersama. */
export function Editor({ editor, loading = false, loadError, retryYear, loadRecovery, onClose, onLoad, onSaved }: TargetEditorProps) {
    const initialValues = { baseline: editor.baseline ?? '', target_tahunan: editor.target_tahunan ?? '', alasan: '', rujukan_sumber: '', expected_state: editor.expected_state };
    const form = useForm(initialValues);
    const recovery = useAuthRecovery();
    const submitting = useRef(false);
    const summary = useRef<HTMLDivElement>(null);
    const dialog = useRef<HTMLDialogElement>(null);
    const opener = useRef<HTMLElement | null>(null);
    const pendingDiscard = useRef<(() => void) | null>(null);
    const approvedVisit = useRef<PendingVisit | null>(null);
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const id = useId();
    const [failure, setFailure] = useState('');
    const path = `/perencanaan/indikator/${editor.indikator_id}/target-tahunan/${editor.tahun}`;
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

    const firstYear = Math.max(editor.renstra.tahun_mulai, editor.indikator.tahun_mulai_berlaku);
    const years = Array.from({ length: editor.renstra.tahun_selesai - firstYear + 1 }, (_, i) => firstYear + i);
    return <><Modal isOpen onClose={close} title="Baseline & target tahunan" description={`${editor.indikator.kode} · ${editor.indikator.nama}`} size="2xl" bodyClassName="p-4 sm:p-6" scrollable>
        <form onSubmit={submit} noValidate className="space-y-5" aria-busy={busy}>
            <div className="flex flex-wrap items-end gap-4">
                <div className="w-32"><Select label="Tahun" name="target-year" value={editor.tahun} disabled={busy} options={years.map((year) => ({ value: year, label: String(year) }))} onChange={(event) => load(Number(event.target.value))} /></div>
                <p className="min-w-0 flex-1 pb-2 text-sm text-muted break-words">{editor.renstra.nama} · {editor.indikator.satuan}</p>
            </div>
            {!editable && <p role="status" className="rounded-lg bg-soft p-3 text-sm">{editor.read_only_reason ?? 'Anda tidak memiliki izin mengubah target.'}</p>}
            <AuthRecoveryNotice recovery={recovery.recovery ?? loadRecovery ?? null} pending={busy} />
            {(form.hasErrors || failure || (loadError && !loadRecovery)) && <div ref={summary} tabIndex={-1} role="alert" className="space-y-2 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-danger/30">
                <p className="font-semibold">{failure || loadError || 'Periksa isian berikut.'}</p>
                {form.hasErrors && <ul className="list-inside list-disc">{Object.entries(form.errors).map(([field, error]) => <li key={field}>{error}</li>)}</ul>}
                {blocked && !recovery.recovery && <><p>Salin isian yang ingin dipertahankan sebelum memuat ulang.</p><Button type="button" variant="outline" onClick={() => load(loadError ? retryYear ?? editor.tahun : editor.tahun)} disabled={busy}>{loadError ? 'Coba lagi' : 'Muat data terbaru'}</Button></>}
            </div>}
            {loading && <p role="status" className="text-sm text-muted">Memuat data…</p>}
            <div className="grid grid-cols-2 gap-3 sm:gap-5">
                <Input label={`Baseline ${editor.baseline_year}`} name="baseline" inputMode="decimal" autoComplete="off" value={form.data.baseline} onChange={(event) => form.setData('baseline', event.target.value)} readOnly={!editable || busy} error={form.errors.baseline} helperText="Nilai acuan tahun sebelumnya." className="min-w-0 font-mono tabular-nums" />
                <Input label={`Target ${editor.tahun}`} name="target_tahunan" inputMode="decimal" autoComplete="off" value={form.data.target_tahunan} onChange={(event) => form.setData('target_tahunan', event.target.value)} readOnly={!editable || busy} error={form.errors.target_tahunan} helperText={editable ? `Maksimal ${editor.indikator.presisi} desimal untuk nilai baru.` : undefined} className="min-w-0 font-mono tabular-nums" />
            </div>
            {editable && <p className="text-xs text-muted">Kosong berarti belum diisi; 0 tetap dihitung sebagai nilai.</p>}
            {editor.has_snapshot && <p className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm">Perubahan ini tidak mengubah data pengukuran sebelumnya.</p>}
            {editable && editor.has_snapshot && <div className="space-y-3 border-t border-border pt-4">
                <p className="text-xs text-muted">Alasan dan rujukan wajib jika nilai diubah.</p>
                <Input label="Alasan perubahan" name="alasan" autoComplete="off" value={form.data.alasan} onChange={(event) => form.setData('alasan', event.target.value)} readOnly={busy} error={form.errors.alasan} maxLength={1000} />
                <Input label="Rujukan sumber" name="rujukan_sumber" autoComplete="off" value={form.data.rujukan_sumber} onChange={(event) => form.setData('rujukan_sumber', event.target.value)} readOnly={busy} error={form.errors.rujukan_sumber} maxLength={1000} />
            </div>}
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
