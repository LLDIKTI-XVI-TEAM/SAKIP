import React from 'react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';

export interface IndikatorOption {
    id: string;
    kode: string;
    nama: string;
    status?: 'aktif' | 'arsip';
}

export interface JenisBerkasFormData {
    id?: string;
    nama: string;
    tahap: 'rencana_aksi' | 'pengukuran' | 'kegiatan' | 'kegiatan_perencanaan';
    indikator_id: string | '' | null;
    wajib: boolean;
    keterangan: string;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    semua_mode_wajib: boolean;
    hanya_kegiatan_beranggaran?: boolean;
    urutan: number;
    format_diizinkan: string;
    ukuran_maks_kb: number | '' | null;
    expected_updated_at?: string;
    aktif?: boolean;
}

interface JenisBerkasModalProps {
    isOpen: boolean;
    isEditing: boolean;
    data: JenisBerkasFormData;
    errors: Record<string, string | undefined>;
    indikators: IndikatorOption[];
    isLoading: boolean;
    batasUnggahan: BatasUnggahan;
    onChange: <K extends keyof JenisBerkasFormData>(field: K, value: JenisBerkasFormData[K]) => void;
    onClose: () => void;
    onSubmit: (e: React.FormEvent) => void;
}

export interface BatasUnggahan {
    ukuran_maks_kb: number;
    format_diizinkan: string;
    batas_atas_kb: number;
}

const MODE = [
    ['izinkan_file', 'jb-mode-file', 'File / dokumen'],
    ['izinkan_tautan', 'jb-mode-tautan', 'Tautan / URL'],
    ['izinkan_teks', 'jb-mode-teks', 'Teks / narasi'],
] as const;

