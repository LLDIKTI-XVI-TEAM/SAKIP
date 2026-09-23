import React, { useState, useEffect, type FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import {
    HardDrive,
    Files,
    Link2,
    FileText,
    AlertTriangle,
    CheckCircle2,
    Save,
    Info,
    ShieldAlert,
    Database,
    HelpCircle,
} from 'lucide-react';

export interface StorageSettings {
    berkas_unggahan_aktif: boolean;
    berkas_ukuran_maks_kb: number;
    berkas_format_diizinkan: string;
    berkas_tautan_selalu_diizinkan: boolean;
    expected_updated_at: string;
}

export interface IndukMetric {
    induk: string;
    label: string;
    file_count: number;
    file_bytes: number;
    link_count: number;
    text_count: number;
    total_count: number;
}

export interface StorageMetrics {
    file_count: number;
    file_total_bytes: number;
    link_count: number;
    text_count: number;
    total_evidence_count: number;
    by_induk: Record<string, IndukMetric>;
}

export interface StorageIndexProps {
    settings: StorageSettings;
    metrics: StorageMetrics;
    can: {
        update: boolean;
    };
}

export function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

export default function StorageIndex({ settings, metrics, can }: StorageIndexProps) {
    const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
    const [auditReason, setAuditReason] = useState('');
    const [auditError, setAuditError] = useState('');

    const form = useForm({
        berkas_unggahan_aktif: settings.berkas_unggahan_aktif,
        berkas_ukuran_maks_kb: settings.berkas_ukuran_maks_kb,
        berkas_format_diizinkan: settings.berkas_format_diizinkan,
        berkas_tautan_selalu_diizinkan: settings.berkas_tautan_selalu_diizinkan,
        expected_updated_at: settings.expected_updated_at,
        alasan: '',
    });

    useEffect(() => {
        form.setData({
            berkas_unggahan_aktif: settings.berkas_unggahan_aktif,
            berkas_ukuran_maks_kb: settings.berkas_ukuran_maks_kb,
            berkas_format_diizinkan: settings.berkas_format_diizinkan,
            berkas_tautan_selalu_diizinkan: settings.berkas_tautan_selalu_diizinkan,
            expected_updated_at: settings.expected_updated_at,
            alasan: '',
        });
        form.setDefaults({
            berkas_unggahan_aktif: settings.berkas_unggahan_aktif,
            berkas_ukuran_maks_kb: settings.berkas_ukuran_maks_kb,
            berkas_format_diizinkan: settings.berkas_format_diizinkan,
            berkas_tautan_selalu_diizinkan: settings.berkas_tautan_selalu_diizinkan,
            expected_updated_at: settings.expected_updated_at,
            alasan: '',
        });
    }, [settings.expected_updated_at]);

    const handleOpenModal = (e?: FormEvent) => {
        if (e) {
            e.preventDefault();
        }
        setAuditError('');
        setIsAuditModalOpen(true);
    };

    const handleConfirmSave = () => {
        const trimmed = auditReason.trim();
        if (trimmed.length < 10) {
            setAuditError('Alasan audit wajib diisi minimal 10 karakter.');
            return;
        }

        form.transform((data) => ({
            ...data,
            alasan: trimmed,
        }));

        form.put('/pengaturan/storage', {
            onSuccess: (page) => {
                setIsAuditModalOpen(false);
                setAuditReason('');
                setAuditError('');
                const newSettings = (page.props as unknown as StorageIndexProps)?.settings;
                if (newSettings?.expected_updated_at) {
                    form.setData((prev) => ({
                        ...prev,
                        expected_updated_at: newSettings.expected_updated_at,
                        alasan: '',
                    }));
                }
            },
            onError: (errors: Record<string, string>) => {
                if (errors.alasan) {
                    setAuditError(errors.alasan);
                } else {
                    const firstError = Object.values(errors)[0];
                    if (firstError) {
                        setAuditError(firstError);
                    }
                }
            },
        });
    };

    const formatList = form.data.berkas_format_diizinkan
        .split(',')
        .map((f) => f.trim().toLowerCase())
        .filter((f) => f.length > 0);

    const sizeInMB = (form.data.berkas_ukuran_maks_kb / 1024).toFixed(1);

    return (
        <AuthenticatedLayout
            title="Kebijakan Storage & Saklar Unggah Berkas"
            breadcrumbs={[{ label: 'Pengaturan' }, { label: 'Kebijakan Storage' }]}
        >
            <Head title="Kebijakan Storage & Saklar Unggah Berkas" />

            <div className="space-y-6">
                {/* Header Information */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-ink sm:text-2xl">
                            Kebijakan Storage & Saklar Unggahan
                        </h1>
                        <p className="mt-1 text-sm text-muted">
                            Pengendalian kapasitas penyimpanan VPS dan kebijakan teknis bukti dukung aplikasi SAKIP.
                        </p>
                    </div>

                    {!can.update && (
                        <div className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-soft px-3 py-1.5 text-xs font-medium text-muted">
                            <Info className="h-4 w-4 text-primary" aria-hidden="true" />
                            <span>Mode Pratinjau (Hanya Baca)</span>
                        </div>
                    )}
                </div>

                {/* Status Switch Notice if disabled */}
                {!form.data.berkas_unggahan_aktif && (
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 text-ink"
                    >
                        <AlertTriangle className="h-5 w-5 shrink-0 text-warning-dark mt-0.5" aria-hidden="true" />
                        <div className="text-sm">
                            <p className="font-semibold text-warning-dark">
                                Saklar Unggahan File Global Sedang Dinonaktifkan
                            </p>
                            <p className="mt-1 text-muted">
                                Seluruh unggahan berkas fisik ditolak sistem. Penanggung jawab (PIC) hanya dapat memenuhi
                                bukti dukung melalui mode tautan atau teks. Persyaratan yang mewajibkan file akan
                                dikecualikan secara otomatis tanpa menghalangi alur pengajuan maupun pengesahan.
                            </p>
                        </div>
                    </div>
                )}

                {/* Metrik Penggunaan Storage Cards */}
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-semibold text-ink flex items-center gap-2">
                            <Database className="h-4 w-4 text-primary" aria-hidden="true" />
                            Penggunaan Penyimpanan Bukti Dukung (Aktif)
                        </h2>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Card 1: Total Ukuran File */}
                        <Card className="border-border">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium uppercase tracking-wider text-muted">
                                        Total Ukuran File
                                    </span>
                                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <HardDrive className="h-5 w-5" aria-hidden="true" />
                                    </span>
                                </div>
                                <p className="mt-3 text-2xl font-bold text-ink" data-testid="total-bytes-display">
                                    {formatBytes(metrics.file_total_bytes)}
                                </p>
                                <p className="mt-1 text-xs text-muted">
                                    Terdistribusi pada {metrics.file_count.toLocaleString('id-ID')} berkas di private disk
                                </p>
                            </CardContent>
                        </Card>

                        {/* Card 2: Jumlah Berkas Fisik */}
                        <Card className="border-border">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium uppercase tracking-wider text-muted">
                                        Berkas Fisik (File)
                                    </span>
                                    <span className="rounded-lg bg-soft p-2 text-ink">
                                        <Files className="h-5 w-5" aria-hidden="true" />
                                    </span>
                                </div>
                                <p className="mt-3 text-2xl font-bold text-ink" data-testid="file-count-display">
                                    {metrics.file_count.toLocaleString('id-ID')}
                                </p>
                                <p className="mt-1 text-xs text-muted">
                                    Dokumen fisik yang tersimpan di disk VPS
                                </p>
                            </CardContent>
                        </Card>

                        {/* Card 3: Bukti Tautan */}
                        <Card className="border-border">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium uppercase tracking-wider text-muted">
                                        Bukti Tautan
                                    </span>
                                    <span className="rounded-lg bg-secondary/15 p-2 text-ink">
                                        <Link2 className="h-5 w-5" aria-hidden="true" />
                                    </span>
                                </div>
                                <p className="mt-3 text-2xl font-bold text-ink" data-testid="link-count-display">
                                    {metrics.link_count.toLocaleString('id-ID')}
                                </p>
                                <p className="mt-1 text-xs text-muted">
                                    Tautan eksternal (0 B penggunaan disk)
                                </p>
                            </CardContent>
                        </Card>

                        {/* Card 4: Bukti Teks */}
                        <Card className="border-border">
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium uppercase tracking-wider text-muted">
                                        Bukti Keterangan Teks
                                    </span>
                                    <span className="rounded-lg bg-success/15 p-2 text-success">
                                        <FileText className="h-5 w-5" aria-hidden="true" />
                                    </span>
                                </div>
                                <p className="mt-3 text-2xl font-bold text-ink" data-testid="text-count-display">
                                    {metrics.text_count.toLocaleString('id-ID')}
                                </p>
                                <p className="mt-1 text-xs text-muted">
                                    Keterangan tertulis (0 B penggunaan disk)
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Tabel Breakdown per Induk Bukti Dukung */}
                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <span>Rincian Penggunaan Berkas Berdasarkan Induk Dokumen</span>
                            <span className="rounded-full bg-soft px-2.5 py-0.5 text-xs font-normal text-muted">
                                {metrics.total_evidence_count.toLocaleString('id-ID')} Total Bukti
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-ink">
                            <thead className="border-b border-border bg-soft text-xs font-semibold uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" className="px-6 py-3">Induk Dokumen</th>
                                    <th scope="col" className="px-6 py-3 text-right">Berkas File</th>
                                    <th scope="col" className="px-6 py-3 text-right">Ukuran Disk</th>
                                    <th scope="col" className="px-6 py-3 text-right">Bukti Tautan</th>
                                    <th scope="col" className="px-6 py-3 text-right">Bukti Teks</th>
                                    <th scope="col" className="px-6 py-3 text-right font-bold">Total Bukti</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {Object.values(metrics.by_induk).map((item) => (
                                    <tr key={item.induk} className="hover:bg-soft/40 transition-colors">
                                        <td className="px-6 py-4 font-medium text-ink">
                                            {item.label}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            {item.file_count.toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-xs text-muted">
                                            {formatBytes(item.file_bytes)}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            {item.link_count.toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            {item.text_count.toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-6 py-4 text-right font-semibold text-ink">
                                            {item.total_count.toLocaleString('id-ID')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* Form Kebijakan Storage */}
                <Card className="border-border">
                    <CardHeader>
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <HardDrive className="h-5 w-5 text-primary" aria-hidden="true" />
                                <span>Konfigurasi Kebijakan & Saklar Unggahan</span>
                            </CardTitle>
                            <p className="mt-1 text-xs text-muted">
                                Nilai default ini berfungsi sebagai fallback teknis aplikasi bila batas spesifik pada
                                persyaratan berkas (Perencanaan) tidak ditentukan.
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent className="p-6">
                        <form onSubmit={handleOpenModal} className="space-y-6">
                            {/* Field 1: Saklar Global Unggahan */}
                            <div className="rounded-xl border border-border bg-soft/50 p-4 sm:p-5">
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-ink text-sm sm:text-base">
                                                Saklar Unggahan Berkas Global
                                            </span>
                                            <span
                                                className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                                    form.data.berkas_unggahan_aktif
                                                        ? 'bg-success/10 text-success border border-success/30'
                                                        : 'bg-warning/10 text-warning-dark border border-warning/30'
                                                }`}
                                            >
                                                {form.data.berkas_unggahan_aktif ? 'Unggahan Aktif' : 'Unggahan Nonaktif'}
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted max-w-2xl leading-relaxed">
                                            Mengendalikan penerimaan berkas file fisik di seluruh aplikasi (Regulasi, Rencana
                                            Aksi, Pengukuran, dan Kegiatan). Bila dimatikan, PIC tetap dapat mengirim bukti
                                            dukung berupa tautan dan teks.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={form.data.berkas_unggahan_aktif}
                                        aria-label={can.update ? 'Toggle saklar unggahan berkas' : 'Status saklar unggahan berkas (Hanya baca)'}
                                        disabled={!can.update || form.processing}
                                        onClick={() => {
                                            if (can.update && !form.processing) {
                                                form.setData(
                                                    'berkas_unggahan_aktif',
                                                    !form.data.berkas_unggahan_aktif
                                                );
                                            }
                                        }}
                                        className={`relative inline-flex h-7 w-12 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
                                            can.update ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'
                                        } ${
                                            form.data.berkas_unggahan_aktif ? 'bg-primary' : 'bg-border'
                                        }`}
                                    >
                                        <span
                                            className={`pointer-events-none inline-block h-6 w-6 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out ${
                                                form.data.berkas_unggahan_aktif
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0'
                                            }`}
                                        />
                                    </button>
                                </div>
                            </div>

                            {/* Field 2: Batas Ukuran Fallback (KB) */}
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label
                                        htmlFor="berkas_ukuran_maks_kb"
                                        className="block text-sm font-semibold text-ink"
                                    >
                                        Batas Ukuran Berkas Default (KB)
                                    </label>
                                    <div className="relative">
                                        <input
                                            id="berkas_ukuran_maks_kb"
                                            type="number"
                                            min={100}
                                            max={102400}
                                            step={100}
                                            disabled={!can.update || form.processing}
                                            value={form.data.berkas_ukuran_maks_kb}
                                            onChange={(e) =>
                                                form.setData(
                                                    'berkas_ukuran_maks_kb',
                                                    parseInt(e.target.value, 10) || 0
                                                )
                                            }
                                            className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink placeholder-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-soft"
                                        />
                                        <span className="absolute right-3 top-2 text-xs font-medium text-muted">
                                            KB
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted flex items-center justify-between">
                                        <span>Setara dengan sekitar <strong className="text-ink">{sizeInMB} MB</strong> per file.</span>
                                        <span>Batas minimal: 100 KB</span>
                                    </p>
                                    {form.errors.berkas_ukuran_maks_kb && (
                                        <p className="text-xs text-danger" role="alert">
                                            {form.errors.berkas_ukuran_maks_kb}
                                        </p>
                                    )}
                                </div>

                                {/* Field 3: Format File Diizinkan */}
                                <div className="space-y-2">
                                    <label
                                        htmlFor="berkas_format_diizinkan"
                                        className="block text-sm font-semibold text-ink"
                                    >
                                        Daftar Format File Default (Dipisahkan koma)
                                    </label>
                                    <input
                                        id="berkas_format_diizinkan"
                                        type="text"
                                        disabled={!can.update || form.processing}
                                        value={form.data.berkas_format_diizinkan}
                                        onChange={(e) =>
                                            form.setData('berkas_format_diizinkan', e.target.value)
                                        }
                                        placeholder="pdf,docx,xlsx,jpg,jpeg,png"
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink placeholder-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-soft"
                                    />
                                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span className="text-xs text-muted">Format aktif:</span>
                                        {formatList.map((ext) => (
                                            <span
                                                key={ext}
                                                className="inline-flex items-center rounded bg-soft px-1.5 py-0.5 font-mono text-xs font-medium text-ink border border-border"
                                            >
                                                .{ext}
                                            </span>
                                        ))}
                                    </div>
                                    {form.errors.berkas_format_diizinkan && (
                                        <p className="text-xs text-danger" role="alert">
                                            {form.errors.berkas_format_diizinkan}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Field 4: Status Tautan Selalu Diizinkan */}
                            <div className="rounded-xl border border-border bg-surface p-4">
                                <div className="flex items-start gap-3">
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary shrink-0 mt-0.5">
                                        <CheckCircle2 className="h-5 w-5" aria-hidden="true" />
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-sm font-semibold text-ink">
                                            Mode Tautan dan Teks Selalu Tersedia Sebagai Alternatif Bebas Storage
                                        </p>
                                        <p className="text-xs text-muted leading-relaxed">
                                            Sesuai ketentuan PRD §18.9, ketersediaan mode tautan (link dokumen eksternal)
                                            dan teks selalu aktif sebagai katup pengaman anti-macet. Pengguna tidak akan
                                            terhambat memenuhi kewajiban kinerja saat kapasitas storage mendekati ambang batas.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Submit Actions */}
                            {can.update && (
                                <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                                    <Button
                                        type="button"
                                        variant="primary"
                                        disabled={form.processing}
                                        className="min-h-[44px] px-6 text-sm font-medium"
                                        onClick={() => handleOpenModal()}
                                    >
                                        <Save className="h-4 w-4 mr-2" aria-hidden="true" />
                                        Simpan Kebijakan Storage
                                    </Button>
                                </div>
                            )}
                        </form>
                    </CardContent>
                </Card>
            </div>

            {/* Modal Alasan Audit (Wajib untuk Aksi Sensitif) */}
            <AuditReasonModal
                open={isAuditModalOpen}
                title="Konfirmasi Perubahan Kebijakan Storage"
                description="Perubahan kebijakan teknis storage dan saklar unggahan bersifat sensitif dan akan dicatat secara permanen pada jejak audit aplikasi."
                reason={auditReason}
                error={auditError}
                busy={form.processing}
                confirmLabel="Simpan & Catat Audit"
                onReasonChange={setAuditReason}
                onClose={() => {
                    setIsAuditModalOpen(false);
                    setAuditReason('');
                    setAuditError('');
                }}
                onConfirm={handleConfirmSave}
            />
        </AuthenticatedLayout>
    );
}
