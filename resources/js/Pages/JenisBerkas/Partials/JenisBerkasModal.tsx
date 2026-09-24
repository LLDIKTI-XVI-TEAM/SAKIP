import React from 'react';
import { FileText, CheckSquare, Settings2, AlertCircle } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Switch } from '@/Components/Switch';

export interface IndikatorOption {
    id: string;
    kode: string;
    nama: string;
    is_aktif?: boolean;
}

export interface JenisBerkasFormData {
    id?: string;
    nama: string;
    tahap: 'rencana_aksi' | 'pengukuran' | 'kegiatan';
    indikator_id: string | '' | null;
    wajib: boolean;
    keterangan: string;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    semua_mode_wajib: boolean;
    urutan: number;
    format_diizinkan: string;
    ukuran_maks_kb: number | '' | null;
    expected_updated_at?: string;
    aktif?: boolean;
}

interface JenisBerkasModalProps {
    isOpen: boolean;
    isEditing: boolean;
    isBatasTeknisOnly?: boolean;
    data: JenisBerkasFormData;
    errors: Record<string, string>;
    indikators: IndikatorOption[];
    isLoading: boolean;
    unggahanAktif?: boolean;
    canManageSettings?: boolean;
    onChange: (field: keyof JenisBerkasFormData, value: any) => void;
    onClose: () => void;
    onSubmit: (e: React.FormEvent) => void;
}

