import React from 'react';
import { FileText, Link2, Plus, Trash2, Type } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import type { LampiranDraft, LampiranMode, RegulasiOption, RenstraFormData } from '@/types/renstra';

type RenstraEditableField = Exclude<keyof RenstraFormData, '_method'>;
type SetRenstraField = <K extends RenstraEditableField>(field: K, value: RenstraFormData[K]) => void;

interface RenstraFormFieldsProps {
    data: RenstraFormData;
    errors: Record<string, string | undefined>;
    regulasiOptions: RegulasiOption[];
    disabled?: boolean;
    isEdit?: boolean;
    requireReason?: boolean;
    setField: SetRenstraField;
}

const modeMeta: Record<LampiranMode, { label: string; icon: typeof FileText; description: string }> = {
    file: { label: 'File', icon: FileText, description: 'Dokumen PDF atau Word naskah Renstra, maksimal 20 MB.' },
    tautan: { label: 'Tautan', icon: Link2, description: 'Alamat tautan repositori dokumen resmi atau cloud storage.' },
    teks: { label: 'Teks', icon: Type, description: 'Kutipan substansi atau rangkuman naskah Renstra.' },
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

export function RenstraFormFields({
    data,
    errors,
    regulasiOptions,
    disabled = false,
    isEdit = false,
    requireReason = false,
    setField,
}: RenstraFormFieldsProps) {
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

    const addLampiran = () => {
        setField('lampiran', [...data.lampiran, newLampiran()]);
    };

    return (
        <div className="space-y-8">
            <section aria-labelledby="identitas-renstra-heading">
                <div className="mb-4">
                    <h2 id="identitas-renstra-heading" className="text-base font-semibold text-ink leading-tight">Identitas Rencana Strategis</h2>
                    <p className="mt-0.5 max-w-3xl text-sm leading-snug text-muted">
                        Isikan nama resmi, kode unik dokumen, dan rentang tahun pelaksanaan Renstra.
                    </p>
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

                    <Select
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
                    </Select>
                </div>
            </section>

            <section aria-labelledby="periode-renstra-heading">
                <div className="mb-4">
                    <h2 id="periode-renstra-heading" className="text-base font-semibold text-ink leading-tight">Periode Pelaksanaan</h2>
                    <p className="mt-0.5 max-w-3xl text-sm leading-snug text-muted">
                        Tahun mulai dan tahun selesai harus valid, dengan tahun selesai sama atau lebih besar dari tahun mulai.
                    </p>
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
                <div className="mb-4">
                    <h2 id="substansi-renstra-heading" className="text-base font-semibold text-ink leading-tight">Substansi dan Dasar Hukum</h2>
                    <p className="mt-0.5 max-w-3xl text-sm leading-snug text-muted">
                        Uraian ringkas visi, misi, atau ringkasan arah kebijakan dan dasar hukum penetapan.
                    </p>
                </div>

                <div className="space-y-5">
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

            <section aria-labelledby="lampiran-heading">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <div>
                        <h2 id="lampiran-heading" className="text-base font-semibold text-ink leading-tight">Naskah Renstra dan Bukti Dukung</h2>
                        <p className="mt-0.5 max-w-3xl text-sm leading-snug text-muted">
                            Unggah dokumen naskah digital Renstra melalui berkas, tautan repositori, atau kutipan teks.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addLampiran}
                        disabled={disabled}
                        className="shrink-0 whitespace-nowrap self-start sm:self-center"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah Lampiran
                    </Button>
                </div>

                {data.lampiran.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-soft/40 py-6 px-4 text-center">
                        <p className="text-sm font-medium text-muted">Belum ada lampiran naskah yang ditambahkan</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {data.lampiran.map((item, index) => {
                            const meta = modeMeta[item.mode];
                            const errorPrefix = `lampiran.${index}`;
                            const fileError = errors[`${errorPrefix}.file`] ?? errors[`lampiran.${index}`];
                            const tautanError = errors[`${errorPrefix}.tautan`];
                            const teksError = errors[`${errorPrefix}.isi_teks`];

                            return (
                                <div key={item.clientId} className="rounded-xl border border-border bg-surface p-4 sm:p-5 shadow-sm">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-3">
                                        <div className="flex items-center gap-2">
                                            <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                                                {index + 1}
                                            </span>
                                            <div className="flex gap-1.5">
                                                {(['file', 'tautan', 'teks'] as LampiranMode[]).map((mode) => (
                                                    <button
                                                        key={mode}
                                                        type="button"
                                                        onClick={() => updateModeLampiran(index, mode)}
                                                        disabled={disabled}
                                                        className={`rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                                                            item.mode === mode
                                                                ? 'bg-primary text-white shadow-xs'
                                                                : 'bg-soft text-muted hover:bg-border hover:text-ink'
                                                        }`}
                                                    >
                                                        {modeMeta[mode].label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => removeLampiran(index)}
                                            disabled={disabled}
                                            className="inline-flex items-center gap-1.5 self-end text-xs font-medium text-danger hover:underline disabled:opacity-50"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                                            Hapus Lampiran
                                        </button>
                                    </div>

                                    <div className="mt-4">
                                        <p className="text-xs text-muted mb-3">{meta.description}</p>

                                        {item.mode === 'file' && (
                                            <div>
                                                <input
                                                    type="file"
                                                    onChange={(event) => {
                                                        const file = event.target.files?.[0] ?? null;
                                                        updateLampiran(index, 'file', file);
                                                    }}
                                                    disabled={disabled}
                                                    className="block w-full text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 focus:outline-none"
                                                />
                                                {fileError && <p className="mt-1 text-xs text-danger">{fileError}</p>}
                                            </div>
                                        )}

                                        {item.mode === 'tautan' && (
                                            <div>
                                                <Input
                                                    name={`lampiran_tautan_${index}`}
                                                    value={item.tautan}
                                                    onChange={(event) => updateLampiran(index, 'tautan', event.target.value)}
                                                    error={tautanError}
                                                    placeholder="https://contoh.lldikti16.kemdikbud.go.id/dokumen/renstra.pdf"
                                                    disabled={disabled}
                                                />
                                            </div>
                                        )}

                                        {item.mode === 'teks' && (
                                            <div>
                                                <Textarea
                                                    name={`lampiran_teks_${index}`}
                                                    value={item.isi_teks}
                                                    onChange={(event) => updateLampiran(index, 'isi_teks', event.target.value)}
                                                    error={teksError}
                                                    rows={3}
                                                    placeholder="Tuliskan naskah atau catatan ringkas lampiran Renstra..."
                                                    disabled={disabled}
                                                />
                                            </div>
                                        )}
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
