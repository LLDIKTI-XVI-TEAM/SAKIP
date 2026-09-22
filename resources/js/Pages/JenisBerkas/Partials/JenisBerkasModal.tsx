import React from 'react';
import { FileText, CheckSquare, Settings2, AlertCircle } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';

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
            scrollable={false}
            bodyClassName="p-4 sm:p-5"
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
            <form onSubmit={onSubmit} className="space-y-2.5 sm:space-y-3">
                {isBatasTeknisOnly && (
                    <div className="p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex items-start gap-2 text-xs text-blue-800" role="alert">
                        <Settings2 className="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-blue-900">Mode Batas Teknis Pengaturan</span>
                            <p className="mt-0.5 text-blue-700 leading-relaxed">
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
                />

                {/* Tahap & Lingkup Indikator */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <Select
                        id="jb-tahap"
                        label="Tahap Kepatuhan"
                        required
                        value={data.tahap}
                        onChange={(e) => onChange('tahap', e.target.value)}
                        disabled={isLoading || isBatasTeknisOnly}
                        error={errors.tahap}
                        options={[
                            { value: 'rencana_aksi', label: 'Rencana Aksi' },
                            { value: 'pengukuran', label: 'Pengukuran Kinerja' },
                            { value: 'kegiatan', label: 'Pelaksanaan Kegiatan (SPJ)' },
                        ]}
                    />

                    <Select
                        id="jb-indikator"
                        label="Lingkup Indikator"
                        value={data.indikator_id || ''}
                        onChange={(e) => onChange('indikator_id', e.target.value || null)}
                        disabled={isLoading || isBatasTeknisOnly}
                        error={errors.indikator_id}
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
                <div className="p-2.5 sm:p-3 rounded-lg border border-slate-200 bg-slate-50/50 space-y-2">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-800 flex items-center gap-1.5">
                            <CheckSquare className="w-3.5 h-3.5 text-primary" />
                            Mode Bukti yang Diizinkan <span className="text-rose-600">*</span>
                        </span>
                        {!atLeastOneMode && (
                            <span className="text-[11px] font-semibold text-rose-600">
                                Minimal 1 mode harus aktif
                            </span>
                        )}
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        <label className="flex items-center gap-2 p-1.5 sm:p-2 rounded-md bg-white border border-slate-200 text-xs text-slate-700 cursor-pointer hover:bg-slate-50">
                            <input
                                type="checkbox"
                                checked={data.izinkan_file}
                                onChange={(e) => onChange('izinkan_file', e.target.checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                className="rounded border-slate-300 text-primary focus:ring-primary"
                            />
                            <span className="font-medium text-[11px] sm:text-xs">File / Dokumen</span>
                        </label>

                        <label className="flex items-center gap-2 p-1.5 sm:p-2 rounded-md bg-white border border-slate-200 text-xs text-slate-700 cursor-pointer hover:bg-slate-50">
                            <input
                                type="checkbox"
                                checked={data.izinkan_tautan}
                                onChange={(e) => onChange('izinkan_tautan', e.target.checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                className="rounded border-slate-300 text-primary focus:ring-primary"
                            />
                            <span className="font-medium text-[11px] sm:text-xs">Tautan / URL</span>
                        </label>

                        <label className="flex items-center gap-2 p-1.5 sm:p-2 rounded-md bg-white border border-slate-200 text-xs text-slate-700 cursor-pointer hover:bg-slate-50">
                            <input
                                type="checkbox"
                                checked={data.izinkan_teks}
                                onChange={(e) => onChange('izinkan_teks', e.target.checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                className="rounded border-slate-300 text-primary focus:ring-primary"
                            />
                            <span className="font-medium text-[11px] sm:text-xs">Teks / Narasi</span>
                        </label>
                    </div>
                    {errors.modes && (
                        <p className="text-[11px] font-medium text-rose-600">{errors.modes}</p>
                    )}
                </div>

                {/* Pengaturan Kewajiban */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 p-2.5 sm:p-3 rounded-lg border border-slate-200 bg-slate-50/50">
                    <label className="flex items-start gap-2 text-xs text-slate-700 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.wajib}
                            onChange={(e) => onChange('wajib', e.target.checked)}
                            disabled={isLoading || isBatasTeknisOnly}
                            className="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary"
                        />
                        <div>
                            <span className="font-semibold text-slate-900">Bukti Wajib</span>
                            <p className="text-[11px] text-slate-500">
                                Harus dipenuhi sebelum pengajuan rencana aksi, pengukuran, atau penyelesaian kegiatan.
                            </p>
                        </div>
                    </label>

                    <label className="flex items-start gap-2 text-xs text-slate-700 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.semua_mode_wajib}
                            onChange={(e) => onChange('semua_mode_wajib', e.target.checked)}
                            disabled={isLoading || isBatasTeknisOnly}
                            className="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary"
                        />
                        <div>
                            <span className="font-semibold text-slate-900">Semua Mode Wajib</span>
                            <p className="text-[11px] text-slate-500">
                                Jika aktif, setiap mode yang diizinkan harus dipenuhi oleh pengunggah.
                            </p>
                        </div>
                    </label>
                </div>

                {/* Status Aktif / Nonaktif Persyaratan (Khusus saat Edit) */}
                {isEditing && (
                    <div className={`p-2.5 sm:p-3 rounded-lg border transition-colors ${data.aktif !== false ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40'}`}>
                        <label className="flex items-start gap-2 text-xs cursor-pointer">
                            <input
                                type="checkbox"
                                id="jb-aktif"
                                checked={data.aktif !== false}
                                onChange={(e) => onChange('aktif', e.target.checked)}
                                disabled={isLoading || isBatasTeknisOnly}
                                className="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary"
                            />
                            <div>
                                <span className={`font-semibold ${data.aktif !== false ? 'text-emerald-900' : 'text-rose-900'}`}>
                                    {data.aktif !== false ? 'Persyaratan Aktif' : 'Persyaratan Dinonaktifkan (Usang)'}
                                </span>
                                <p className="text-[11px] text-slate-600 mt-0.5 leading-relaxed">
                                    {data.aktif !== false 
                                        ? 'Persyaratan ini aktif berlaku pada tahap kepatuhan dan akan dievaluasi saat pemeriksaan kelengkapan bukti.'
                                        : 'Persyaratan yang dinonaktifkan tidak akan lagi dituntut atau dievaluasi pada pengajuan bukti mendatang, namun riwayat berkas lama yang merujuknya tetap aman.'}
                                </p>
                            </div>
                        </label>
                    </div>
                )}

                {/* Peringatan jika berkas.unggahan_aktif = false dan syarat wajib hanya mode file */}
                {!unggahanAktif && data.wajib && data.izinkan_file && !data.izinkan_tautan && !data.izinkan_teks && (
                    <div className="p-2.5 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2 text-xs text-amber-800" role="alert">
                        <AlertCircle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <span className="font-semibold text-amber-900">Peringatan: Mode Unggahan File Dinonaktifkan Global</span>
                            <p className="mt-0.5 text-amber-700 leading-relaxed">
                                Setelan aplikasi saat ini menonaktifkan mode unggahan file (<code className="bg-amber-100/80 px-1 py-0.5 rounded font-mono text-[11px]">berkas.unggahan_aktif = false</code>). 
                                Persyaratan wajib dengan hanya mode file ini berpotensi tidak dapat dipenuhi oleh PIC dan akan ditandai <span className="font-semibold">tidak dapat dipenuhi</span> pada alur kerja. Disarankan mengaktifkan mode tautan atau teks.
                            </p>
                        </div>
                    </div>
                )}

                {/* Batasan Teknis File (Hanya relevan jika izinkan_file = true) */}
                <div className={`p-2.5 sm:p-3 rounded-lg border transition-colors ${data.izinkan_file ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-100 opacity-60'}`}>
                    <div className="flex items-center gap-1.5 mb-2">
                        <Settings2 className="w-3.5 h-3.5 text-slate-500" />
                        <span className="text-xs font-semibold text-slate-800">
                            Batasan Teknis File {data.izinkan_file ? '' : '(Nonaktif karena mode file tidak diizinkan)'}
                        </span>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                        <Input
                            id="jb-format"
                            label="Format File Diizinkan"
                            value={data.format_diizinkan}
                            onChange={(e) => onChange('format_diizinkan', e.target.value)}
                            placeholder="Contoh: pdf,docx,xlsx,jpg,png"
                            disabled={isLoading || !data.izinkan_file || !canManageSettings}
                            error={errors.format_diizinkan}
                            helperText={!canManageSettings ? 'Hanya Admin/Superadmin yang dapat mengubah format.' : 'Kosong = default aplikasi'}
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
                        />
                    </div>
                </div>

                {/* Urutan & Keterangan */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5 sm:gap-3">
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
                        />
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
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
