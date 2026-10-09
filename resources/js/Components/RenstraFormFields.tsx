import React from 'react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import type { LampiranDraft, RegulasiOption, RenstraFormData } from '@/types/renstra';

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

const modeDescriptions = {
    file: 'Dokumen PDF atau Word naskah Renstra sesuai batas kebijakan berkas.',
    tautan: 'Alamat tautan repositori dokumen resmi atau cloud storage.',
    teks: 'Kutipan substansi atau rangkuman naskah Renstra.',
};

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
    return (
        <div className="space-y-8">
            <section aria-label="Identitas Rencana Strategis">
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

                    {canReadRegulasi ? <Select
                        name="regulasi_id"
                        label="Rujukan Regulasi Utama"
                        value={data.regulasi_id}
                        onChange={(event) => setField('regulasi_id', event.target.value)}
                        error={errors.regulasi_id}
                        disabled={disabled}
                    >
                        <option value="">Pilih rujukan regulasi (opsional)</option>
                        {regulasiOptions.map((reg) => (
                            <option key={reg.id} value={String(reg.id)}>
                                {reg.nomor} ({reg.tahun}) - {reg.tentang.length > 50 ? `${reg.tentang.slice(0, 50)}...` : reg.tentang}
                            </option>
                        ))}
                    </Select> : isEdit ? (
                        <p className="text-sm text-muted">Rujukan regulasi yang sudah tersimpan tetap dipertahankan.</p>
                    ) : null}
                </div>
            </section>

            <section aria-label="Periode Pelaksanaan">
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

            <section aria-label="Substansi dan Dasar Hukum">
                <div className="space-y-5">
                    <Textarea
                        name="deskripsi"
                        label="Deskripsi"
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
                        <p className="mt-0.5 text-sm leading-snug text-muted">
                            Renstra ini berstatus aktif. Perubahan data master memerlukan catatan alasan audit minimal 5 karakter.
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

            <LampiranDraftFields
                items={data.lampiran}
                errors={errors}
                onChange={setLampiran}
                title="Naskah Renstra dan Bukti Dukung"
                modeDescriptions={modeDescriptions}
                placeholders={{
                    tautan: 'https://contoh.lldikti16.kemdikbud.go.id/dokumen/renstra.pdf',
                    teks: 'Tuliskan naskah atau catatan ringkas lampiran Renstra...',
                }}
                emptyText="Belum ada lampiran naskah yang ditambahkan"
                disabled={disabled}
                blockedMessage={canUploadAttachment ? undefined : attachmentLocked
                    ? 'Lampiran baru hanya dapat ditambahkan saat Renstra berstatus draft.'
                    : 'Anda tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.'}
            />
        </div>
    );
}
