import React, { useId, type ReactNode } from 'react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import type { LampiranDraft, LampiranMode } from '@/types/lampiran';

interface LampiranDraftFieldsProps {
    items: LampiranDraft[];
    errors: Record<string, string | undefined>;
    onChange: (updater: (previous: LampiranDraft[]) => LampiranDraft[]) => void;
    title: string;
    description?: string;
    modeDescriptions: Record<LampiranMode, string>;
    placeholders: { tautan: string; teks: string };
    emptyText: string;
    disabled?: boolean;
    accept?: string;
    /** Unggah file dimatikan (mis. oleh pengaturan storage); mode tautan/teks tetap dapat dipakai. */
    fileDisabled?: boolean;
    /** Field khusus modul untuk mode tautan/teks, dirender di atas input utama. */
    renderExtraFields?: (index: number) => ReactNode;
    /** Bila diisi, penambahan lampiran ditutup dan pesan ini yang ditampilkan. */
    blockedMessage?: string;
}

const modeLabels: Record<LampiranMode, string> = { file: 'File', tautan: 'Tautan', teks: 'Teks' };

function newLampiran(): LampiranDraft {
    return {
        clientId: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        mode: 'file',
        file: null,
        tautan: '',
        isi_teks: '',
    };
}

/** Editor draf lampiran file/tautan/teks yang dipakai bersama form master; validasi tetap di server. */
export function LampiranDraftFields({
    items,
    errors,
    onChange,
    title,
    description,
    modeDescriptions,
    placeholders,
    emptyText,
    disabled = false,
    accept,
    fileDisabled = false,
    renderExtraFields,
    blockedMessage,
}: LampiranDraftFieldsProps) {
    const headingId = useId();

    const updateLampiran = <K extends keyof LampiranDraft>(index: number, field: K, value: LampiranDraft[K]) => {
        onChange((previous) => previous.map((item, itemIndex) => (
            itemIndex === index ? { ...item, [field]: value } : item
        )));
    };

    const updateModeLampiran = (index: number, mode: LampiranMode) => {
        onChange((previous) => previous.map((item, itemIndex) => (
            itemIndex === index ? { ...item, mode, file: null, tautan: '', isi_teks: '' } : item
        )));
    };

    return (
        <section aria-labelledby={headingId}>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id={headingId} className="text-base font-semibold leading-tight text-ink">{title}</h2>
                    {description && <p className="mt-0.5 max-w-3xl text-sm leading-snug text-muted">{description}</p>}
                </div>
                {!blockedMessage && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onChange((previous) => [...previous, newLampiran()])}
                        disabled={disabled}
                        className="shrink-0 self-start whitespace-nowrap sm:self-center"
                    >
                        Tambah Lampiran
                    </Button>
                )}
            </div>

            {errors.lampiran && <p className="mb-3 text-sm text-danger">{errors.lampiran}</p>}

            {blockedMessage || items.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border bg-soft/40 px-4 py-6 text-center">
                    <p className="text-sm font-medium text-muted">{blockedMessage ?? emptyText}</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {items.map((item, index) => {
                        const prefix = `lampiran.${index}`;
                        const fileError = errors[`${prefix}.file`] ?? errors[prefix];
                        const fileErrorId = `${headingId}-file-${index}-error`;

                        return (
                            <div key={item.clientId} className="rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-5">
                                <div className="flex flex-col gap-3 border-b border-border pb-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold tabular-nums text-primary">
                                            {index + 1}
                                        </span>
                                        <div className="flex gap-3">
                                            {(Object.keys(modeLabels) as LampiranMode[]).map((mode) => (
                                                <button
                                                    key={mode}
                                                    type="button"
                                                    onClick={() => updateModeLampiran(index, mode)}
                                                    disabled={disabled}
                                                    aria-pressed={item.mode === mode}
                                                    className={`border-b-2 px-0.5 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 ${
                                                        item.mode === mode
                                                            ? 'border-primary text-primary'
                                                            : 'border-transparent text-muted hover:text-ink'
                                                    }`}
                                                >
                                                    {modeLabels[mode]}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => onChange((previous) => previous.filter((_, itemIndex) => itemIndex !== index))}
                                        disabled={disabled}
                                        className="self-end text-xs font-medium text-danger hover:underline disabled:opacity-50"
                                    >
                                        Hapus Lampiran
                                    </button>
                                </div>

                                <div className="mt-4">
                                    <p className="mb-3 text-xs text-muted">{modeDescriptions[item.mode]}</p>
                                    {errors[`${prefix}.mode`] && <p className="mb-3 text-xs text-danger">{errors[`${prefix}.mode`]}</p>}

                                    {item.mode === 'file' && (
                                        <div>
                                            <input
                                                type="file"
                                                accept={accept}
                                                aria-label={`File lampiran ${index + 1}`}
                                                aria-invalid={fileError ? true : undefined}
                                                aria-describedby={fileError ? fileErrorId : undefined}
                                                onChange={(event) => updateLampiran(index, 'file', event.target.files?.[0] ?? null)}
                                                disabled={disabled || fileDisabled}
                                                className="block w-full text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 focus:outline-none"
                                            />
                                            {fileError && <p id={fileErrorId} role="alert" className="mt-1 text-xs text-danger">{fileError}</p>}
                                        </div>
                                    )}

                                    {item.mode !== 'file' && renderExtraFields && <div className="mb-3">{renderExtraFields(index)}</div>}

                                    {item.mode === 'tautan' && (
                                        <Input
                                            name={`lampiran_tautan_${index}`}
                                            aria-label={`Tautan lampiran ${index + 1}`}
                                            value={item.tautan}
                                            onChange={(event) => updateLampiran(index, 'tautan', event.target.value)}
                                            error={errors[`${prefix}.tautan`]}
                                            placeholder={placeholders.tautan}
                                            disabled={disabled}
                                        />
                                    )}

                                    {item.mode === 'teks' && (
                                        <Textarea
                                            name={`lampiran_teks_${index}`}
                                            aria-label={`Teks lampiran ${index + 1}`}
                                            value={item.isi_teks}
                                            onChange={(event) => updateLampiran(index, 'isi_teks', event.target.value)}
                                            error={errors[`${prefix}.isi_teks`]}
                                            rows={3}
                                            placeholder={placeholders.teks}
                                            disabled={disabled}
                                        />
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
