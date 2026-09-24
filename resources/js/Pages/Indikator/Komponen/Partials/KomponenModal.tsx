import React, { useEffect, useState } from 'react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Switch } from '@/Components/Switch';

export interface KomponenFormData {
    id?: string;
    kode: string;
    label: string;
    satuan: string;
    peran: 'pembilang' | 'penyebut' | 'penjumlah';
    bobot: number | string;
    urutan: number | string;
    aktif: boolean;
}

interface KomponenModalProps {
    open: boolean;
    isEditing: boolean;
    initialData: KomponenFormData;
    errors: Record<string, string>;
    isSubmitting: boolean;
    onClose: () => void;
    onSubmit: (formData: KomponenFormData) => void;
}

export function KomponenModal({
    open,
    isEditing,
    initialData,
    errors = {},
    isSubmitting,
    onClose,
    onSubmit,
}: KomponenModalProps) {
    const [formData, setFormData] = useState<KomponenFormData>(initialData);

    useEffect(() => {
        setFormData(initialData);
    }, [initialData, open]);

    const handleChange = <K extends keyof KomponenFormData>(field: K, value: KomponenFormData[K]) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(formData);
    };

    return (
        <Modal
            isOpen={open}
            onClose={onClose}
            title={isEditing ? 'Ubah Komponen Indikator' : 'Tambah Komponen Indikator'}
            description={
                isEditing
                    ? 'Perbarui definisi komponen indikator data-driven. Perubahan memerlukan pengisian alasan audit.'
                    : 'Tambahkan variabel komponen baru sebagai input pembentuk nilai indikator.'
            }
            size="lg"
            footer={
                <div className="flex items-center justify-end gap-2.5">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isSubmitting}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        onClick={handleSubmit}
                        isLoading={isSubmitting}
                    >
                        {isEditing ? 'Lanjutkan Perubahan' : 'Simpan Komponen'}
                    </Button>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {/* Kode Komponen */}
                    <div>
                        <Input
                            label="Kode Komponen"
                            required
                            placeholder="misal: sakip, zi_wbk, n, t"
                            value={formData.kode}
                            onChange={e => handleChange('kode', e.target.value.toLowerCase().replace(/\s+/g, '_'))}
                            error={errors.kode}
                            helperText="Hanya huruf kecil, angka, dan garis bawah (_)."
                        />
                    </div>

                    {/* Peran Komponen */}
                    <div>
                        <Select
                            label="Peran Komponen"
                            required
                            value={formData.peran}
                            onChange={e => handleChange('peran', e.target.value as any)}
                            error={errors.peran}
                            options={[
                                { value: 'pembilang', label: 'Pembilang (Numerator)' },
                                { value: 'penyebut', label: 'Penyebut (Denominator)' },
                                { value: 'penjumlah', label: 'Penjumlah (Additive)' },
                            ]}
                        />
                    </div>
                </div>

                {/* Label Komponen */}
                <div>
                    <Input
                        label="Label Komponen"
                        required
                        placeholder="misal: Skor Evaluasi SAKIP LLDIKTI XVI"
                        value={formData.label}
                        onChange={e => handleChange('label', e.target.value)}
                        error={errors.label}
                        helperText="Deskripsi lengkap dan jelas mengenai angka yang diinput."
                    />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {/* Bobot */}
                    <div>
                        <Input
                            label="Bobot Komponen"
                            type="number"
                            step="any"
                            min="0"
                            required
                            placeholder="1.0"
                            value={formData.bobot}
                            onChange={e => handleChange('bobot', e.target.value)}
                            error={errors.bobot}
                            helperText="Pengali bobot pada formula."
                        />
                    </div>

                    {/* Urutan */}
                    <div>
                        <Input
                            label="Urutan"
                            type="number"
                            min="1"
                            required
                            placeholder="1"
                            value={formData.urutan}
                            onChange={e => handleChange('urutan', e.target.value)}
                            error={errors.urutan}
                            helperText="Urutan posisi tampilan komponen."
                        />
                    </div>

                    {/* Satuan */}
                    <div>
                        <Input
                            label="Satuan Nilai"
                            placeholder="misal: %, Skor, PTS"
                            value={formData.satuan}
                            onChange={e => handleChange('satuan', e.target.value)}
                            error={errors.satuan}
                            helperText="Opsional."
                        />
                    </div>
                </div>

                {/* Switch Aktif */}
                <div className="rounded-lg border border-border bg-soft/50 p-3.5 flex items-center justify-between">
                    <div>
                        <span className="text-sm font-medium text-ink block">
                            Status Komponen Aktif
                        </span>
                        <p className="text-xs text-muted">
                            Hanya komponen berstatus aktif yang disertakan dalam perhitungan formula server.
                        </p>
                    </div>
                    <Switch
                        checked={formData.aktif}
                        onChange={val => handleChange('aktif', val)}
                        aria-label="Status Komponen Aktif"
                    />
                </div>
            </form>
        </Modal>
    );
}
