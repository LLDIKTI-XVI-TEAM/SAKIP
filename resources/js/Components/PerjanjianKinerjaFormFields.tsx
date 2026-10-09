import React, { useMemo } from 'react';
import { Input } from '@/Components/Input';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import type {
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

    const allowedYears = useMemo(() => {
        if (!selectedRenstra) return [];
        const years: number[] = [];
        for (let y = selectedRenstra.tahun_mulai; y <= selectedRenstra.tahun_selesai; y++) {
            years.push(y);
        }
        return years;
    }, [selectedRenstra]);

    const setNamaLampiran = (index: number, nama: string) => {
        setField('lampiran', data.lampiran.map((item, itemIndex) => (itemIndex === index ? { ...item, nama_asli: nama } : item)));
    };

    const maksMb = storageSettings?.ukuran_maks_kb ? Math.round(storageSettings.ukuran_maks_kb / 1024) : 10;

    return (
        <div className="space-y-8">
            <section aria-labelledby="metadata-pk-heading">
                <h2 id="metadata-pk-heading" className="mb-4 text-base font-semibold text-ink">
                    Metadata Perjanjian Kinerja
                </h2>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-12">
                    <div className="sm:col-span-2 lg:col-span-5">
                        <Select
                            id="renstra_id"
                            name="renstra_id"
                            label="Periode Renstra Induk"
                            value={data.renstra_id}
                            onChange={(event) => {
                                setField('renstra_id', event.target.value);
                                const found = renstras.find((r) => r.id === event.target.value);
                                if (found && (!data.tahun || Number(data.tahun) < found.tahun_mulai || Number(data.tahun) > found.tahun_selesai)) {
                                    setField('tahun', found.tahun_mulai);
                                }
                            }}
                            error={errors.renstra_id}
                            disabled={disabled || isEdit}
                            required
                        >
                            <option value="">-- Pilih Rencana Strategis --</option>
                            {renstras.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.nama} ({r.tahun_mulai}–{r.tahun_selesai})
                                </option>
                            ))}
                        </Select>
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
                            placeholder="Jelaskan alasan pembaruan data atau lampiran PK…"
                            rows={3}
                            disabled={disabled}
                            required
                        />
                    </div>
                )}
            </section>

            <LampiranDraftFields
                items={data.lampiran}
                errors={errors}
                onChange={(update) => setField('lampiran', update(data.lampiran))}
                title="Lampiran Dokumen"
                modeDescriptions={{
                    file: storageSettings?.unggahan_aktif === false
                        ? 'Unggahan file sedang dinonaktifkan di pengaturan sistem. Gunakan tautan atau teks.'
                        : `Format dokumen legal resmi (PDF, Word, atau gambar); maksimal ${maksMb} MB.`,
                    tautan: 'Alamat berkas cloud atau portal resmi dengan tautan HTTPS.',
                    teks: 'Catatan ringkasan klausul atau komitmen Perjanjian Kinerja.',
                }}
                placeholders={{ tautan: 'https://drive.google.com/...', teks: 'Tuliskan ringkasan klausul atau komitmen...' }}
                emptyText="Belum ada lampiran yang ditambahkan"
                accept={storageSettings?.format_diizinkan
                    ? storageSettings.format_diizinkan.split(',').map((format) => `.${format.trim()}`).join(',')
                    : '.pdf,.doc,.docx,.jpg,.jpeg,.png'}
                fileDisabled={storageSettings?.unggahan_aktif === false}
                disabled={disabled}
                blockedMessage={canUploadBerkas ? undefined : 'Anda tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.'}
                renderExtraFields={(index) => (
                    <Input
                        label="Nama dokumen (opsional)"
                        value={data.lampiran[index]?.nama_asli ?? ''}
                        onChange={(event) => setNamaLampiran(index, event.target.value)}
                        disabled={disabled}
                    />
                )}
            />
        </div>
    );
}
