import React, { useMemo } from 'react';
import { AlertCircle, FileText, Link2, Plus, Trash2, Type } from 'lucide-react';
import { Button } from '@/Components/Button';
import { CustomSelect } from '@/Components/CustomSelect';
import { InfoTooltip } from '@/Components/InfoTooltip';
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
    canUploadBerkas?: boolean;
    setField: SetPkField;
}

const modeIcons: Record<LampiranMode, typeof FileText> = {
    file: FileText,
    tautan: Link2,
    teks: Type,
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
    canUploadBerkas = true,
    setField,
}: PerjanjianKinerjaFormFieldsProps) {

    const selectedRenstra = useMemo(() => {
        return renstras.find((r) => r.id === data.renstra_id);
    }, [renstras, data.renstra_id]);

    const formatRenstraLabel = (r: RenstraSummary) => {
        const yearRange = `${r.tahun_mulai} - ${r.tahun_selesai}`;
        const compactYearRange = `${r.tahun_mulai}-${r.tahun_selesai}`;
        if (r.nama.includes(yearRange) || r.nama.includes(compactYearRange)) {
            return r.nama;
        }
        return `${r.nama} (${yearRange})`;
    };

    const renstraOptions = useMemo(() => {
        return renstras.map((r) => ({
            value: r.id,
            label: formatRenstraLabel(r),
        }));
    }, [renstras]);

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
        <div className="space-y-8 w-full max-w-full min-w-0">
            <section aria-labelledby="metadata-pk-heading" className="w-full max-w-full min-w-0">
                <div className="mb-4 flex items-center gap-2">
                    <h2 id="metadata-pk-heading" className="text-base font-semibold text-ink">
                        Metadata Perjanjian Kinerja
                    </h2>
                    <InfoTooltip
                        content="Kombinasi Renstra dan tahun pelaksanaan bersifat unik (satu PK per tahun per Renstra)."
                        label="Informasi metadata PK"
                    />
                </div>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-12 w-full max-w-full min-w-0">
                    <div className="w-full min-w-0 sm:col-span-2 lg:col-span-5">
                        <CustomSelect
                            id="renstra_id"
                            name="renstra_id"
                            label="Periode Renstra Induk"
                            labelClassName="sm:whitespace-nowrap"
                            placeholder="-- Pilih Rencana Strategis --"
                            options={renstraOptions}
                            value={data.renstra_id}
                            onChange={(val) => {
                                const nextRenstraId = String(val ?? '');
                                setField('renstra_id', nextRenstraId);
                                const foundRenstra = renstras.find((r) => String(r.id) === nextRenstraId);
                                if (foundRenstra) {
                                    if (
                                        !data.tahun ||
                                        Number(data.tahun) < foundRenstra.tahun_mulai ||
                                        Number(data.tahun) > foundRenstra.tahun_selesai
                                    ) {
                                        setField('tahun', foundRenstra.tahun_mulai);
                                    }
                                }
                            }}
                            error={errors.renstra_id}
                            disabled={disabled || isEdit}
                            required
                            useHoverScroll={true}
                        />
                    </div>

                    <div className="w-full min-w-0 sm:col-span-1 lg:col-span-3">
                        {allowedYears.length > 0 ? (
                            <Select
                                name="tahun"
                                label="Tahun Pelaksanaan"
                                labelClassName="sm:whitespace-nowrap"
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
                                labelClassName="sm:whitespace-nowrap"
                                value={data.tahun}
                                onChange={(event) => setField('tahun', event.target.value)}
                                error={errors.tahun}
                                placeholder="Contoh: 2026"
                                disabled={disabled || isEdit}
                                required
                            />
                        )}
                    </div>

                    <div className="w-full min-w-0 sm:col-span-1 lg:col-span-4">
                        <Input
                            name="tanggal_pk"
                            type="date"
                            label="Tanggal Penandatanganan"
                            labelClassName="sm:whitespace-nowrap"
                            value={data.tanggal_pk}
                            onChange={(event) => setField('tanggal_pk', event.target.value)}
                            error={errors.tanggal_pk}
                            disabled={disabled}
                            required
                        />
                    </div>

                    <div className="w-full min-w-0 sm:col-span-2 lg:col-span-12">
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
                            rows={3}
                            disabled={disabled}
                            required
                        />
                    </div>
                )}
            </section>

            <section aria-labelledby="lampiran-pk-heading" className="border-t border-border pt-7">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <h2 id="lampiran-pk-heading" className="text-base font-semibold text-ink">
                            Lampiran Dokumen Legal
                        </h2>
                        <InfoTooltip
                            content="Mendukung unggahan berkas privat, tautan repositori cloud, atau salinan teks komitmen."
                            label="Informasi mode lampiran"
                        />
                    </div>
                    {canUploadBerkas ? (
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
                    ) : (
                        <div className="text-xs text-muted italic">
                            Penambahan lampiran dinonaktifkan (tanpa izin berkas:upload)
                        </div>
                    )}
                </div>

                {! canUploadBerkas && (
                    <div className="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3.5 text-sm text-warning flex items-center gap-2">
                        <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
                        <span>Anda tidak memiliki izin (berkas:upload) untuk menambahkan atau mengunggah lampiran dokumen.</span>
                    </div>
                )}

                {errors.lampiran && (
                    <div className="mt-4 rounded-lg border border-danger/30 bg-danger/10 p-3.5 text-sm text-danger flex items-center gap-2">
                        <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
                        <span>{errors.lampiran}</span>
                    </div>
                )}

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
                            const ModeIcon = modeIcons[item.mode];

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
                                                                ? 'Unggahan berkas dinonaktifkan di pengaturan sistem.'
                                                                : `Maks. ${storageSettings?.ukuran_maks_kb ? Math.round(storageSettings.ukuran_maks_kb / 1024) : 10} MB. Format: ${
                                                                      storageSettings?.format_diizinkan
                                                                          ? storageSettings.format_diizinkan.toUpperCase()
                                                                          : 'PDF, DOC, DOCX, JPG, PNG'
                                                                  }.`
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
