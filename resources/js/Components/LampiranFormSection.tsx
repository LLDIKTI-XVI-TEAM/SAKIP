import React from 'react';
import { FileText, Link2, Plus, Trash2, Type } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';

export type LampiranMode = 'file' | 'tautan' | 'teks';

export interface LampiranDraft {
    clientId: string;
    mode: LampiranMode;
    file: File | null;
    tautan: string;
    isi_teks: string;
    nama_asli?: string;
}

export function newLampiran(): LampiranDraft {
    return {
        clientId: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        mode: 'file',
        file: null,
        tautan: '',
        isi_teks: '',
        nama_asli: '',
    };
}

export const modeMeta: Record<LampiranMode, { label: string; icon: React.ComponentType<{ className?: string }>; description: string }> = {
    file: { label: 'File', icon: FileText, description: 'Format PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.' },
    tautan: { label: 'Tautan', icon: Link2, description: 'Alamat tautan repositori dokumen resmi atau cloud storage.' },
    teks: { label: 'Teks', icon: Type, description: 'Kutipan substansi atau rangkuman naskah.' },
};

export interface LampiranFormSectionProps {
    lampiran: LampiranDraft[];
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
    canUpload?: boolean;
    locked?: boolean;
    lockedMessage?: string;
    emptyTitle?: string;
    emptyMessage?: string;
    title?: string;
    fileAccept?: string;
    fileHelperText?: string;
    showNamaAsli?: boolean;
    onChange: (updater: (previous: LampiranDraft[]) => LampiranDraft[]) => void;
    className?: string;
}

