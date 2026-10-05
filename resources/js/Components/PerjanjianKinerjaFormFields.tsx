import React, { useMemo } from 'react';
import { CustomSelect, type CustomSelectOption } from '@/Components/CustomSelect';
import { InfoTooltip } from '@/Components/InfoTooltip';
import { Input } from '@/Components/Input';
import { LampiranDraft, LampiranFormSection } from '@/Components/LampiranFormSection';
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

    const tahunOptions: CustomSelectOption[] = useMemo(() => {
        if (!selectedRenstra) return [];
        const options: CustomSelectOption[] = [];
        for (let y = selectedRenstra.tahun_mulai; y <= selectedRenstra.tahun_selesai; y++) {
            options.push({ value: y, label: `Tahun ${y}` });
        }
        return options;
    }, [selectedRenstra]);

    const fileAccept = useMemo(() => {
        if (storageSettings?.format_diizinkan) {
            return storageSettings.format_diizinkan
                .split(',')
                .map((f) => `.${f.trim()}`)
                .join(',');
        }
        return '.pdf,.doc,.docx,.jpg,.jpeg,.png';
    }, [storageSettings?.format_diizinkan]);

    const fileHelperText = useMemo(() => {
        if (storageSettings?.unggahan_aktif === false) {
            return 'Unggahan berkas dinonaktifkan di pengaturan sistem.';
        }
        const maxMb = storageSettings?.ukuran_maks_kb ? Math.round(storageSettings.ukuran_maks_kb / 1024) : 10;
        const formats = storageSettings?.format_diizinkan ? storageSettings.format_diizinkan.toUpperCase() : 'PDF, DOC, DOCX, JPG, PNG';
        return `Maks. ${maxMb} MB. Format: ${formats}.`;
    }, [storageSettings]);

    return (
        <div className="space-y-8 w-full max-w-full min-w-0">
            <section aria-labelledby="metadata-pk-heading" className="w-full max-w-full min-w-0">
                <div className="mb-4 flex items-center gap-2">
                    <h2 id="metadata-pk-heading" className="text-base font-semibold text-ink">
                        Metadata Perjanjian Kinerja
                    </h2>
                    <InfoTooltip
                        content="Pilih dokumen induk Renstra dan tahun pelaksanaan sebelum mengisi nomor dan tanggal naskah resmi."
                        label="Bantuan metadata Perjanjian Kinerja"
                    />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div className="min-w-0">
                        <CustomSelect
                            id="renstra_id"
                            name="renstra_id"
                            label="Induk Renstra"
                            options={renstraOptions}
                            value={data.renstra_id}
                            onChange={(val) => {
                                setField('renstra_id', String(val ?? ''));
                                setField('tahun', '');
                            }}
                            placeholder="Pilih Dokumen Renstra"
                            disabled={disabled || isEdit}
                            error={errors.renstra_id}
                            required
                            searchable
                        />
                    </div>

                    <div className="min-w-0">
                        <CustomSelect
                            id="tahun"
                            name="tahun"
                            label="Tahun PK"
                            options={tahunOptions}
                            value={data.tahun ? Number(data.tahun) : ''}
                            onChange={(val) => setField('tahun', String(val ?? ''))}
                            placeholder={data.renstra_id ? 'Pilih Tahun' : 'Pilih Renstra Terlebih Dahulu'}
                            disabled={disabled || !data.renstra_id || isEdit}
                            error={errors.tahun}
                            required
                        />
                    </div>

                    <div className="min-w-0">
                        <Input
                            name="nomor"
                            label="Nomor Dokumen PK"
                            value={data.nomor}
                            onChange={(e) => setField('nomor', e.target.value)}
                            placeholder="Contoh: 012/LL16/PK/2026"
                            disabled={disabled}
                            error={errors.nomor}
                            required
                        />
                    </div>

                    <div className="min-w-0">
                        <Input
                            type="date"
                            name="tanggal"
                            label="Tanggal Penandatanganan"
                            value={data.tanggal}
                            onChange={(e) => setField('tanggal', e.target.value)}
                            disabled={disabled}
                            error={errors.tanggal}
                            required
                        />
                    </div>

                    <div className="sm:col-span-2 min-w-0">
                        <Textarea
                            name="catatan"
                            label="Catatan / Keterangan Tambahan (Opsional)"
                            value={data.catatan ?? ''}
                            onChange={(e) => setField('catatan', e.target.value)}
                            placeholder="Keterangan mengenai penetapan atau perubahan naskah PK..."
                            rows={3}
                            disabled={disabled}
                            error={errors.catatan}
                        />
                    </div>
                </div>
            </section>

            {isEdit && (
                <section aria-labelledby="alasan-koreksi-heading" className="w-full max-w-full min-w-0 border-t border-border pt-6">
                    <div className="mb-3">
                        <h2 id="alasan-koreksi-heading" className="text-base font-semibold text-ink leading-tight">
                            Alasan Koreksi Perjanjian Kinerja <span className="text-danger">*</span>
                        </h2>
                        <p className="mt-0.5 text-xs text-muted">
                            Setiap penyesuaian metadata atau lampiran PK teraudit dan membutuhkan catatan alasan minimal 5 karakter.
                        </p>
                    </div>
                    <Textarea
                        name="alasan"
                        label="Catatan Alasan Koreksi"
                        value={data.alasan ?? ''}
                        onChange={(e) => setField('alasan', e.target.value)}
                        placeholder="Contoh: Perbaikan nomor registrasi dokumen berdasarkan naskah fisik..."
                        rows={3}
                        disabled={disabled}
                        error={errors.alasan}
                        required
                    />
                </section>
            )}

            <LampiranFormSection
                lampiran={data.lampiran as LampiranDraft[]}
                errors={errors}
                disabled={disabled}
                canUpload={canUploadBerkas && storageSettings?.unggahan_aktif !== false}
                title="Lampiran Dokumen Sumber & Perjanjian Kinerja"
                emptyTitle="Belum ada lampiran baru"
                emptyMessage="Lampiran bersifat opsional dan dapat ditambahkan kapan saja sebelum Jadwal Tahunan aktif."
                fileAccept={fileAccept}
                fileHelperText={fileHelperText}
                showNamaAsli={true}
                onChange={(updater) => setField('lampiran', updater(data.lampiran as LampiranDraft[]))}
            />
        </div>
    );
}

export default PerjanjianKinerjaFormFields;
