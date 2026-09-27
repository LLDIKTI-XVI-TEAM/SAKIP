import React, { useMemo, useState } from 'react';
import { ChevronDown, FileText, Link2, Plus, Trash2, Type } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { Button } from '@/Components/Button';
import { HoverScrollText } from '@/Components/HoverScrollText';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import type {
    LampiranDraft,
    LampiranMode,
    PerjanjianKinerjaFormData,
    RenstraSummary,
    StorageSettings,
} from '@/types/perjanjian-kinerja';

type PkEditableField = Exclude<keyof PerjanjianKinerjaFormData, '_method'>;
type SetPkField = <K extends PkEditableField>(field: K, value: PerjanjianKinerjaFormData[K]) => void;

interface PerjanjianKinerjaFormFieldsProps {
    data: PerjanjianKinerjaFormData;
    errors: Record<string, string | undefined>;
    renstras: RenstraSummary[];
    storageSettings?: StorageSettings;
    isEdit?: boolean;
    disabled?: boolean;
    setField: SetPkField;
}

const modeMeta: Record<LampiranMode, { label: string; icon: typeof FileText; description: string }> = {
    file: { label: 'File', icon: FileText, description: 'Format dokumen legal resmi (PDF, Word, atau gambar).' },
    tautan: { label: 'Tautan', icon: Link2, description: 'Alamat berkas cloud atau portal resmi dengan tautan HTTPS.' },
    teks: { label: 'Teks', icon: Type, description: 'Catatan ringkasan klausul atau komitmen Perjanjian Kinerja.' },
};

function newLampiran(): LampiranDraft {
    return {
        clientId: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        mode: 'file',
        file: null,
        tautan: '',
        isi_teks: '',
        nama_asli: '',
    };
}

