import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { DefinitionFields } from '@/Pages/Indikator/Komponen/DefinitionFields';
import { definitionErrors, changedFormRows, rowPayload, formRows, newRow, type DefinitionEditor, type DefinitionRow } from '@/Pages/Indikator/Komponen/definition';
import { useDefinitionFeedback } from '@/Pages/Indikator/Komponen/useDefinitionFeedback';
import type { IndikatorTipePerhitungan } from '@/types/sasaran-indikator';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    editor: DefinitionEditor;
    returnTo?: 'komponen' | 'sasaran-indikator';
    addRow?: boolean;
    focusComponentId?: string;
}
interface FormulaForm {
    tipe_perhitungan: IndikatorTipePerhitungan;
    presisi: number | string;
    komponen: DefinitionRow[];
    hapus_komponen_ids: string[];
    expected_updated_at: string;
    alasan: string;
}

export function FormulaModal({ isOpen, onClose, editor, returnTo = 'sasaran-indikator', addRow = false, focusComponentId }: Props) {
    // Mount per pembukaan: props baru tidak mengganti baseline draft yang sedang diedit.
    const [baseline] = useState(editor);
    const originalRows = baseline.komponen ?? [];
    const form = useForm<FormulaForm>({
        tipe_perhitungan: baseline.indikator.tipe_perhitungan,
        presisi: baseline.indikator.presisi,
        komponen: [...formRows(originalRows), ...(addRow ? [newRow(baseline.indikator.tipe_perhitungan, originalRows.length + 1)] : [])],
        hapus_komponen_ids: [],
        expected_updated_at: baseline.revision,
        alasan: '',
    });
    const feedback = useDefinitionFeedback();
    const submitting = useRef(false);
    const summary = useRef<HTMLDivElement>(null);
    const [limitError, setLimitError] = useState('');
    const [submittedKeys, setSubmittedKeys] = useState<string[]>([]);
    const canEdit = baseline.can.create || baseline.can.update || baseline.can.delete || baseline.can.update_indikator;
    const disabled = form.processing || feedback.blocked || !baseline.pagination.complete || baseline.komponen === null || !canEdit;
    const errors = definitionErrors(form.errors, form.data.komponen, submittedKeys);
    const refreshPath = returnTo === 'komponen' ? `/indikator/${baseline.indikator.id}/komponen` : '/perencanaan/sasaran-indikator';

    useEffect(() => {
        if (focusComponentId) document.getElementById(`formula-${focusComponentId}-label`)?.focus();
    }, [focusComponentId]);
    useEffect(() => {
        if (form.hasErrors || limitError) summary.current?.focus();
    }, [form.hasErrors, form.errors, limitError]);

    const close = () => { if (!form.processing && !submitting.current) onClose(); };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (disabled || submitting.current) return;
        const retained = form.data.komponen.filter((row) => !form.data.hapus_komponen_ids.includes(row.id ?? ''));
        const changed = changedFormRows(retained, originalRows);
        const intents = changed.map(rowPayload);
        if (intents.length > 50 || form.data.hapus_komponen_ids.length > 50) {
            setLimitError('Satu penyimpanan dapat memuat maksimal 50 komponen baru/berubah dan 50 penghapusan. Kurangi perubahan sebelum menyimpan.');
            return;
        }
        setLimitError('');
        feedback.clearNotice();
        const requestId = crypto.randomUUID();
        setSubmittedKeys(changed.map((row) => row.client_key));
        form.transform(() => ({
            tipe_perhitungan: form.data.tipe_perhitungan,
            presisi: form.data.presisi,
            komponen: intents,
            hapus_komponen_ids: form.data.hapus_komponen_ids,
            expected_updated_at: form.data.expected_updated_at,
            alasan: form.data.alasan,
            request_id: requestId,
            return_to: returnTo,
        }));
        submitting.current = true;
        const path = `/perencanaan/indikator/${baseline.indikator.id}/formula`;
        form.patch(path, {
            ...feedback.options(requestId, baseline.indikator.id, path, 'patch', () => {
                submitting.current = false;
                onClose();
            }),
            onFinish: () => { submitting.current = false; },
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={close}
            title={`Atur Formula — ${baseline.indikator.kode}`}
            size="2xl"
            footer={(
                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={close} disabled={form.processing}>Batal</Button>
                    <Button type="submit" form="formula-editor-form" isLoading={form.processing} disabled={disabled}>Simpan Formula</Button>
                </div>
            )}
        >
            <form id="formula-editor-form" onSubmit={submit} className="space-y-4">
                <AuthRecoveryNotice recovery={feedback.recovery} pending={form.processing} />
                {feedback.failure && (
                    <div role="alert" className="space-y-2 rounded-lg bg-soft p-3 text-sm">
                        <p>{feedback.failure}</p>
                        <Link preserveState={false} href={refreshPath} className="font-medium text-primary underline">Buang draft dan muat ulang data</Link>
                    </div>
                )}
                {feedback.notice && <p role="status" className="text-sm text-ink">{feedback.notice}</p>}
                {(form.hasErrors || limitError) && (
                    <div ref={summary} tabIndex={-1} role="alert" className="rounded-lg border border-danger/30 p-3 text-sm text-danger">
                        <p>{limitError || 'Penyimpanan ditolak. Periksa isian berikut.'}</p>
                        <ul>{Object.entries(form.errors).map(([key, value]) => <li key={key}>{value}</li>)}</ul>
                        {(errors.konflik || errors.expected_updated_at) && (
                            <Link preserveState={false} href={refreshPath} className="mt-2 inline-block font-medium underline">Buang draft dan muat ulang data</Link>
                        )}
                    </div>
                )}
                {baseline.komponen === null && <p role="alert">Data komponen formula tidak dapat diakses.</p>}
                <fieldset disabled={disabled} className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Select
                            id="formula_tipe" label="Tipe Perhitungan Target"
                            disabled={!baseline.can.update_indikator} value={form.data.tipe_perhitungan} error={errors.tipe_perhitungan}
                            onChange={(event) => {
                                const tipe = event.target.value;
                                if (tipe === 'manual' || tipe === 'rasio_persen' || tipe === 'penjumlahan') form.setData('tipe_perhitungan', tipe);
                            }}
                        >
                            <option value="manual">Manual</option>
                            <option value="rasio_persen">Rasio Persen (%)</option>
                            <option value="penjumlahan">Penjumlahan</option>
                        </Select>
                        <Input
                            id="formula_presisi" label="Presisi Desimal" type="number" min={0} max={4}
                            disabled={!baseline.can.update_indikator} value={form.data.presisi}
                            onChange={(event) => form.setData('presisi', event.target.value)} error={errors.presisi}
                        />
                    </div>
                    <DefinitionFields
                        prefix="formula" rows={form.data.komponen} deleted={form.data.hapus_komponen_ids}
                        tipe={form.data.tipe_perhitungan} can={baseline.can} disabled={disabled}
                        onRows={(rows) => form.setData('komponen', rows)} onDeleted={(ids) => form.setData('hapus_komponen_ids', ids)} errors={errors}
                    />
                    <Textarea
                        id="formula_alasan" label="Alasan perubahan formula" value={form.data.alasan}
                        onChange={(event) => form.setData('alasan', event.target.value)} error={errors.alasan}
                        helperText="Wajib untuk perubahan sensitif; 5–1.000 karakter."
                    />
                </fieldset>
            </form>
        </Modal>
    );
}
