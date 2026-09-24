import React, { useState, useEffect, type FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Switch } from '@/Components/Switch';
import { Badge } from '@/Components/Badge';
import { Input } from '@/Components/Input';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/Components/Table';
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
    expected_version?: number;
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
        expected_version: settings.expected_version ?? 1,
        alasan: '',
    });

    useEffect(() => {
        form.setData({
            berkas_unggahan_aktif: settings.berkas_unggahan_aktif,
            berkas_ukuran_maks_kb: settings.berkas_ukuran_maks_kb,
            berkas_format_diizinkan: settings.berkas_format_diizinkan,
            berkas_tautan_selalu_diizinkan: settings.berkas_tautan_selalu_diizinkan,
            expected_updated_at: settings.expected_updated_at,
            expected_version: settings.expected_version ?? 1,
            alasan: '',
        });
        form.setDefaults({
            berkas_unggahan_aktif: settings.berkas_unggahan_aktif,
            berkas_ukuran_maks_kb: settings.berkas_ukuran_maks_kb,
            berkas_format_diizinkan: settings.berkas_format_diizinkan,
            berkas_tautan_selalu_diizinkan: settings.berkas_tautan_selalu_diizinkan,
            expected_updated_at: settings.expected_updated_at,
            expected_version: settings.expected_version ?? 1,
            alasan: '',
        });
    }, [settings.expected_updated_at, settings.expected_version]);

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
                        expected_version: newSettings.expected_version ?? ((prev.expected_version || 1) + 1),
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
                    <p className="text-sm text-muted">
                        Pengendalian kapasitas penyimpanan VPS dan kebijakan teknis bukti dukung aplikasi SAKIP.
                    </p>

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

                {/* Header Bagian Tabel Distribusi */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold text-ink flex items-center gap-2">
                            <FileText className="h-4 w-4 text-primary" aria-hidden="true" />
                            <span>Distribusi Bukti Dukung per Induk Dokumen SAKIP</span>
                        </h2>
                        <p className="text-xs text-muted mt-0.5">
                            Rincian akumulasi bukti fisik file, tautan, dan teks pada seluruh modul kinerja.
                        </p>
                    </div>
                    <Badge variant="primary">
                        {metrics.total_evidence_count.toLocaleString('id-ID')} Total Bukti
                    </Badge>
                </div>

                {/* Tabel Breakdown per Induk Bukti Dukung */}
                <Card className="overflow-hidden border-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>INDUK DOKUMEN</TableHead>
                                <TableHead className="text-right">BERKAS FILE</TableHead>
                                <TableHead className="text-right">UKURAN DISK</TableHead>
                                <TableHead className="text-right">BUKTI TAUTAN</TableHead>
                                <TableHead className="text-right">BUKTI TEKS</TableHead>
                                <TableHead className="text-right">TOTAL BUKTI</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {Object.values(metrics.by_induk).map((item) => (
                                <TableRow key={item.induk}>
                                    <TableCell className="font-semibold text-ink">
                                        {item.label}
                                    </TableCell>
                                    <TableCell className="text-right font-medium text-ink">
                                        {item.file_count.toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell className="text-right font-mono text-xs text-muted">
                                        {formatBytes(item.file_bytes)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium text-ink">
                                        {item.link_count.toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell className="text-right font-medium text-ink">
                                        {item.text_count.toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell className="text-right font-bold text-ink">
                                        {item.total_count.toLocaleString('id-ID')}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
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
                                            <Badge variant={form.data.berkas_unggahan_aktif ? 'success' : 'warning'}>
                                                {form.data.berkas_unggahan_aktif ? 'Unggahan Aktif' : 'Unggahan Nonaktif'}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted max-w-2xl leading-relaxed">
                                            Mengendalikan penerimaan berkas file fisik di seluruh aplikasi (Regulasi, Rencana
                                            Aksi, Pengukuran, dan Kegiatan). Bila dimatikan, PIC tetap dapat mengirim bukti
                                            dukung berupa tautan dan teks.
                                        </p>
                                    </div>

                                    <Switch
                                        checked={form.data.berkas_unggahan_aktif}
                                        onChange={(checked) => {
                                            if (can.update && !form.processing) {
                                                form.setData('berkas_unggahan_aktif', checked);
                                            }
                                        }}
                                        disabled={!can.update || form.processing}
                                        aria-label={can.update ? 'Toggle saklar unggahan berkas' : 'Status saklar unggahan berkas (Hanya baca)'}
                                    />
                                </div>
                            </div>

                            {/* Field 2: Batas Ukuran Fallback (KB) */}
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Input
                                        id="berkas_ukuran_maks_kb"
                                        label="Batas Ukuran Berkas Default (KB)"
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
                                        error={form.errors.berkas_ukuran_maks_kb}
                                        helperText={`Setara dengan sekitar ${sizeInMB} MB per file. Batas minimal: 100 KB.`}
                                    />
                                </div>

                                {/* Field 3: Format File Diizinkan */}
                                <div className="space-y-2">
                                    <Input
                                        id="berkas_format_diizinkan"
                                        label="Daftar Format File Default (Dipisahkan koma)"
                                        type="text"
                                        disabled={!can.update || form.processing}
                                        value={form.data.berkas_format_diizinkan}
                                        onChange={(e) =>
                                            form.setData('berkas_format_diizinkan', e.target.value)
                                        }
                                        placeholder="pdf,docx,xlsx,jpg,jpeg,png"
                                        error={form.errors.berkas_format_diizinkan}
                                    />
                                    <div className="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span className="text-xs text-muted">Format aktif:</span>
                                        {formatList.map((ext) => (
                                            <Badge
                                                key={ext}
                                                variant="muted"
                                                size="sm"
                                                className="font-mono text-xs"
                                            >
                                                .{ext}
                                            </Badge>
                                        ))}
                                    </div>
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