export function PerjanjianKinerjaFormFields({
    data,
    errors,
    renstras,
    storageSettings,
    isEdit = false,
    disabled = false,
    setField,
}: PerjanjianKinerjaFormFieldsProps) {
    const [isRenstraHovered, setIsRenstraHovered] = useState(false);

    const selectedRenstra = useMemo(() => {
        return renstras.find((r) => r.id === data.renstra_id);
    }, [renstras, data.renstra_id]);

    const allowedYears = useMemo(() => {
        if (!selectedRenstra) return [];
        const years: number[] = [];
        for (let y = selectedRenstra.tahun_mulai; y <= selectedRenstra.tahun_selesai; y++) {
            years.push(y);
        }
        return years;
    }, [selectedRenstra]);

    const updateLampiran = <K extends keyof LampiranDraft>(index: number, field: K, value: LampiranDraft[K]) => {
        const next = data.lampiran.map((item, itemIndex) =>
            itemIndex === index ? { ...item, [field]: value } : item
        );
        setField('lampiran', next);
    };

    const updateModeLampiran = (index: number, mode: LampiranMode) => {
        const next = data.lampiran.map((item, itemIndex) =>
            itemIndex === index
                ? { ...item, mode, file: null, tautan: '', isi_teks: '', nama_asli: '' }
                : item
        );
        setField('lampiran', next);
    };

    const removeLampiran = (index: number) => {
        setField('lampiran', data.lampiran.filter((_, itemIndex) => itemIndex !== index));
    };

    return (
        <div className="space-y-8">
            <section aria-labelledby="metadata-pk-heading">
                <div className="mb-4">
                    <h2 id="metadata-pk-heading" className="text-base font-semibold text-ink">
                        Metadata Perjanjian Kinerja
                    </h2>
                    <p className="mt-1 max-w-3xl text-sm leading-6 text-muted">
                        Kombinasi Renstra dan tahun pelaksanaan bersifat unik. Dokumen ini menjadi rujukan komitmen kinerja tahunan LLDIKTI Wilayah XVI.
                    </p>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-12">
                    <div className="sm:col-span-2 lg:col-span-5">
                        <label
                            htmlFor="renstra_id"
                            className="mb-1.5 block text-sm font-medium text-ink whitespace-nowrap"
                        >
                            Periode Renstra Induk
                            <span className="ml-1 text-danger font-normal" aria-hidden="true">*</span>
                        </label>

                        <div
                            className={twMerge(
                                clsx(
                                    'relative w-full h-[42px] rounded-lg border bg-surface px-3.5 py-2 text-sm transition-colors flex items-center justify-between',
                                    errors.renstra_id
                                        ? 'border-danger focus-within:border-danger focus-within:ring-2 focus-within:ring-danger/20'
                                        : 'border-border focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20',
                                    disabled || isEdit
                                        ? 'cursor-not-allowed bg-soft text-muted'
                                        : 'cursor-pointer hover:border-border-dark'
                                )
                            )}
                            onMouseEnter={() => setIsRenstraHovered(true)}
                            onMouseLeave={() => setIsRenstraHovered(false)}
                        >
                            <div className="min-w-0 flex-1 pr-6 overflow-hidden">
                                {selectedRenstra ? (
                                    <HoverScrollText
                                        text={`${selectedRenstra.nama} (${selectedRenstra.tahun_mulai} - ${selectedRenstra.tahun_selesai})`}
                                        isParentHovered={isRenstraHovered}
                                        className="text-sm font-normal text-ink"
                                        textClassName="font-normal"
                                    />
                                ) : (
                                    <span className="text-muted text-sm select-none">
                                        -- Pilih Rencana Strategis --
                                    </span>
                                )}
                            </div>

                            <div className="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-muted">
                                <ChevronDown className="h-4 w-4" aria-hidden="true" />
                            </div>

                            {!(disabled || isEdit) && (
                                <select
                                    id="renstra_id"
                                    name="renstra_id"
                                    aria-label="Periode Renstra Induk"
                                    value={data.renstra_id}
                                    onChange={(event) => {
                                        setField('renstra_id', event.target.value);
                                        const found = renstras.find((r) => r.id === event.target.value);
                                        if (
                                            found &&
                                            (!data.tahun ||
                                                Number(data.tahun) < found.tahun_mulai ||
                                                Number(data.tahun) > found.tahun_selesai)
                                        ) {
                                            setField('tahun', found.tahun_mulai);
                                        }
                                    }}
                                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20"
                                    required
                                >
                                    <option value="">-- Pilih Rencana Strategis --</option>
                                    {renstras.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.nama} ({r.tahun_mulai} - {r.tahun_selesai})
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {errors.renstra_id && (
                            <p className="mt-1.5 text-xs font-medium text-danger">{errors.renstra_id}</p>
                        )}
                    </div>

                    <div className="sm:col-span-1 lg:col-span-3">
                        {allowedYears.length > 0 ? (
                            <Select
                                name="tahun"
                                label="Tahun Pelaksanaan"
                                labelClassName="whitespace-nowrap"
                                value={data.tahun}
                                onChange={(event) => setField('tahun', Number(event.target.value))}
                                error={errors.tahun}
                                disabled={disabled || isEdit}
                                required
                            >
                                <option value="">-- Pilih Tahun --</option>
                                {allowedYears.map((yr) => (
                                    <option key={yr} value={yr}>
                                        Tahun {yr}
                                    </option>
                                ))}
                            </Select>
                        ) : (
                            <Input
                                name="tahun"
                                type="number"
                                label="Tahun Pelaksanaan"
                                labelClassName="whitespace-nowrap"
                                value={data.tahun}
                                onChange={(event) => setField('tahun', event.target.value)}
                                error={errors.tahun}
                                placeholder="Contoh: 2026"
                                disabled={disabled || isEdit}
                                required
                            />
                        )}
                    </div>

                    <div className="sm:col-span-1 lg:col-span-4">
                        <Input
                            name="tanggal_pk"
                            type="date"
                            label="Tanggal Penandatanganan"
                            labelClassName="whitespace-nowrap"
                            value={data.tanggal_pk}
                            onChange={(event) => setField('tanggal_pk', event.target.value)}
                            error={errors.tanggal_pk}
                            disabled={disabled}
                            required
                        />
                    </div>

                    <div className="sm:col-span-2 lg:col-span-12">
                        <Input
                            name="nomor_pk"
                            label="Nomor Dokumen Perjanjian Kinerja"
                            value={data.nomor_pk}
                            onChange={(event) => setField('nomor_pk', event.target.value)}
                            error={errors.nomor_pk}
                            placeholder="Contoh: PK/LLDIKTI16/2026/001"
                            disabled={disabled}
                            required
                        />
                    </div>
                </div>

                {isEdit && (
                    <div className="mt-5 border-t border-border pt-5">
                        <Textarea
                            name="alasan"
                            label="Alasan Perubahan Data Perjanjian Kinerja"
                            value={data.alasan ?? ''}
                            onChange={(event) => setField('alasan', event.target.value)}
                            error={errors.alasan}
                            placeholder="Jelaskan alasan pembaruan metadata atau lampiran dokumen PK (wajib diisi)..."
                            helperText="Pencatatan alasan mutasi dipersyaratkan demi akuntabilitas jejak audit SAKIP."
                            rows={3}
                            disabled={disabled}
                            required
                        />
                    </div>
                )}
            </section>

            <section aria-labelledby="lampiran-pk-heading" className="border-t border-border pt-7">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="lampiran-pk-heading" className="text-base font-semibold text-ink">
                            Lampiran Dokumen Legal (3 Mode)
                        </h2>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted">
                            Lampirkan naskah bertanda tangan digital/basah melalui berkas privat, tautan repositori awan, atau salinan teks komitmen.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        onClick={() => setField('lampiran', [...data.lampiran, newLampiran()])}
                        disabled={disabled}
                        className="whitespace-nowrap shrink-0 shadow-2xs"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        <span className="whitespace-nowrap">Tambah Lampiran</span>
                    </Button>
                </div>

                {data.lampiran.length === 0 ? (
                    <div className="mt-4 rounded-lg border border-dashed border-border bg-page px-5 py-7 text-center">
                        <FileText className="mx-auto h-7 w-7 text-muted" aria-hidden="true" />
                        <p className="mt-2 text-sm font-semibold text-ink">Belum ada lampiran baru</p>
                        <p className="mt-1 text-xs text-muted">
                            Lampiran bersifat opsional dan dapat ditambahkan kapan saja sebelum Jadwal Tahunan aktif.
                        </p>
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
                                            Lampiran Dokumen #{index + 1}
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
                                        <Select
                                            label="Mode Lampiran"
                                            value={item.mode}
                                            onChange={(event) => updateModeLampiran(index, event.target.value as LampiranMode)}
                                            disabled={disabled}
                                            error={errors[`lampiran.${index}.mode`]}
                                        >
                                            <option value="file">File (Unggahan)</option>
                                            <option value="tautan">Tautan (URL Cloud)</option>
                                            <option value="teks">Teks (Catatan/Ringkasan)</option>
                                        </Select>

                                        <div>
                                            {item.mode === 'file' && (
                                                <div className="space-y-3">
                                                    <Input
                                                        type="file"
                                                        label="Pilih Berkas Naskah PK"
                                                        accept={
                                                            storageSettings?.format_diizinkan
                                                                ? storageSettings.format_diizinkan.split(',').map((f) => `.${f.trim()}`).join(',')
                                                                : '.pdf,.doc,.docx,.jpg,.jpeg,.png'
                                                        }
                                                        onChange={(event) => updateLampiran(index, 'file', event.target.files?.[0] ?? null)}
                                                        error={errors[`lampiran.${index}.file`]}
                                                        helperText={
                                                            storageSettings?.unggahan_aktif === false
                                                                ? 'Unggahan file sedang dinonaktifkan di pengaturan sistem. Gunakan tautan atau teks.'
                                                                : `Maksimal ${storageSettings?.ukuran_maks_kb ? Math.round(storageSettings.ukuran_maks_kb / 1024) : 10} MB. Berkas disimpan aman di private storage.`
                                                        }
                                                        disabled={disabled || storageSettings?.unggahan_aktif === false}
                                                    />
                                                </div>
                                            )}

                                            {item.mode === 'tautan' && (
                                                <div className="space-y-3">
                                                    <Input
                                                        label="Nama / Judul Dokumen (Opsional)"
                                                        value={item.nama_asli ?? ''}
                                                        onChange={(event) => updateLampiran(index, 'nama_asli', event.target.value)}
                                                        placeholder="Contoh: Dokumen PK 2026 di Google Drive"
                                                        disabled={disabled}
                                                    />
                                                    <Input
                                                        type="url"
                                                        label="URL / Alamat Tautan"
                                                        value={item.tautan}
                                                        onChange={(event) => updateLampiran(index, 'tautan', event.target.value)}
                                                        error={errors[`lampiran.${index}.tautan`]}
                                                        helperText={modeMeta.tautan.description}
                                                        placeholder="https://drive.google.com/..."
                                                        disabled={disabled}
                                                    />
                                                </div>
                                            )}

                                            {item.mode === 'teks' && (
                                                <div className="space-y-3">
                                                    <Input
                                                        label="Nama / Judul Catatan (Opsional)"
                                                        value={item.nama_asli ?? ''}
                                                        onChange={(event) => updateLampiran(index, 'nama_asli', event.target.value)}
                                                        placeholder="Contoh: Catatan Ringkasan Komitmen"
                                                        disabled={disabled}
                                                    />
                                                    <Textarea
                                                        label="Isi Catatan Ringkasan"
                                                        value={item.isi_teks}
                                                        onChange={(event) => updateLampiran(index, 'isi_teks', event.target.value)}
                                                        error={errors[`lampiran.${index}.isi_teks`]}
                                                        helperText={modeMeta.teks.description}
                                                        rows={3}
                                                        disabled={disabled}
                                                    />
                                                </div>
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