export const JenisBerkasModal: React.FC<JenisBerkasModalProps> = ({
    isOpen,
    isEditing,
    isBatasTeknisOnly = false,
    data,
    errors,
    indikators,
    isLoading,
    unggahanAktif = true,
    canManageSettings = true,
    onChange,
    onClose,
    onSubmit,
}) => {
    if (!isOpen) return null;

    const atLeastOneMode = data.izinkan_file || data.izinkan_tautan || data.izinkan_teks;

    const availableIndikators = indikators.filter((ind) => {
        if (ind.is_aktif !== false) {
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
            hideScrollbar={true}
            bodyClassName="p-3 sm:p-4"
            title={
                <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold">
                        {isBatasTeknisOnly ? <Settings2 className="w-4 h-4" /> : <FileText className="w-4 h-4" />}
                    </div>
                    <span>
                        {isBatasTeknisOnly
                            ? 'Ubah Batas Teknis Persyaratan'
                            : isEditing
                            ? 'Ubah Persyaratan Jenis Berkas'
                            : 'Tambah Persyaratan Jenis Berkas'}
                    </span>
                </div>
            }
            description={
                isBatasTeknisOnly
                    ? 'Penyesuaian konfigurasi format berkas dan batas ukuran dokumen oleh Administrator'
                    : 'Konfigurasi standar bukti dukung sesuai alur dan kepatuhan SAKIP'
            }
        >
            <form onSubmit={onSubmit} className="space-y-2 sm:space-y-2.5">
                {isBatasTeknisOnly && (
                    <div className="p-2 bg-info/10 border border-info/20 rounded-lg flex items-start gap-2 text-xs text-info-dark" role="alert">
                        <Settings2 className="w-4 h-4 text-info-dark shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-info-dark">Mode Batas Teknis Pengaturan</span>
                            <p className="mt-0.5 text-muted leading-relaxed text-[11px]">
                                Anda sedang mengubah batas teknis format dan ukuran berkas. Kolom substantif persyaratan hanya dapat diubah oleh tim Perencanaan.
                            </p>
                        </div>
                    </div>
                )}

                {/* Nama Persyaratan */}
                <Input
                    id="jb-nama"
                    label="Nama Persyaratan Bukti"
                    required
                    value={data.nama}
                    onChange={(e) => onChange('nama', e.target.value)}
                    placeholder="Contoh: Laporan Capaian Kinerja Triwulan"
                    disabled={isLoading || isBatasTeknisOnly}
                    error={errors.nama}
                    className="py-1.5 sm:py-2 text-xs sm:text-sm"
                />

                {/* Tahap & Lingkup Indikator */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                    <Select
                        id="jb-tahap"
                        label="Tahap Kepatuhan"
                        required
                        value={data.tahap}
                        onChange={(e) => onChange('tahap', e.target.value)}
                        disabled={isLoading || isBatasTeknisOnly}
                        error={errors.tahap}
                        helperText="Tahap saat ini mendukung Pengukuran Kinerja sesuai alur gerbang bukti SAKIP."
                        className="py-1.5 sm:py-2 text-xs sm:text-sm"
                        options={[
                            { value: 'pengukuran', label: 'Pengukuran Kinerja' },
                        ]}
                    />

                    <Select
                        id="jb-indikator"
                        label="Lingkup Indikator"
                        value={data.indikator_id || ''}
                        onChange={(e) => onChange('indikator_id', e.target.value || null)}
                        disabled={isLoading || isBatasTeknisOnly}
                        error={errors.indikator_id}
                        className="py-1.5 sm:py-2 text-xs sm:text-sm"
                    >
                        <option value="">Global (Berlaku Semua Indikator)</option>
                        {availableIndikators.map((ind) => (
                            <option key={ind.id} value={ind.id}>
                                {ind.kode} : {ind.nama}{ind.is_aktif === false ? ' (Nonaktif)' : ''}
                            </option>
                        ))}
                    </Select>
                </div>

                {/* Mode Bukti yang Diizinkan */}
                <div className="p-2 sm:p-2.5 rounded-lg border border-border bg-soft/50 space-y-2">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-ink flex items-center gap-1.5">
                            <CheckSquare className="w-3.5 h-3.5 text-primary" />
                            Mode Bukti yang Diizinkan <span className="text-danger">*</span>
                        </span>
                        {!atLeastOneMode && (
                            <span className="text-[11px] font-semibold text-danger">
                                Minimal 1 mode harus aktif
                            </span>
                        )}
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div className={`flex items-center justify-between p-2 rounded-lg border transition-colors ${data.izinkan_file ? 'border-primary/40 bg-surface' : 'border-border bg-surface/60'}`}>
                            <label htmlFor="jb-mode-file" className="font-medium text-xs text-ink cursor-pointer select-none">
                                File / Dokumen
                            </label>
                            <Switch
                                id="jb-mode-file"
                                checked={data.izinkan_file}
                                onChange={(checked) => onChange('izinkan_file', checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                aria-label="Izinkan Mode File / Dokumen"
                            />
                        </div>

                        <div className={`flex items-center justify-between p-2 rounded-lg border transition-colors ${data.izinkan_tautan ? 'border-primary/40 bg-surface' : 'border-border bg-surface/60'}`}>
                            <label htmlFor="jb-mode-tautan" className="font-medium text-xs text-ink cursor-pointer select-none">
                                Tautan / URL
                            </label>
                            <Switch
                                id="jb-mode-tautan"
                                checked={data.izinkan_tautan}
                                onChange={(checked) => onChange('izinkan_tautan', checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                aria-label="Izinkan Mode Tautan / URL"
                            />
                        </div>

                        <div className={`flex items-center justify-between p-2 rounded-lg border transition-colors ${data.izinkan_teks ? 'border-primary/40 bg-surface' : 'border-border bg-surface/60'}`}>
                            <label htmlFor="jb-mode-teks" className="font-medium text-xs text-ink cursor-pointer select-none">
                                Teks / Narasi
                            </label>
                            <Switch
                                id="jb-mode-teks"
                                checked={data.izinkan_teks}
                                onChange={(checked) => onChange('izinkan_teks', checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                aria-label="Izinkan Mode Teks / Narasi"
                            />
                        </div>
                    </div>
                    {errors.modes && (
                        <p className="text-[11px] font-medium text-danger">{errors.modes}</p>
                    )}
                </div>

                {/* Pengaturan Kewajiban */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 p-2 sm:p-2.5 rounded-lg border border-border bg-soft/50">
                    <div className="flex items-start justify-between gap-3 p-2.5 rounded-lg bg-surface border border-border">
                        <div>
                            <label htmlFor="jb-wajib" className="font-semibold text-xs text-ink cursor-pointer select-none">
                                Bukti Wajib
                            </label>
                            <p className="text-[11px] leading-relaxed text-muted mt-0.5">
                                Harus dipenuhi sebelum pengajuan rencana aksi, pengukuran, atau penyelesaian kegiatan.
                            </p>
                        </div>
                        <Switch
                            id="jb-wajib"
                            checked={data.wajib}
                            onChange={(checked) => onChange('wajib', checked)}
                            disabled={isLoading || isBatasTeknisOnly}
                            aria-label="Bukti Wajib"
                        />
                    </div>

                    <div className="flex items-start justify-between gap-3 p-2.5 rounded-lg bg-surface border border-border">
                        <div>
                            <label htmlFor="jb-semua-mode-wajib" className="font-semibold text-xs text-ink cursor-pointer select-none">
                                Semua Mode Wajib
                            </label>
                            <p className="text-[11px] leading-relaxed text-muted mt-0.5">
                                Jika aktif, setiap mode yang diizinkan harus dipenuhi oleh pengunggah.
                            </p>
                        </div>
                        <Switch
                            id="jb-semua-mode-wajib"
                            checked={data.semua_mode_wajib}
                            onChange={(checked) => onChange('semua_mode_wajib', checked)}
                            disabled={isLoading || isBatasTeknisOnly}
                            aria-label="Semua Mode Wajib"
                        />
                    </div>
                </div>

                {/* Status Aktif / Nonaktif Persyaratan (Khusus saat Edit) */}
                {isEditing && (
                    <div className={`p-2.5 rounded-lg border transition-colors flex items-start justify-between gap-3 ${data.aktif !== false ? 'border-success/30 bg-success/10' : 'border-danger/30 bg-danger/10'}`}>
                        <div>
                            <label htmlFor="jb-aktif" className={`font-semibold text-xs cursor-pointer select-none ${data.aktif !== false ? 'text-success-dark' : 'text-danger'}`}>
                                {data.aktif !== false ? 'Persyaratan Aktif' : 'Persyaratan Dinonaktifkan (Usang)'}
                            </label>
                            <p className="text-[11px] text-muted mt-0.5 leading-relaxed">
                                {data.aktif !== false 
                                    ? 'Persyaratan ini aktif berlaku pada tahap kepatuhan dan akan dievaluasi saat pemeriksaan kelengkapan bukti.'
                                    : 'Persyaratan yang dinonaktifkan tidak akan lagi dituntut atau dievaluasi pada pengajuan bukti mendatang, namun riwayat berkas lama yang merujuknya tetap aman.'}
                            </p>
                        </div>
                        <Switch
                            id="jb-aktif"
                            checked={data.aktif !== false}
                            onChange={(checked) => onChange('aktif', checked)}
                            disabled={isLoading || isBatasTeknisOnly}
                            aria-label="Status Keaktifan Persyaratan"
                        />
                    </div>
                )}

                {/* Peringatan jika berkas.unggahan_aktif = false dan syarat wajib hanya mode file */}
                {!unggahanAktif && data.wajib && data.izinkan_file && !data.izinkan_tautan && !data.izinkan_teks && (
                    <div className="p-2 bg-warning/10 border border-warning/25 rounded-lg flex items-start gap-2 text-xs text-warning-dark" role="alert">
                        <AlertCircle className="w-4 h-4 text-warning-dark shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-warning-dark">Peringatan: Mode Unggahan File Dinonaktifkan Global</span>
                            <p className="mt-0.5 text-muted leading-tight text-[10.5px]">
                                Setelan aplikasi saat ini menonaktifkan mode unggahan file (<code className="bg-warning/15 px-1 py-0.5 rounded font-mono text-[10px] text-ink">berkas.unggahan_aktif = false</code>). 
                                Persyaratan wajib dengan hanya mode file ini berpotensi tidak dapat dipenuhi oleh PIC dan akan ditandai <span className="font-semibold text-ink">tidak dapat dipenuhi</span> pada alur kerja. Disarankan mengaktifkan mode tautan atau teks.
                            </p>
                        </div>
                    </div>
                )}

                {/* Batasan Teknis File (Hanya relevan jika izinkan_file = true) */}
                <div className={`p-2 sm:p-2.5 rounded-lg border transition-colors ${data.izinkan_file ? 'border-border bg-surface' : 'border-border bg-soft opacity-60'}`}>
                    <div className="flex items-center gap-1.5 mb-1.5">
                        <Settings2 className="w-3.5 h-3.5 text-muted" />
                        <span className="text-xs font-semibold text-ink">
                            Batasan Teknis File {data.izinkan_file ? '' : '(Nonaktif karena mode file tidak diizinkan)'}
                        </span>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        <Input
                            id="jb-format"
                            label="Format File Diizinkan"
                            value={data.format_diizinkan}
                            onChange={(e) => onChange('format_diizinkan', e.target.value)}
                            placeholder="Contoh: pdf,docx,xlsx,jpg,png"
                            disabled={isLoading || !data.izinkan_file || !canManageSettings}
                            error={errors.format_diizinkan}
                            helperText={!canManageSettings ? 'Hanya Admin/Superadmin yang dapat mengubah format.' : 'Kosong = default aplikasi'}
                            className="py-1.5 sm:py-2 text-xs sm:text-sm"
                        />

                        <Input
                            id="jb-ukuran"
                            label="Batas Ukuran Maksimum (KB)"
                            type="number"
                            min={100}
                            step={1}
                            value={data.ukuran_maks_kb ?? ''}
                            onChange={(e) => onChange('ukuran_maks_kb', e.target.value ? Number(e.target.value) : null)}
                            placeholder="Contoh: 10240 (10 MB)"
                            disabled={isLoading || !data.izinkan_file || !canManageSettings}
                            error={errors.ukuran_maks_kb}
                            helperText={!canManageSettings ? 'Hanya Admin/Superadmin yang dapat mengubah batas ukuran.' : 'Kosong = default aplikasi (minimal 100 KB)'}
                            className="py-1.5 sm:py-2 text-xs sm:text-sm"
                        />
                    </div>
                </div>

                {/* Urutan & Keterangan */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 sm:gap-3">
                    <div className="sm:col-span-1">
                        <Input
                            id="jb-urutan"
                            label="Urutan Tampil"
                            type="number"
                            min={0}
                            value={data.urutan}
                            onChange={(e) => onChange('urutan', Number(e.target.value))}
                            disabled={isLoading || isBatasTeknisOnly}
                            error={errors.urutan}
                            className="py-1.5 sm:py-2 text-xs sm:text-sm"
                        />
                    </div>

                    <div className="sm:col-span-3">
                        <Input
                            id="jb-keterangan"
                            label="Petunjuk / Keterangan PIC"
                            type="text"
                            value={data.keterangan}
                            onChange={(e) => onChange('keterangan', e.target.value)}
                            placeholder="Petunjuk khusus pengunggahan bagi PIC..."
                            disabled={isLoading || isBatasTeknisOnly}
                            error={errors.keterangan}
                            className="py-1.5 sm:py-2 text-xs sm:text-sm"
                        />
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex items-center justify-end gap-2 pt-2.5 border-t border-border">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={isLoading}
                        onClick={handleSafeClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        size="sm"
                        isLoading={isLoading}
                        disabled={!isBatasTeknisOnly && !atLeastOneMode}
                    >
                        {isBatasTeknisOnly || isEditing ? 'Lanjut ke Konfirmasi' : 'Simpan Persyaratan'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
};