export const JenisBerkasModal: React.FC<JenisBerkasModalProps> = ({
    isOpen,
    isEditing,
    data,
    errors,
    indikators,
    isLoading,
    batasUnggahan,
    onChange,
    onClose,
    onSubmit,
}) => {
    if (!isOpen) return null;

    const atLeastOneMode = data.izinkan_file || data.izinkan_tautan || data.izinkan_teks;
    const substantifDisabled = isLoading;

    const availableIndikators = indikators.filter((ind) => {
        if (ind.status !== 'arsip') {
            return true;
        }
        return isEditing && ind.id === data.indikator_id;
    });

    const handleSafeClose = () => {
        if (isLoading) return;
        onClose();
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleSafeClose}
            showCloseButton={!isLoading}
            size="2xl"
            title={isEditing ? 'Edit persyaratan' : 'Tambah persyaratan'}
            description="Bukti dukung yang harus dipenuhi PIC pada tahap kepatuhan."
            footer={
                <>
                    <Button type="button" variant="outline" disabled={isLoading} onClick={handleSafeClose}>
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="jenis-berkas-form"
                        isLoading={isLoading}
                        disabled={!atLeastOneMode}
                    >
                        {isEditing ? 'Lanjut ke konfirmasi' : 'Simpan persyaratan'}
                    </Button>
                </>
            }
        >
            <form id="jenis-berkas-form" onSubmit={onSubmit} className="space-y-5">
                <Input
                    id="jb-nama"
                    label="Nama persyaratan"
                    required
                    value={data.nama}
                    onChange={(e) => onChange('nama', e.target.value)}
                    placeholder="Contoh: Laporan Capaian Kinerja Triwulan"
                    disabled={substantifDisabled}
                    error={errors.nama}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Select
                        id="jb-tahap"
                        label="Tahap"
                        required
                        value={data.tahap}
                        onChange={(e) => onChange('tahap', e.target.value as JenisBerkasFormData['tahap'])}
                        disabled={substantifDisabled}
                        error={errors.tahap}
                        helperText="Persyaratan Rencana Aksi dan Perencanaan Kegiatan diperiksa saat rencana aksi diajukan; Kegiatan (SPJ) saat kegiatan ditandai terlaksana."
                        options={[
                            { value: 'rencana_aksi', label: 'Rencana Aksi' },
                            { value: 'pengukuran', label: 'Pengukuran Kinerja' },
                            { value: 'kegiatan_perencanaan', label: 'Perencanaan Kegiatan' },
                            { value: 'kegiatan', label: 'Kegiatan (SPJ)' },
                        ]}
                    />

                    <Select
                        id="jb-indikator"
                        label="Lingkup indikator"
                        value={data.indikator_id || ''}
                        onChange={(e) => onChange('indikator_id', e.target.value || null)}
                        disabled={substantifDisabled}
                        error={errors.indikator_id}
                    >
                        <option value="">Semua indikator</option>
                        {availableIndikators.map((ind) => (
                            <option key={ind.id} value={ind.id}>
                                {ind.kode} : {ind.nama}{ind.status === 'arsip' ? ' (Arsip)' : ''}
                            </option>
                        ))}
                    </Select>
                </div>

                <fieldset>
                    <legend className="text-sm font-medium text-ink">
                        Mode bukti yang diterima
                        <span className="ml-1 text-danger" aria-hidden="true">*</span>
                    </legend>
                    <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                        {MODE.map(([field, id, label]) => (
                            <label key={field} htmlFor={id} className="flex items-center gap-2 text-sm text-ink">
                                <input
                                    id={id}
                                    type="checkbox"
                                    checked={data[field]}
                                    onChange={(e) => onChange(field, e.target.checked)}
                                    disabled={substantifDisabled}
                                    className="h-4 w-4 accent-primary disabled:cursor-not-allowed"
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    {(errors.modes || !atLeastOneMode) && (
                        <p role="alert" className="mt-1.5 text-xs font-medium text-danger">
                            {errors.modes ?? 'Pilih minimal satu mode bukti.'}
                        </p>
                    )}
                </fieldset>

                <fieldset className="space-y-3">
                    <legend className="text-sm font-medium text-ink">Kewajiban</legend>
                    <CheckboxField
                        id="jb-wajib"
                        label="Bukti wajib"
                        help="Harus dipenuhi sebelum pengajuan rencana aksi, pengukuran, atau penyelesaian kegiatan."
                        checked={data.wajib}
                        disabled={substantifDisabled}
                        onChange={(checked) => onChange('wajib', checked)}
                    />
                    <CheckboxField
                        id="jb-semua-mode-wajib"
                        label="Semua mode wajib"
                        help="Setiap mode yang diterima harus dipenuhi pengunggah."
                        checked={data.semua_mode_wajib}
                        disabled={substantifDisabled}
                        onChange={(checked) => onChange('semua_mode_wajib', checked)}
                    />
                    {data.tahap === 'kegiatan_perencanaan' && (
                        <CheckboxField
                            id="jb-hanya-beranggaran"
                            label="Hanya kegiatan beranggaran"
                            help="Tidak dituntut dari kegiatan dengan anggaran Rp0, misalnya RAB."
                            checked={data.hanya_kegiatan_beranggaran === true}
                            disabled={substantifDisabled}
                            onChange={(checked) => onChange('hanya_kegiatan_beranggaran', checked)}
                        />
                    )}
                    {isEditing && (
                        <CheckboxField
                            id="jb-aktif"
                            label="Aktif"
                            help={
                                data.aktif !== false
                                    ? 'Dievaluasi saat pemeriksaan kelengkapan bukti.'
                                    : 'Tidak lagi dituntut pada pengajuan berikutnya; riwayat berkas lama tetap aman.'
                            }
                            checked={data.aktif !== false}
                            disabled={substantifDisabled}
                            onChange={(checked) => onChange('aktif', checked)}
                        />
                    )}
                </fieldset>

                <fieldset>
                    <legend className="text-sm font-medium text-ink">Format dan ukuran file</legend>
                    {!data.izinkan_file && <p className="mt-1 text-xs text-muted">Aktif bila mode file diterima.</p>}
                    <div className="mt-2 grid gap-4 sm:grid-cols-2">
                        <Input
                            id="jb-format"
                            label="Format file"
                            value={data.format_diizinkan}
                            onChange={(e) => onChange('format_diizinkan', e.target.value)}
                            placeholder="Contoh: pdf,docx,xlsx"
                            disabled={isLoading || !data.izinkan_file}
                            error={errors.format_diizinkan}
                            helperText={`Kosongkan untuk memakai format bawaan: ${batasUnggahan.format_diizinkan.split(',').join(', ')}.`}
                        />
                        <Input
                            id="jb-ukuran"
                            label="Ukuran maksimum (KB)"
                            type="number"
                            min={100}
                            max={batasUnggahan.batas_atas_kb}
                            step={1}
                            value={data.ukuran_maks_kb ?? ''}
                            onChange={(e) => onChange('ukuran_maks_kb', e.target.value ? Number(e.target.value) : null)}
                            placeholder="Contoh: 10240"
                            disabled={isLoading || !data.izinkan_file}
                            error={errors.ukuran_maks_kb}
                            helperText={`Kosongkan untuk bawaan ${batasUnggahan.ukuran_maks_kb.toLocaleString('id-ID')} KB. Minimal 100 KB, maksimal ${batasUnggahan.batas_atas_kb.toLocaleString('id-ID')} KB.`}
                        />
                    </div>
                </fieldset>

                <div className="grid gap-4 sm:grid-cols-4">
                    <Input
                        id="jb-urutan"
                        label="Urutan tampil"
                        type="number"
                        min={0}
                        value={data.urutan}
                        onChange={(e) => onChange('urutan', Number(e.target.value))}
                        disabled={substantifDisabled}
                        error={errors.urutan}
                    />
                    <div className="sm:col-span-3">
                        <Input
                            id="jb-keterangan"
                            label="Petunjuk untuk PIC"
                            value={data.keterangan}
                            onChange={(e) => onChange('keterangan', e.target.value)}
                            placeholder="Petunjuk khusus pengunggahan bagi PIC..."
                            disabled={substantifDisabled}
                            error={errors.keterangan}
                        />
                    </div>
                </div>
            </form>
        </Modal>
    );
};

function CheckboxField({
    id,
    label,
    help,
    checked,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    help: string;
    checked: boolean;
    disabled: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div>
            <label htmlFor={id} className="flex items-center gap-2 text-sm text-ink">
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    disabled={disabled}
                    aria-describedby={`${id}-help`}
                    onChange={(e) => onChange(e.target.checked)}
                    className="h-4 w-4 accent-primary disabled:cursor-not-allowed"
                />
                {label}
            </label>
            <p id={`${id}-help`} className="ml-6 mt-1 text-xs leading-relaxed text-muted">
                {help}
            </p>
        </div>
    );
}
