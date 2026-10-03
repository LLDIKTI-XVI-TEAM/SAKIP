import React, { useEffect, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Switch } from '@/Components/Switch';
import { Textarea } from '@/Components/Textarea';
import type {
    FormulaKomponenInput,
    FormulaKomponenItem,
    IndikatorKinerjaItem,
    IndikatorTipePerhitungan,
    KomponenPeran,
} from '@/types/sasaran-indikator';

interface FormulaModalProps {
    isOpen: boolean;
    onClose: () => void;
    indikator: IndikatorKinerjaItem | null;
}

const KODE_REGEX = /^[a-zA-Z0-9_]+$/;

const TIPE_OPTIONS: Array<{ value: IndikatorTipePerhitungan; label: string }> = [
    { value: 'manual', label: 'Manual' },
    { value: 'rasio_persen', label: 'Rasio Persen (%)' },
    { value: 'penjumlahan', label: 'Penjumlahan' },
];

const PERAN_OPTIONS: Array<{ value: KomponenPeran; label: string }> = [
    { value: 'pembilang', label: 'Pembilang' },
    { value: 'penyebut', label: 'Penyebut' },
    { value: 'penjumlah', label: 'Penjumlah' },
];

function defaultPeranFor(tipe: IndikatorTipePerhitungan): KomponenPeran {
    return tipe === 'penjumlahan' ? 'penjumlah' : 'pembilang';
}

function emptyRow(urutan: number, tipe: IndikatorTipePerhitungan): FormulaKomponenInput {
    return {
        kode: '',
        label: '',
        peran: defaultPeranFor(tipe),
        bobot: 1,
        urutan,
        satuan: '',
        aktif: true,
    };
}

function isValidTipe(value: string): value is IndikatorTipePerhitungan {
    return value === 'manual' || value === 'rasio_persen' || value === 'penjumlahan';
}

function componentFormRow(row: FormulaKomponenItem): FormulaKomponenInput {
    return {
        id: row.id,
        kode: row.kode,
        label: row.label,
        peran: row.peran,
        bobot: row.bobot,
        urutan: row.urutan,
        satuan: row.satuan ?? '',
        aktif: row.aktif,
    };
}

