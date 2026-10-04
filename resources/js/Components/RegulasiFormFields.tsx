import React from 'react';
import { FileText, Link2, Plus, Trash2, Type } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { CustomSelect, type CustomSelectOption } from '@/Components/CustomSelect';
import { Textarea } from '@/Components/Textarea';
import type { LampiranDraft, LampiranMode, RegulasiFormData, RegulasiJenis } from '@/types/regulasi';

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

const lampiranModeOptions: CustomSelectOption[] = [
    { value: 'file', label: 'File' },
    { value: 'tautan', label: 'Tautan' },
    { value: 'teks', label: 'Teks' },
];

const modeMeta: Record<LampiranMode, { label: string; icon: typeof FileText; description: string }> = {
    file: { label: 'File', icon: FileText, description: 'PDF, dokumen Office, atau gambar; maksimal 10 MB.' },
    tautan: { label: 'Tautan', icon: Link2, description: 'Gunakan alamat resmi dengan protokol HTTPS bila tersedia.' },
    teks: { label: 'Teks', icon: Type, description: 'Catatan sumber atau keterangan dokumen.' },
};

function newLampiran(): LampiranDraft {
    return {
        clientId: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        mode: 'file',
        file: null,
        tautan: '',
        isi_teks: '',
    };
}

export function RegulasiFormFields({ data, errors, disabled = false, isEdit = false, setField }: RegulasiFormFieldsProps) {
    const updateLampiran = <K extends keyof LampiranDraft>(index: number, field: K, value: LampiranDraft[K]) => {
        const next = data.lampiran.map((item, itemIndex) => (
            itemIndex === index ? { ...item, [field]: value } : item
        ));
        setField('lampiran', next);
    };

    const updateModeLampiran = (index: number, mode: LampiranMode) => {
        const next = data.lampiran.map((item, itemIndex) => (
            itemIndex === index
                ? { ...item, mode, file: null, tautan: '', isi_teks: '' }
                : item
        ));
        setField('lampiran', next);
    };

    const removeLampiran = (index: number) => {
        setField('lampiran', data.lampiran.filter((_, itemIndex) => itemIndex !== index));
    };

    return (
        <div className="space-y-8">
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

            <section aria-labelledby="lampiran-heading" className="border-t border-border pt-7">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="lampiran-heading" className="text-base font-semibold text-ink">Lampiran dokumen sumber</h2>
                    </div>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        className="gap-1.5"
                        onClick={() => setField('lampiran', [...data.lampiran, newLampiran()])}
                        disabled={disabled}
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah lampiran
                    </Button>
                </div>

                {data.lampiran.length === 0 ? (
                    <div className="mt-4 rounded-lg border border-dashed border-border bg-page px-5 py-7 text-center">
                        <FileText className="mx-auto h-7 w-7 text-muted" aria-hidden="true" />
                        <p className="mt-2 text-sm font-semibold text-ink">Belum ada lampiran baru</p>
                        <p className="mt-1 text-xs text-muted">Lampiran bersifat opsional dan dapat berupa file, tautan, atau teks.</p>
                    </div>
                ) : (
                    <div className="mt-4 space-y-4">
                        {data.lampiran.map((item, index) => {
                            const ModeIcon = modeMeta[item.mode].icon;

                            return (
                                <div key={item.clientId} className="rounded-xl border border-border bg-page p-4">
                                    <div className="mb-4 flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-2 text-sm font-semibold text-ink">
                                            <ModeIcon className="h-4 w-4 text-primary" aria-hidden="true" />
                                            Lampiran {index + 1}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeLampiran(index)}
                                            disabled={disabled}
                                            className="rounded-lg p-2 text-muted transition-colors hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger/20 disabled:opacity-50"
                                            aria-label={`Hapus lampiran ${index + 1}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)]">
                                        <CustomSelect
                                            id={`lampiran-${index}-mode`}
                                            label="Mode lampiran"
                                            options={lampiranModeOptions}
                                            value={item.mode}
                                            onChange={(val) => updateModeLampiran(index, String(val) as LampiranMode)}
                                            disabled={disabled}
                                            error={errors[`lampiran.${index}.mode`]}
                                            showEmptyOption={false}
                                        />

                                        <div>
                                            {item.mode === 'file' && (
                                                <Input
                                                    type="file"
                                                    label="Pilih file"
                                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                                    onChange={(event) => updateLampiran(index, 'file', event.target.files?.[0] ?? null)}
                                                    error={errors[`lampiran.${index}.file`]}
                                                    helperText={modeMeta.file.description}
                                                    disabled={disabled}
                                                />
                                            )}
                                            {item.mode === 'tautan' && (
                                                <Input
                                                    type="url"
                                                    label="Tautan dokumen"
                                                    value={item.tautan}
                                                    onChange={(event) => updateLampiran(index, 'tautan', event.target.value)}
                                                    error={errors[`lampiran.${index}.tautan`]}
                                                    helperText={modeMeta.tautan.description}
                                                    placeholder="https://..."
                                                    disabled={disabled}
                                                />
                                            )}
                                            {item.mode === 'teks' && (
                                                <Textarea
                                                    label="Keterangan dokumen"
                                                    value={item.isi_teks}
                                                    onChange={(event) => updateLampiran(index, 'isi_teks', event.target.value)}
                                                    error={errors[`lampiran.${index}.isi_teks`]}
                                                    helperText={modeMeta.teks.description}
                                                    rows={3}
                                                    disabled={disabled}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </section>
        </div>
    );
}
