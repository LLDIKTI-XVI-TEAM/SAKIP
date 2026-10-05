import React, { useMemo } from 'react';
import { CustomSelect, CustomSelectOption } from '@/Components/CustomSelect';
import { Input } from '@/Components/Input';
import { LampiranDraft, LampiranFormSection } from '@/Components/LampiranFormSection';
import { Textarea } from '@/Components/Textarea';
import type { RegulasiOption, RenstraFormData } from '@/types/renstra';

type RenstraEditableField = Exclude<keyof RenstraFormData, '_method' | 'lampiran'>;
type SetRenstraField = <K extends RenstraEditableField>(field: K, value: RenstraFormData[K]) => void;

interface RenstraFormFieldsProps {
    data: RenstraFormData;
    errors: Record<string, string | undefined>;
    regulasiOptions: RegulasiOption[];
    disabled?: boolean;
    isEdit?: boolean;
    requireReason?: boolean;
    canReadRegulasi?: boolean;
    canUploadAttachment?: boolean;
    attachmentLocked?: boolean;
    setField: SetRenstraField;
    setLampiran: (updater: (previous: LampiranDraft[]) => LampiranDraft[]) => void;
}

export function RenstraFormFields({
    data,
    errors,
    regulasiOptions,
    disabled = false,
    isEdit = false,
    requireReason = false,
    canReadRegulasi = false,
    canUploadAttachment = true,
    attachmentLocked = false,
    setField,
    setLampiran,
}: RenstraFormFieldsProps) {
    const regulasiSelectOptions: CustomSelectOption[] = useMemo(() => {
        return regulasiOptions.map((reg) => ({
            value: String(reg.id),
            label: `${reg.nomor} (${reg.tahun}) - ${reg.tentang.length > 55 ? `${reg.tentang.slice(0, 55)}...` : reg.tentang}`,
        }));
    }, [regulasiOptions]);

    return (
        <div className="space-y-6">
            <section aria-labelledby="identitas-renstra-heading">
                <div className="mb-3">
                    <h2 id="identitas-renstra-heading" className="text-base font-semibold text-ink leading-tight">
                        Identitas Rencana Strategis
                    </h2>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <Input
                            name="nama"
                            label="Nama Rencana Strategis"
                            value={data.nama}
                            onChange={(event) => setField('nama', event.target.value)}
                            error={errors.nama}
                            placeholder="Contoh: Rencana Strategis LLDIKTI Wilayah XVI 2025-2029"
                            disabled={disabled}
                            required
                        />
                    </div>

                    <Input
                        name="kode"
                        label="Kode Dokumen"
                        value={data.kode}
                        onChange={(event) => setField('kode', event.target.value)}
                        error={errors.kode}
                        placeholder="Contoh: RENSTRA-2025-2029"
                        disabled={disabled}
                        required
                    />

                    {canReadRegulasi ? (
                        <CustomSelect
                            id="regulasi_id"
                            name="regulasi_id"
                            label="Rujukan Regulasi Utama"
                            value={data.regulasi_id}
                            onChange={(val) => setField('regulasi_id', String(val ?? ''))}
                            options={regulasiSelectOptions}
                            placeholder="Pilih rujukan regulasi (opsional)"
                            emptyOptionLabel="Pilih rujukan regulasi (opsional)"
                            error={errors.regulasi_id}
                            disabled={disabled}
                            searchable
                        />
                    ) : isEdit ? (
                        <p className="text-sm text-muted">Rujukan regulasi yang sudah tersimpan tetap dipertahankan.</p>
                    ) : null}
                </div>
            </section>

            <section aria-labelledby="periode-renstra-heading">
                <div className="mb-3">
                    <h2 id="periode-renstra-heading" className="text-base font-semibold text-ink leading-tight">
                        Periode Pelaksanaan
                    </h2>
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Input
                        name="tahun_mulai"
                        type="number"
                        min="2000"
                        max="2100"
                        label="Tahun Mulai"
                        value={data.tahun_mulai}
                        onChange={(event) => setField('tahun_mulai', event.target.value)}
                        error={errors.tahun_mulai}
                        placeholder="2025"
                        disabled={disabled}
                        required
                    />

                    <Input
                        name="tahun_selesai"
                        type="number"
                        min="2000"
                        max="2100"
                        label="Tahun Selesai"
                        value={data.tahun_selesai}
                        onChange={(event) => setField('tahun_selesai', event.target.value)}
                        error={errors.tahun_selesai}
                        placeholder="2029"
                        disabled={disabled}
                        required
                    />
                </div>
            </section>

            <section aria-labelledby="substansi-renstra-heading">
                <div className="mb-3">
                    <h2 id="substansi-renstra-heading" className="text-base font-semibold text-ink leading-tight">
                        Substansi dan Dasar Hukum
                    </h2>
                </div>

                <div className="space-y-4">
                    <Textarea
                        name="deskripsi"
                        label="Deskripsi / Ringkasan Renstra"
                        value={data.deskripsi}
                        onChange={(event) => setField('deskripsi', event.target.value)}
                        error={errors.deskripsi}
                        rows={3}
                        placeholder="Ringkasan arah strategis, visi misi, atau fokus pencapaian target kinerja..."
                        disabled={disabled}
                    />

                    <Textarea
                        name="dasar_hukum"
                        label="Uraian Dasar Hukum"
                        value={data.dasar_hukum}
                        onChange={(event) => setField('dasar_hukum', event.target.value)}
                        error={errors.dasar_hukum}
                        rows={3}
                        placeholder="Daftar peraturan perundang-undangan atau surat keputusan penetapan Renstra..."
                        disabled={disabled}
                    />
                </div>
            </section>

            {requireReason && (
                <section aria-labelledby="alasan-heading" className="rounded-xl border border-warning/30 bg-warning/5 p-4 sm:p-5">
                    <div className="mb-3">
                        <h2 id="alasan-heading" className="text-base font-semibold text-ink leading-tight">
                            Alasan Perubahan <span className="text-danger">*</span>
                        </h2>
                        <p className="mt-0.5 text-xs leading-snug text-muted">
                            Renstra ini berstatus aktif. Pembaruan data memerlukan catatan alasan audit minimal 5 karakter.
                        </p>
                    </div>
                    <Textarea
                        name="alasan"
                        label="Catatan Alasan Audit"
                        value={data.alasan}
                        onChange={(event) => setField('alasan', event.target.value)}
                        error={errors.alasan}
                        rows={3}
                        placeholder="Tuliskan alasan penyesuaian atau perubahan dokumen Renstra..."
                        disabled={disabled}
                        required
                    />
                </section>
            )}

            <LampiranFormSection
                lampiran={data.lampiran}
                errors={errors}
                disabled={disabled}
                canUpload={canUploadAttachment}
                locked={attachmentLocked}
                lockedMessage="Lampiran baru hanya dapat ditambahkan saat Renstra berstatus draft."
                emptyTitle="Belum ada lampiran naskah yang ditambahkan"
                title="Naskah Renstra dan Bukti Dukung"
                onChange={setLampiran}
            />
        </div>
    );
}

export default RenstraFormFields;