export const FormulaModal: React.FC<FormulaModalProps> = ({ isOpen, onClose, indikator }) => {
    const formulaUnavailable = Boolean(indikator && !Array.isArray(indikator.komponen));
    const prevOpenRef = useRef(false);
    const prevIndikatorIdRef = useRef<string | null>(null);
    const submittingRef = useRef(false);
    const formRef = useRef<HTMLFormElement>(null);
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

    const { data, setData, patch, processing, errors, reset, clearErrors, setError } = useForm({
        tipe_perhitungan: 'manual' as IndikatorTipePerhitungan,
        komponen: [] as FormulaKomponenInput[],
        expected_updated_at: '',
        alasan: '',
    });

    useEffect(() => {
        const wasOpen = prevOpenRef.current;
        const prevId = prevIndikatorIdRef.current;
        const currentId = indikator?.id ?? null;

        prevOpenRef.current = isOpen;
        prevIndikatorIdRef.current = currentId;

        if (isOpen && indikator && (!wasOpen || prevId !== currentId)) {
            clearErrors();
            setClientErrors({});
            setData({
                tipe_perhitungan: indikator.tipe_perhitungan,
                komponen: indikator.tipe_perhitungan === 'manual'
                    ? []
                    : (indikator.komponen ?? []).filter((row) => row.aktif).map(componentFormRow),
                expected_updated_at: indikator.updated_at ?? '',
                alasan: '',
            });
        } else if (!isOpen && wasOpen) {
            reset();
            clearErrors();
            setClientErrors({});
        }
    }, [isOpen, indikator, clearErrors, reset, setData]);

    useEffect(() => {
        if (isOpen && Object.keys(errors).length > 0) {
            errorSummaryRef.current?.focus();
        }
    }, [errors, isOpen]);

    const handleClose = () => {
        if (processing || submittingRef.current) {
            return;
        }
        reset();
        clearErrors();
        setClientErrors({});
        onClose();
    };

    const updateRow = (index: number, field: keyof FormulaKomponenInput, value: string | boolean) => {
        const next = data.komponen.map((row, i) => (i === index ? { ...row, [field]: value } : row));
        setData('komponen', next);
        const keys = [
            `komponen.${index}.${field}`,
            `komponen.${index}.kode`,
            `komponen.${index}.bobot`,
            'komponen',
        ];
        setClientErrors((prev) => {
            let changed = false;
            const copy = { ...prev };
            for (const key of keys) {
                if (copy[key] !== undefined) {
                    delete copy[key];
                    changed = true;
                }
            }
            return changed ? copy : prev;
        });
    };

    const handleAddRow = () => {
        if (data.komponen.length >= 50) {
            return;
        }
        const maxUrutan = data.komponen.reduce((acc, row) => {
            const n = Number(row.urutan);
            return Number.isInteger(n) && n > acc ? n : acc;
        }, 0);
        setData('komponen', [...data.komponen, emptyRow(maxUrutan + 1, data.tipe_perhitungan)]);
        setClientErrors((prev) => {
            if (prev['komponen'] === undefined) {
                return prev;
            }
            const copy = { ...prev };
            delete copy['komponen'];
            return copy;
        });
    };

    const handleRemoveRow = (index: number) => {
        setData(
            'komponen',
            data.komponen.filter((_, i) => i !== index)
        );
        setClientErrors((prev) => {
            const copy = { ...prev };
            let changed = false;
            for (const key of Object.keys(copy)) {
                if (key === 'komponen' || key.startsWith(`komponen.${index}.`)) {
                    delete copy[key];
                    changed = true;
                }
            }
            return changed ? copy : prev;
        });
    };

    const validate = (): Record<string, string> => {
        const found: Record<string, string> = {};

        if (!isValidTipe(data.tipe_perhitungan)) {
            found['tipe_perhitungan'] = 'Tipe perhitungan wajib dipilih.';
        }

        if (data.tipe_perhitungan !== 'manual' && data.komponen.length === 0) {
            found['komponen'] = 'Minimal satu komponen wajib ditambahkan untuk tipe Rasio Persen/Penjumlahan.';
        }

        if (data.komponen.length > 50) {
            found['komponen'] = 'Jumlah komponen maksimal 50 baris.';
        }

        const seenKode = new Map<string, number>();
        data.komponen.forEach((row, index) => {
            const kode = row.kode.trim();
            if (kode.length === 0) {
                found[`komponen.${index}.kode`] = 'Kode komponen wajib diisi.';
            } else if (kode.length > 50) {
                found[`komponen.${index}.kode`] = 'Kode komponen maksimal 50 karakter.';
            } else if (!KODE_REGEX.test(kode)) {
                found[`komponen.${index}.kode`] = 'Kode komponen hanya boleh berisi huruf, angka, dan garis bawah (_).';
            } else if (seenKode.has(kode)) {
                found[`komponen.${index}.kode`] = 'Kode komponen tidak boleh duplikat dalam satu transisi.';
            } else {
                seenKode.set(kode, index);
            }

            const label = row.label.trim();
            if (label.length === 0) {
                found[`komponen.${index}.label`] = 'Label komponen wajib diisi.';
            } else if (label.length > 255) {
                found[`komponen.${index}.label`] = 'Label komponen maksimal 255 karakter.';
            }

            if (row.peran !== 'pembilang' && row.peran !== 'penyebut' && row.peran !== 'penjumlah') {
                found[`komponen.${index}.peran`] = 'Peran komponen wajib dipilih.';
            }

            const bobotRaw = typeof row.bobot === 'string' ? row.bobot.trim() : row.bobot;
            if (bobotRaw === '' || bobotRaw === null || bobotRaw === undefined) {
                found[`komponen.${index}.bobot`] = 'Bobot komponen wajib diisi.';
            } else {
                const bobot = Number(bobotRaw);
                if (!Number.isFinite(bobot)) {
                    found[`komponen.${index}.bobot`] = 'Bobot komponen harus berupa angka numerik.';
                } else if (bobot < 0) {
                    found[`komponen.${index}.bobot`] = 'Bobot komponen minimal 0.';
                } else if (bobot > 999999999) {
                    found[`komponen.${index}.bobot`] = 'Bobot komponen maksimal 999999999.';
                } else if (row.peran === 'penyebut' && bobot <= 0) {
                    found[`komponen.${index}.bobot`] = 'Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.';
                }
            }

            const urutanRaw = typeof row.urutan === 'string' ? row.urutan.trim() : row.urutan;
            if (urutanRaw === '' || urutanRaw === null || urutanRaw === undefined) {
                found[`komponen.${index}.urutan`] = 'Urutan komponen wajib diisi.';
            } else {
                const urutan = Number(urutanRaw);
                if (!Number.isInteger(urutan) || urutan < 1 || urutan > 32767) {
                    found[`komponen.${index}.urutan`] = 'Urutan komponen wajib berupa bilangan bulat 1–32767.';
                }
            }

            if (row.satuan.trim().length > 50) {
                found[`komponen.${index}.satuan`] = 'Satuan komponen maksimal 50 karakter.';
            }
        });

        const alasan = data.alasan.trim();
        if (alasan.length === 0) {
            found['alasan'] = 'Alasan perubahan formula wajib diisi.';
        } else if (alasan.length < 5) {
            found['alasan'] = 'Alasan perubahan formula minimal 5 karakter.';
        } else if (alasan.length > 1000) {
            found['alasan'] = 'Alasan perubahan formula maksimal 1000 karakter.';
        }

        return found;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!indikator || formulaUnavailable || processing || submittingRef.current) {
            return;
        }
        const found = validate();
        setClientErrors(found);
        if (Object.keys(found).length > 0) {
            requestAnimationFrame(() => formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus());
            return;
        }
        submittingRef.current = true;
        patch(`/perencanaan/indikator/${indikator.id}/formula`, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as { error?: string } | undefined;
                const error = page.flash?.error ?? flash?.error;
                if (typeof error === 'string' && error.length > 0) {
                    setError('expected_updated_at', error);
                    return;
                }
                submittingRef.current = false;
                handleClose();
            },
            onFinish: () => {
                submittingRef.current = false;
            },
        });
    };

    if (!indikator) {
        return null;
    }

    const serverErrors = errors as Record<string, string | undefined>;
    const serverEntries = Object.entries(serverErrors).filter(
        (entry): entry is [string, string] => typeof entry[1] === 'string' && entry[1].length > 0
    );
    const fieldError = (key: string): string | undefined => clientErrors[key] ?? serverErrors[key];

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title={`Atur Formula — ${indikator.kode}`}
            size="2xl"
            footer={
                <div className="flex items-center justify-end gap-3">
                    <Button type="button" variant="outline" onClick={handleClose} disabled={processing}>
                        Batal
                    </Button>
                    <Button type="submit" variant="primary" onClick={handleSubmit} isLoading={processing} disabled={processing || formulaUnavailable}>
                        {processing ? 'Menyimpan...' : 'Simpan Formula'}
                    </Button>
                </div>
            }
        >
            <form ref={formRef} onSubmit={handleSubmit} className="space-y-4">
                {serverEntries.length > 0 && (
                    <div ref={errorSummaryRef} tabIndex={-1} role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 focus:outline-none focus:ring-2 focus:ring-danger/30">
                        <p className="text-sm font-semibold text-danger">Penyimpanan formula ditolak server:</p>
                        <ul className="mt-1 list-disc space-y-0.5 pl-5 text-sm text-danger">
                            {serverEntries.map(([key, message]) => (
                                <li key={key}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {formulaUnavailable ? (
                    <div role="alert" className="rounded-lg border border-warning/30 bg-warning/5 p-3 text-sm text-ink">
                        Data komponen formula belum tersedia atau tidak dapat diakses. Muat ulang halaman atau hubungi pengelola akses sebelum mengatur formula.
                    </div>
                ) : (
                    <fieldset disabled={processing} className="space-y-4">
                        <div>
                            <Select
                                id="formula_tipe"
                                label="Tipe Perhitungan Target"
                                value={data.tipe_perhitungan}
                                onChange={(e) => {
                                    const next = e.target.value;
                                    if (isValidTipe(next)) {
                                        let komponen = data.komponen;
                                        if (next === 'manual') {
                                            komponen = [];
                                        } else if (data.tipe_perhitungan === 'manual' && komponen.length === 0) {
                                            const existing = indikator.komponen ?? [];
                                            const active = existing.filter((row) => row.aktif);
                                            komponen = (active.length > 0 ? active : existing).map((row) => ({
                                                ...componentFormRow(row),
                                                aktif: true,
                                            }));
                                        }
                                        setData({ ...data, tipe_perhitungan: next, komponen });
                                    }
                                    setClientErrors((prev) => {
                                        if (prev['tipe_perhitungan'] === undefined) {
                                            return prev;
                                        }
                                        const copy = { ...prev };
                                        delete copy['tipe_perhitungan'];
                                        return copy;
                                    });
                                }}
                                error={fieldError('tipe_perhitungan')}
                                disabled={processing}
                                helperText="Pilih manual untuk menonaktifkan komponen, atau rasio/penjumlahan beserta daftar komponennya."
                                required
                            >
                                {TIPE_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        {data.tipe_perhitungan === 'manual' && (
                            <div className="rounded-lg border border-info/30 bg-info/5 p-3 text-sm text-ink">
                                <p className="text-xs text-muted">
                                    Target manual tidak memerlukan komponen. Komponen perhitungan existing akan dinonaktifkan
                                    saat formula disimpan; data historis tetap dipertahankan.
                                </p>
                            </div>
                        )}

                        {data.tipe_perhitungan !== 'manual' && <div className="space-y-3">
                            <p className="text-xs text-muted">
                                Daftar ini menjadi formula akhir. Komponen existing yang dihapus dari daftar akan dinonaktifkan saat disimpan.
                            </p>
                            <div className="flex items-center justify-between">
                                <h4 className="text-sm font-semibold text-ink">Daftar Komponen ({data.komponen.length})</h4>
                                <Button
                                    type="button"
                                    variant="primary"
                                    size="sm"
                                    onClick={handleAddRow}
                                    disabled={processing || data.komponen.length >= 50}
                                    className="gap-1.5"
                                >
                                    <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                                    Tambah Komponen
                                </Button>
                            </div>

                            {fieldError('komponen') && (
                                <p className="text-xs font-medium text-danger">{fieldError('komponen')}</p>
                            )}

                            {data.komponen.length === 0 && (
                                <div className="rounded-lg border border-border bg-soft/50 p-4 text-center">
                                    <p className="text-sm text-muted">Belum ada baris komponen. Tambahkan minimal satu baris untuk tipe nonmanual.</p>
                                </div>
                            )}

                            {data.komponen.map((row, index) => (
                                <div key={row.id ?? index} className="space-y-3 rounded-lg border border-border bg-soft/40 p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-muted">
                                            Komponen #{index + 1}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveRow(index)}
                                            disabled={processing}
                                            className="inline-flex items-center gap-1 rounded-md p-1 text-xs font-medium text-muted hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger"
                                            title={`Hapus komponen ${index + 1}`}
                                            aria-label={`Hapus komponen ${index + 1}`}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                                            Hapus
                                        </button>
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <Input
                                            id={`formula_komponen_${index}_kode`}
                                            label={`Kode komponen ${index + 1}`}
                                            placeholder="mis. N, T"
                                            value={row.kode}
                                            onChange={(e) => updateRow(index, 'kode', e.target.value)}
                                            error={fieldError(`komponen.${index}.kode`)}
                                            required
                                        />
                                        <Input
                                            id={`formula_komponen_${index}_label`}
                                            label={`Label komponen ${index + 1}`}
                                            placeholder="Nama komponen selengkapnya..."
                                            value={row.label}
                                            onChange={(e) => updateRow(index, 'label', e.target.value)}
                                            error={fieldError(`komponen.${index}.label`)}
                                            required
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <Select
                                            id={`formula_komponen_${index}_peran`}
                                            label={`Peran komponen ${index + 1}`}
                                            value={row.peran}
                                            onChange={(e) => updateRow(index, 'peran', e.target.value)}
                                            error={fieldError(`komponen.${index}.peran`)}
                                            required
                                        >
                                            {PERAN_OPTIONS.map((opt) => (
                                                <option key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </option>
                                            ))}
                                        </Select>
                                        <Input
                                            id={`formula_komponen_${index}_bobot`}
                                            label={`Bobot komponen ${index + 1}`}
                                            type="number"
                                            step="any"
                                            min="0"
                                            value={row.bobot}
                                            onChange={(e) => updateRow(index, 'bobot', e.target.value)}
                                            error={fieldError(`komponen.${index}.bobot`)}
                                            helperText={row.peran === 'penyebut' ? 'Penyebut wajib lebih besar dari 0.' : undefined}
                                            required
                                        />
                                        <Input
                                            id={`formula_komponen_${index}_urutan`}
                                            label={`Urutan komponen ${index + 1}`}
                                            type="number"
                                            min="1"
                                            value={row.urutan}
                                            onChange={(e) => updateRow(index, 'urutan', e.target.value)}
                                            error={fieldError(`komponen.${index}.urutan`)}
                                            required
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <Input
                                            id={`formula_komponen_${index}_satuan`}
                                            label={`Satuan komponen ${index + 1}`}
                                            placeholder="Opsional, mis. %, Skor"
                                            value={row.satuan}
                                            onChange={(e) => updateRow(index, 'satuan', e.target.value)}
                                            error={fieldError(`komponen.${index}.satuan`)}
                                        />
                                        <div className="flex items-center gap-3 rounded-lg border border-border bg-surface p-3">
                                            <Switch
                                                id={`formula_komponen_${index}_aktif`}
                                                checked={row.aktif}
                                                onChange={(checked) => updateRow(index, 'aktif', checked)}
                                                aria-label={`Aktif komponen ${index + 1}`}
                                            />
                                            <label htmlFor={`formula_komponen_${index}_aktif`} className="cursor-pointer text-sm font-medium text-ink">
                                                Komponen aktif dalam perhitungan
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>}

                        <div>
                            <Textarea
                                id="formula_alasan"
                                label="Alasan Perubahan Formula"
                                placeholder="Jelaskan alasan penyesuaian formula ini (minimal 5 karakter)..."
                                rows={3}
                                value={data.alasan}
                                onChange={(e) => {
                                    setData('alasan', e.target.value);
                                    setClientErrors((prev) => {
                                        if (prev['alasan'] === undefined) {
                                            return prev;
                                        }
                                        const copy = { ...prev };
                                        delete copy['alasan'];
                                        return copy;
                                    });
                                }}
                                error={fieldError('alasan')}
                                helperText="Alasan wajib diisi dan dicatat pada audit perubahan komponen via formula."
                                required
                            />
                        </div>

                        <p className="text-xs text-muted">
                            Transisi dikunci bersama data terkini indikator. Bila data berubah sejak modal dibuka, server menolak dengan
                            pesan konflik dan halaman perlu dimuat ulang.
                        </p>
                    </fieldset>
                )}
            </form>
        </Modal>
    );
};