export function LampiranFormSection({
    lampiran,
    errors = {},
    disabled = false,
    canUpload = true,
    locked = false,
    lockedMessage = 'Lampiran dikunci dan tidak dapat diubah.',
    emptyTitle = 'Belum ada lampiran naskah',
    emptyMessage = 'Lampiran bersifat opsional dan dapat berupa file, tautan, atau teks.',
    title = 'Lampiran Dokumen Sumber & Bukti',
    fileAccept = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png',
    fileHelperText,
    showNamaAsli = false,
    onChange,
    className = '',
}: LampiranFormSectionProps) {
    const updateLampiran = <K extends keyof LampiranDraft>(index: number, field: K, value: LampiranDraft[K]) => {
        onChange((previous) =>
            previous.map((item, itemIndex) => (itemIndex === index ? { ...item, [field]: value } : item))
        );
    };

    const updateModeLampiran = (index: number, mode: LampiranMode) => {
        onChange((previous) =>
            previous.map((item, itemIndex) =>
                itemIndex === index
                    ? { ...item, mode, file: null, tautan: '', isi_teks: '' }
                    : item
            )
        );
    };

    const removeLampiran = (index: number) => {
        onChange((previous) => previous.filter((_, itemIndex) => itemIndex !== index));
    };

    const addLampiran = () => {
        onChange((previous) => [...previous, newLampiran()]);
    };

    return (
        <section aria-labelledby="lampiran-section-heading" className={`border-t border-border pt-6 ${className}`}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                <div>
                    <h2 id="lampiran-section-heading" className="text-base font-semibold text-ink leading-tight">
                        {title}
                    </h2>
                </div>
                {canUpload && !locked && (
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        onClick={addLampiran}
                        disabled={disabled}
                        className="gap-1.5 self-start sm:self-center"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah Lampiran
                    </Button>
                )}
            </div>

            {!canUpload || locked ? (
                <div className="rounded-xl border border-dashed border-border bg-soft/40 py-6 px-4 text-center">
                    <p className="text-sm font-medium text-muted">
                        {locked
                            ? lockedMessage
                            : 'Anda tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.'}
                    </p>
                </div>
            ) : lampiran.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border bg-page py-7 px-5 text-center">
                    <FileText className="mx-auto h-7 w-7 text-muted opacity-60" aria-hidden="true" />
                    <p className="mt-2 text-sm font-semibold text-ink">{emptyTitle}</p>
                    <p className="mt-1 text-xs text-muted">{emptyMessage}</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {lampiran.map((item, index) => {
                        const meta = modeMeta[item.mode];
                        const ModeIcon = meta.icon;
                        const errorPrefix = `lampiran.${index}`;
                        const fileError = errors[`${errorPrefix}.file`] ?? errors[`lampiran.${index}`];
                        const tautanError = errors[`${errorPrefix}.tautan`];
                        const teksError = errors[`${errorPrefix}.isi_teks`];

                        return (
                            <div key={item.clientId} className="rounded-xl border border-border bg-page p-4 sm:p-5 shadow-2xs">
                                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-3">
                                    <div className="flex flex-wrap items-center gap-2.5">
                                        <span className="flex h-6 w-6 items-center justify-center rounded-md bg-primary/10 text-xs font-semibold text-primary">
                                            {index + 1}
                                        </span>
                                        <span className="text-sm font-semibold text-ink">Lampiran {index + 1}</span>

                                        <div className="inline-flex items-center rounded-lg border border-border bg-soft/50 p-0.5">
                                            {(['file', 'tautan', 'teks'] as LampiranMode[]).map((mode) => {
                                                const isActive = item.mode === mode;
                                                const Icon = modeMeta[mode].icon;
                                                return (
                                                    <button
                                                        key={mode}
                                                        type="button"
                                                        onClick={() => updateModeLampiran(index, mode)}
                                                        disabled={disabled}
                                                        className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors cursor-pointer disabled:cursor-not-allowed ${
                                                            isActive
                                                                ? 'bg-surface text-primary shadow-xs font-semibold'
                                                                : 'text-muted hover:text-ink'
                                                        }`}
                                                    >
                                                        <Icon className="h-3.5 w-3.5" aria-hidden="true" />
                                                        {modeMeta[mode].label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => removeLampiran(index)}
                                        disabled={disabled}
                                        className="inline-flex items-center gap-1.5 self-end sm:self-center rounded-lg px-2 py-1 text-xs font-medium text-danger transition-colors hover:bg-danger/10 focus:outline-none focus:ring-2 focus:ring-danger/20 disabled:opacity-50 cursor-pointer"
                                        aria-label="Hapus Lampiran"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                                        Hapus Lampiran
                                    </button>
                                </div>

                                <div>
                                    {item.mode === 'file' && (
                                        <div>
                                            <Input
                                                type="file"
                                                label="Pilih Berkas"
                                                accept={fileAccept}
                                                onChange={(event) =>
                                                    updateLampiran(index, 'file', event.target.files?.[0] ?? null)
                                                }
                                                error={fileError}
                                                helperText={fileHelperText ?? meta.description}
                                                disabled={disabled}
                                            />
                                        </div>
                                    )}

                                    {item.mode === 'tautan' && (
                                        <div className="space-y-3">
                                            {showNamaAsli && (
                                                <Input
                                                    label="Nama / Judul Dokumen (Opsional)"
                                                    value={item.nama_asli ?? ''}
                                                    onChange={(event) => updateLampiran(index, 'nama_asli', event.target.value)}
                                                    placeholder="Contoh: Salinan Naskah di Google Drive"
                                                    disabled={disabled}
                                                />
                                            )}
                                            <Input
                                                type="url"
                                                label="URL / Tautan Dokumen"
                                                value={item.tautan}
                                                onChange={(event) => updateLampiran(index, 'tautan', event.target.value)}
                                                error={tautanError}
                                                placeholder="https://..."
                                                helperText={meta.description}
                                                disabled={disabled}
                                            />
                                        </div>
                                    )}

                                    {item.mode === 'teks' && (
                                        <div className="space-y-3">
                                            {showNamaAsli && (
                                                <Input
                                                    label="Nama / Judul Catatan (Opsional)"
                                                    value={item.nama_asli ?? ''}
                                                    onChange={(event) => updateLampiran(index, 'nama_asli', event.target.value)}
                                                    placeholder="Contoh: Ringkasan Kebijakan Renstra"
                                                    disabled={disabled}
                                                />
                                            )}
                                            <Textarea
                                                label="Isi Catatan / Kutipan Teks"
                                                value={item.isi_teks}
                                                onChange={(event) => updateLampiran(index, 'isi_teks', event.target.value)}
                                                error={teksError}
                                                helperText={meta.description}
                                                rows={3}
                                                placeholder="Tuliskan naskah atau catatan ringkas lampiran..."
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
    );
}

export default LampiranFormSection;
