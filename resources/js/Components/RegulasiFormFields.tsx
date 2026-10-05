import React from 'react';
import { CustomSelect, type CustomSelectOption } from '@/Components/CustomSelect';
import { Input } from '@/Components/Input';
import { LampiranDraft, LampiranFormSection } from '@/Components/LampiranFormSection';
import { Textarea } from '@/Components/Textarea';
import type { RegulasiFormData, RegulasiJenis } from '@/types/regulasi';

type RegulasiEditableField = Exclude<keyof RegulasiFormData, '_method'>;
type SetRegulasiField = <K extends RegulasiEditableField>(field: K, value: RegulasiFormData[K]) => void;

interface RegulasiFormFieldsProps {
    data: RegulasiFormData;
    errors: Record<string, string | undefined>;
    disabled?: boolean;
    isEdit?: boolean;
    setField: SetRegulasiField;
}

const jenisOptions: CustomSelectOption[] = [
    { value: 'kepmen', label: 'Keputusan Menteri' },
    { value: 'permen', label: 'Peraturan Menteri' },
    { value: 'perpres', label: 'Peraturan Presiden' },
    { value: 'keputusan_lainnya', label: 'Keputusan lainnya' },
];

export function RegulasiFormFields({ data, errors, disabled = false, isEdit = false, setField }: RegulasiFormFieldsProps) {
    return (
        <div className="space-y-6">
            <section aria-labelledby="metadata-heading">
                <div className="mb-4">
                    <h2 id="metadata-heading" className="text-base font-semibold text-ink">Metadata dasar aturan</h2>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <CustomSelect
                        id="jenis"
                        name="jenis"
                        label="Jenis regulasi"
                        options={jenisOptions}
                        value={data.jenis}
                        onChange={(val) => setField('jenis', String(val) as RegulasiJenis)}
                        error={errors.jenis}
                        disabled={disabled}
                        required
                        showEmptyOption={false}
                    />
                    <Input
                        name="nomor"
                        label="Nomor"
                        value={data.nomor}
                        onChange={(event) => setField('nomor', event.target.value)}
                        error={errors.nomor}
                        placeholder="Contoh: 358/M/KEP/2025"
                        disabled={disabled}
                        required
                    />
                    <Input
                        name="tahun"
                        type="number"
                        min="1800"
                        max={new Date().getFullYear() + 1}
                        label="Tahun"
                        value={data.tahun}
                        onChange={(event) => setField('tahun', event.target.value)}
                        error={errors.tahun}
                        disabled={disabled}
                        required
                    />
                    <Input
                        name="tanggal"
                        type="date"
                        label="Tanggal penetapan"
                        value={data.tanggal}
                        onChange={(event) => setField('tanggal', event.target.value)}
                        error={errors.tanggal}
                        disabled={disabled}
                    />
                    <div className="sm:col-span-2">
                        <Input
                            name="tautan_sumber"
                            type="url"
                            label="Tautan sumber resmi"
                            value={data.tautan_sumber}
                            onChange={(event) => setField('tautan_sumber', event.target.value)}
                            error={errors.tautan_sumber}
                            placeholder="https://jdih.example.go.id/..."
                            disabled={disabled}
                        />
                    </div>
                    <div className="sm:col-span-2 lg:col-span-3">
                        <Textarea
                            name="tentang"
                            label="Tentang"
                            value={data.tentang}
                            onChange={(event) => setField('tentang', event.target.value)}
                            error={errors.tentang}
                            placeholder="Tuliskan pokok pengaturan sesuai judul dokumen resmi."
                            rows={3}
                            disabled={disabled}
                            required
                        />
                    </div>
                    <div className="sm:col-span-2 lg:col-span-3">
                        <Textarea
                            name="catatan"
                            label="Catatan internal"
                            value={data.catatan}
                            onChange={(event) => setField('catatan', event.target.value)}
                            error={errors.catatan}
                            helperText="Opsional. Jangan menaruh kredensial atau data rahasia pada catatan."
                            rows={3}
                            disabled={disabled}
                        />
                    </div>
                </div>

                <label className="mt-5 flex items-center gap-3 rounded-lg bg-soft px-4 py-3 text-sm text-ink cursor-pointer">
                    <input
                        type="checkbox"
                        checked={data.aktif}
                        onChange={(event) => setField('aktif', event.target.checked)}
                        disabled={disabled}
                        className="h-4 w-4 rounded border-border text-primary focus:ring-primary/25"
                    />
                    <span className="text-sm font-medium text-ink">
                        Regulasi aktif dapat dipilih sebagai dasar hukum Renstra atau Indikator.
                    </span>
                </label>

                {isEdit && (
                    <div className="mt-5 border-t border-border pt-5">
                        <Textarea
                            name="alasan"
                            label="Alasan Perubahan Dasar Aturan"
                            value={data.alasan ?? ''}
                            onChange={(event) => setField('alasan', event.target.value)}
                            error={errors.alasan}
                            placeholder="Jelaskan alasan perubahan data dasar aturan (minimal 10 karakter)..."
                            rows={3}
                            disabled={disabled}
                            required
                        />
                    </div>
                )}
            </section>

            <LampiranFormSection
                lampiran={data.lampiran as LampiranDraft[]}
                errors={errors}
                disabled={disabled}
                title="Lampiran dokumen sumber"
                emptyTitle="Belum ada lampiran baru"
                emptyMessage="Lampiran bersifat opsional dan dapat berupa file, tautan, atau teks."
                onChange={(updater) => setField('lampiran', updater(data.lampiran as LampiranDraft[]))}
            />
        </div>
    );
}

export default RegulasiFormFields;
