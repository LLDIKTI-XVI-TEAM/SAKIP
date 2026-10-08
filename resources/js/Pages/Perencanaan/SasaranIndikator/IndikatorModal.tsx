import React, { useEffect, useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { Switch } from '@/Components/Switch';
import { Button } from '@/Components/Button';
import type {
    SasaranIndikatorCapabilities,
    IndikatorArah,
    IndikatorKinerjaItem,
    IndikatorTipePerhitungan,
    RegulasiOption,
    SasaranStrategisItem,
    UnitOption,
} from '@/types/sasaran-indikator';

import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { DefinitionFields } from '@/Pages/Indikator/Komponen/DefinitionFields';
import { definitionErrors, changedFormRows, rowPayload, formRows, type DefinitionEditor, type DefinitionRow } from '@/Pages/Indikator/Komponen/definition';
import { useDefinitionFeedback } from '@/Pages/Indikator/Komponen/useDefinitionFeedback';

interface IndikatorModalProps {
    isOpen: boolean;
    onClose: () => void;
    sasarans: SasaranStrategisItem[];
    defaultSasaranId?: string;
    units: UnitOption[];
    regulasis: RegulasiOption[];
    indikator: IndikatorKinerjaItem | null;
    editor: DefinitionEditor | null;
    can: SasaranIndikatorCapabilities;
}

export const IndikatorModal: React.FC<IndikatorModalProps> = ({
    isOpen,
    onClose,
    sasarans,
    defaultSasaranId,
    units,
    regulasis,
    indikator: initialIndikator, editor, can,
}) => {
    const [indikator] = useState(initialIndikator);
    const isEdit = Boolean(indikator);
    const [baseline] = useState(editor);
    const feedback = useDefinitionFeedback();
    const submitting = useRef(false);
    const errorSummary = useRef<HTMLDivElement>(null);
    const [definitionOpen, setDefinitionOpen] = useState(indikator?.tipe_perhitungan !== 'manual' && isEdit);
    const [rows, setRows] = useState<DefinitionRow[]>(formRows(baseline?.komponen ?? []));
    const [deleted, setDeleted] = useState<string[]>([]);
    const [submittedKeys, setSubmittedKeys] = useState<string[]>([]);
    const [limitError, setLimitError] = useState('');
    const definitionCan = baseline?.can ?? { create: Boolean(can?.komponen_create), update: false, delete: false, update_indikator: true };
    const definitionReadable = !isEdit || Boolean(baseline?.komponen);

    // Setiap pembukaan dimount ulang setelah pembacaan editor koheren.
    const { data, setData, post, put, processing, errors, transform } = useForm({
        sasaran_strategis_id: indikator?.sasaran_strategis_id ?? defaultSasaranId ?? sasarans[0]?.id ?? '',
        nama: indikator?.nama ?? '',
        definisi_operasional: indikator?.definisi_operasional ?? '',
        satuan: indikator?.satuan ?? '%',
        unit_id: indikator?.unit_id ?? units[0]?.id ?? '',
        arah: indikator?.arah ?? 'naik_baik' as IndikatorArah,
        tipe_perhitungan: indikator?.tipe_perhitungan ?? 'manual' as IndikatorTipePerhitungan,
        presisi: indikator?.presisi ?? 2,
        desimal_tampilan: indikator?.desimal_tampilan ?? 2,
        wajib_catatan: Boolean(indikator?.wajib_catatan),
        regulasi_id: indikator?.regulasi_id ?? '',
        expected_updated_at: baseline?.revision ?? '',
        alasan: '',
    });

    useEffect(() => {
        if (Object.keys(errors).length || limitError) errorSummary.current?.focus();
    }, [errors, limitError]);

    const close = () => { if (!processing && !submitting.current) onClose(); };
    const nestedErrors = definitionErrors(errors, rows, submittedKeys);
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (processing || submitting.current || feedback.blocked || (isEdit && !baseline?.pagination.complete)) return;
        const changed = changedFormRows(rows.filter((row) => !deleted.includes(row.id ?? '')), baseline?.komponen ?? []);
        if (changed.length > 50 || deleted.length > 50) {
            setLimitError('Maksimal 50 komponen baru/berubah dan 50 penghapusan per penyimpanan.');
            return;
        }
        setLimitError('');
        feedback.clearNotice();
        setSubmittedKeys(changed.map((row) => row.client_key));
        const requestId = crypto.randomUUID();
        const includeDefinition = definitionReadable && (changed.length > 0 || deleted.length > 0 || (!isEdit && data.tipe_perhitungan !== 'manual'));
        transform((formData) => ({ ...formData, ...(isEdit && indikator ? { unit_id: indikator.unit_id } : {}),
            regulasi_id: formData.regulasi_id || null, definisi_operasional: formData.definisi_operasional || null,
            ...(includeDefinition ? { komponen: changed.map(rowPayload), hapus_komponen_ids: deleted } : {}), request_id: requestId }));
        const path = isEdit && indikator ? `/perencanaan/indikator/${indikator.id}` : '/perencanaan/indikator';
        submitting.current = true;
        const options = {
            ...feedback.options(requestId, indikator?.id, path, isEdit ? 'put' : 'post', () => {
                submitting.current = false;
                onClose();
            }),
            onFinish: () => { submitting.current = false; },
        };
        if (isEdit) put(path, options); else post(path, options);
    };

    // Nama unit tersimpan untuk tampilan read-only saat edit. Unit aktif
    // difilter server sehingga unit lama yang nonaktif fallback ke nama
    // yang tersimpan pada payload indikator.
    const unitNamaSaatIni =
        units.find((u) => u.id === indikator?.unit_id)?.nama
        ?? indikator?.unit_nama
        ?? indikator?.unit_id
        ?? '';

    return (
        <Modal
            isOpen={isOpen}
            onClose={close}
            title={isEdit ? 'Ubah Indikator Kinerja' : 'Tambah Indikator Kinerja'}
            size="2xl"
            footer={
                <div className="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={close}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        form="indikator-editor-form"
                        disabled={processing || feedback.blocked || (isEdit && !baseline?.pagination.complete)}
                    >
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Indikator'}
                    </Button>
                </div>
            }
        >
            <form id="indikator-editor-form" onSubmit={handleSubmit} className="space-y-4">
                <AuthRecoveryNotice recovery={feedback.recovery} pending={processing} />
                {feedback.failure && <div role="alert"><p>{feedback.failure}</p><Link href="/perencanaan/sasaran-indikator" preserveState={false} className="text-primary underline">Buang draft dan muat ulang data</Link></div>}
                {feedback.notice && <p role="status">{feedback.notice}</p>}
                {(Object.keys(errors).length > 0 || limitError) && <div ref={errorSummary} tabIndex={-1} role="alert" className="text-sm text-danger"><p>{limitError || 'Penyimpanan ditolak. Periksa isian berikut.'}</p><ul>{Object.entries(errors).map(([key, message]) => <li key={key}>{message}</li>)}</ul></div>}
                <fieldset disabled={processing || feedback.blocked} className="space-y-4">
                <div>
                    <Select
                        id="sasaran_strategis_id"
                        label="Sasaran Strategis"
                        value={data.sasaran_strategis_id}
                        onChange={(e) => setData('sasaran_strategis_id', e.target.value)}
                        error={errors.sasaran_strategis_id}
                        required
                    >
                        {sasarans.map((s) => (
                            <option key={s.id} value={s.id}>
                                [{s.kode}] {s.deskripsi.length > 80 ? `${s.deskripsi.substring(0, 80)}...` : s.deskripsi}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        {isEdit && indikator ? (
                            <Input
                                id="indikator_kode"
                                label="Kode Indikator"
                                value={indikator.kode}
                                disabled
                                readOnly
                                helperText="Dibangkitkan otomatis oleh sistem."
                            />
                        ) : (
                            <div className="rounded-lg border border-border bg-soft p-3">
                                <p className="text-xs text-muted">
                                    Kode dibuat otomatis oleh sistem secara berurutan dengan format{' '}
                                    <span className="font-mono font-medium text-ink">IK-01</span>,{' '}
                                    <span className="font-mono font-medium text-ink">IK-02</span>, dan seterusnya.
                                </p>
                            </div>
                        )}
                    </div>
                    <div className="md:col-span-2">
                        <Input
                            id="indikator_nama"
                            label="Nama Indikator Kinerja"
                            placeholder="Nama indikator secara lengkap..."
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            error={errors.nama}
                            required
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Input
                            id="indikator_satuan"
                            label="Satuan"
                            placeholder="Contoh: %, Dokumen, Skor"
                            value={data.satuan}
                            onChange={(e) => setData('satuan', e.target.value)}
                            error={errors.satuan}
                            required
                        />
                    </div>
                    <div className="md:col-span-2">
                        {isEdit ? (
                            <Input
                                id="indikator_unit_id"
                                label="Unit Penanggung Jawab"
                                value={unitNamaSaatIni}
                                disabled
                                readOnly
                                helperText='Untuk mengganti unit, gunakan aksi “Pindah Unit” pada tabel.'
                            />
                        ) : (
                            <Select
                                id="indikator_unit_id"
                                label="Unit Penanggung Jawab"
                                value={data.unit_id}
                                onChange={(e) => setData('unit_id', e.target.value)}
                                error={errors.unit_id}
                                required
                            >
                                {units.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.nama}
                                    </option>
                                ))}
                            </Select>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <Select
                            id="indikator_arah"
                            label="Arah Kinerja"
                            value={data.arah}
                            onChange={(e) => setData('arah', e.target.value as IndikatorArah)}
                            error={errors.arah}
                            required
                        >
                            <option value="naik_baik">Makin Tinggi Makin Baik</option>
                            <option value="turun_baik">Makin Rendah Makin Baik</option>
                        </Select>
                    </div>
                    <div>
                        <Select
                            id="indikator_tipe"
                            label="Tipe Perhitungan"
                            value={data.tipe_perhitungan}
                            onChange={(e) => { setData('tipe_perhitungan', e.target.value as IndikatorTipePerhitungan); setDefinitionOpen(true); }}
                            error={errors.tipe_perhitungan}
                            required
                        >
                            <option value="manual">Manual</option>
                            <option value="rasio_persen">
                                Rasio Persen (%)
                            </option>
                            <option value="penjumlahan">
                                Penjumlahan
                            </option>
                        </Select>
                    </div>
                    <div>
                        <Input
                            id="indikator_presisi"
                            label="Presisi Desimal"
                            type="number"
                            min={0}
                            max={4}
                            value={data.presisi}
                            onChange={(e) => setData('presisi', parseInt(e.target.value, 10) || 0)}
                            error={errors.presisi}
                            required
                        />
                    </div>
                    <div>
                        <Input
                            id="indikator_desimal_tampilan"
                            label="Desimal Tampilan"
                            type="number"
                            min={0}
                            max={4}
                            value={data.desimal_tampilan}
                            onChange={(e) => setData('desimal_tampilan', parseInt(e.target.value, 10) || 0)}
                            error={errors.desimal_tampilan}
                            required
                        />
                    </div>
                </div>

                <div>
                    <Select
                        id="indikator_regulasi_id"
                        label="Regulasi Rujukan (Opsional)"
                        value={data.regulasi_id}
                        onChange={(e) => setData('regulasi_id', e.target.value)}
                        error={errors.regulasi_id}
                    >
                        <option value="">-- Tanpa Regulasi Rujukan --</option>
                        {regulasis.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.jenis.toUpperCase()} No. {r.nomor}/{r.tahun} - {r.tentang.length > 60 ? `${r.tentang.substring(0, 60)}...` : r.tentang}
                            </option>
                        ))}
                    </Select>
                </div>

                <Textarea
                    id="indikator_definisi"
                    label="Definisi Operasional (Opsional)"
                    placeholder="Penjelasan formula, batasan, sumber data, atau metodologi penghitungan..."
                    rows={3}
                    value={data.definisi_operasional}
                    onChange={(e) => setData('definisi_operasional', e.target.value)}
                    error={errors.definisi_operasional}
                />

                <div className="flex flex-col gap-3 pt-2">
                    <div className="flex items-center gap-3">
                        <Switch
                            id="indikator_wajib_catatan"
                            checked={data.wajib_catatan}
                            onChange={(val) => setData('wajib_catatan', val)}
                        />
                        <label htmlFor="indikator_wajib_catatan" className="text-sm font-medium text-ink cursor-pointer">
                            Wajib Melampirkan Catatan Penjelasan saat Pengisian Realisasi
                        </label>
                    </div>
                </div>
                {definitionReadable && <section className="space-y-3 rounded-lg border border-border p-4">
                    <Button type="button" variant="outline" aria-expanded={definitionOpen} onClick={() => setDefinitionOpen((value) => !value)}>Komponen Formula</Button>
                    {definitionOpen && <DefinitionFields prefix="indikator" rows={rows} deleted={deleted} tipe={data.tipe_perhitungan} can={definitionCan} disabled={processing || feedback.blocked} onRows={setRows} onDeleted={setDeleted} errors={nestedErrors} />}
                </section>}
                <Textarea id="indikator-alasan" label="Alasan perubahan" value={data.alasan} onChange={(event) => setData('alasan', event.target.value)} error={errors.alasan} helperText="Wajib saat mengubah tipe atau memperbarui/menghapus komponen; minimal 5 karakter." />
                </fieldset>
            </form>
        </Modal>
    );
};
