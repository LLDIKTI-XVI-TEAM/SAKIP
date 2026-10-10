import React from 'react';
import { Input } from '@/Components/Input';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Select } from '@/Components/Select';
import { Switch } from '@/Components/Switch';
import { Textarea } from '@/Components/Textarea';
import type { RegulasiFormData, RegulasiJenis } from '@/types/regulasi';

type RegulasiEditableField = Exclude<keyof RegulasiFormData, '_method'>;
type SetRegulasiField = <K extends RegulasiEditableField>(field: K, value: RegulasiFormData[K]) => void;

interface RegulasiFormFieldsProps {
    data: RegulasiFormData;
    errors: Record<string, string | undefined>;
    disabled?: boolean;
    setField: SetRegulasiField;
}

const modeDescriptions = {
    file: 'PDF, dokumen Office, atau gambar; maksimal 10 MB.',
    tautan: 'Gunakan alamat resmi dengan protokol HTTPS bila tersedia.',
    teks: 'Catatan sumber atau keterangan dokumen.',
};

export function RegulasiFormFields({ data, errors, disabled = false, setField }: RegulasiFormFieldsProps) {
    return (
        <div className="space-y-8">
            <section aria-labelledby="metadata-heading">
                <h2 id="metadata-heading" className="mb-4 text-base font-semibold text-ink">Metadata dasar aturan</h2>

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Select
                        name="jenis"
                        label="Jenis regulasi"
                        value={data.jenis}
                        onChange={(event) => setField('jenis', event.target.value as RegulasiJenis)}
                        error={errors.jenis}
                        disabled={disabled}
                        required
                    >
                        <option value="kepmen">Keputusan Menteri</option>
                        <option value="permen">Peraturan Menteri</option>
                        <option value="perpres">Peraturan Presiden</option>
                        <option value="keputusan_lainnya">Keputusan lainnya</option>
                    </Select>
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

                <div className="mt-5 flex items-center gap-3">
                    <Switch
                        id="regulasi-aktif"
                        checked={data.aktif}
                        onChange={(checked) => setField('aktif', checked)}
                        disabled={disabled}
                    />
                    <label htmlFor="regulasi-aktif" className="cursor-pointer text-sm font-medium text-ink">
                        Regulasi aktif
                    </label>
                </div>
            </section>

            <LampiranDraftFields
                items={data.lampiran}
                errors={errors}
                onChange={(update) => setField('lampiran', update(data.lampiran))}
                title="Lampiran Dokumen Sumber"
                modeDescriptions={modeDescriptions}
                placeholders={{ tautan: 'https://jdih.example.go.id/...', teks: 'Tuliskan catatan sumber atau keterangan dokumen...' }}
                emptyText="Belum ada lampiran yang ditambahkan"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                disabled={disabled}
            />
        </div>
    );
}
