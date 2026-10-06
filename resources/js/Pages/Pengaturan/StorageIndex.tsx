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
import { StatCard } from '@/Components/StatCard';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import {
    HardDrive,
    Files,
    Link2,
    FileText,
    AlertTriangle,
    Save,
    Info,
} from 'lucide-react';

export function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

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
    const { setData, setDefaults } = form;
    const {
        berkas_unggahan_aktif,
        berkas_ukuran_maks_kb,
        berkas_format_diizinkan,
        berkas_tautan_selalu_diizinkan,
        expected_updated_at,
        expected_version,
    } = settings;

    useEffect(() => {
        const serverData = {
            berkas_unggahan_aktif,
            berkas_ukuran_maks_kb,
            berkas_format_diizinkan,
            berkas_tautan_selalu_diizinkan,
            expected_updated_at,
            expected_version: expected_version ?? 1,
            alasan: '',
        };
        setData(serverData);
        setDefaults(serverData);
    }, [
        berkas_unggahan_aktif,
        berkas_ukuran_maks_kb,
        berkas_format_diizinkan,
        berkas_tautan_selalu_diizinkan,
        expected_updated_at,
        expected_version,
        setData,
        setDefaults,
    ]);

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
                {/* Header Information (Read-only notice if applicable) */}
                {!can.update && (
                    <div className="flex justify-end">
                        <div className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-soft px-3 py-1.5 text-xs font-medium text-muted">
                            <Info className="h-4 w-4 text-primary" aria-hidden="true" />
                            <span>Mode Pratinjau (Hanya Baca)</span>
                        </div>
                    </div>
                )}

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
                            <p className="mt-1 text-xs text-muted leading-relaxed">
                                Seluruh unggahan berkas fisik ditolak sistem. Penanggung jawab (PIC) dapat memenuhi
                                bukti dukung melalui mode tautan atau teks. Persyaratan yang mewajibkan file akan
                                dikecualikan secara otomatis tanpa menghalangi alur pengajuan maupun pengesahan.
                            </p>
                        </div>
                    </div>
                )}

                {/* Metrik Penggunaan Storage Cards */}
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-base font-semibold text-ink">
                            Penggunaan Penyimpanan Bukti Dukung
                        </h2>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            title="Total Ukuran File"
                            value={formatBytes(metrics.file_total_bytes)}
                            icon={HardDrive}
                            iconVariant="primary"
                            testId="total-bytes-display"
                            description={`Terdistribusi pada ${metrics.file_count.toLocaleString('id-ID')} berkas di private disk`}
                        />
                        <StatCard
                            title="Berkas Fisik (File)"
                            value={metrics.file_count.toLocaleString('id-ID')}
                            icon={Files}
                            iconVariant="muted"
                            testId="file-count-display"
                        />
                        <StatCard
                            title="Bukti Tautan"
                            value={metrics.link_count.toLocaleString('id-ID')}
                            icon={Link2}
                            iconVariant="secondary"
                            testId="link-count-display"
                        />
                        <StatCard
                            title="Bukti Keterangan Teks"
                            value={metrics.text_count.toLocaleString('id-ID')}
                            icon={FileText}
                            iconVariant="success"
                            testId="text-count-display"
                        />
                    </div>
                </div>

                {/* Header Bagian Tabel Distribusi */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h2 className="text-sm font-semibold text-ink">
                        Distribusi Bukti Dukung per Induk Dokumen SAKIP
                    </h2>
                    <Badge variant="primary">
                        {metrics.total_evidence_count.toLocaleString('id-ID')} Total Bukti
                    </Badge>
                </div>

                {/* Tabel Breakdown per Induk Bukti Dukung */}
                <Card className="overflow-hidden border-border bg-surface shadow-xs">
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
                <Card className="border-border bg-surface shadow-xs">
                    <CardHeader className="pb-3 border-b border-border/70">
                        <CardTitle className="flex items-center gap-2 text-sm sm:text-base font-bold text-ink">
                            <HardDrive className="h-4.5 w-4.5 text-primary" aria-hidden="true" />
                            <span>Konfigurasi Kebijakan & Saklar Unggahan</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-5 sm:p-6">
                        <form onSubmit={handleOpenModal} className="space-y-5">
                            {/* Field 1: Saklar Global Unggahan */}
                            <div className="rounded-xl border border-border/80 bg-soft/40 p-4 sm:p-4.5">
                                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div className="flex items-center gap-2.5">
                                        <span className="font-semibold text-ink text-xs sm:text-sm">
                                            Saklar Unggahan Berkas Global
                                        </span>
                                        <Badge variant={form.data.berkas_unggahan_aktif ? 'success' : 'warning'} size="sm">
                                            {form.data.berkas_unggahan_aktif ? 'Unggahan Aktif' : 'Unggahan Nonaktif'}
                                        </Badge>
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

                            {/* Field 2 & 3: Batas Ukuran Fallback (KB) & Format File Diizinkan */}
                            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div className="space-y-1.5">
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

                                <div className="space-y-1.5">
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

                            {/* Submit Actions */}
                            {can.update && (
                                <div className="flex items-center justify-end gap-3 border-t border-border/80 pt-4">
                                    <Button
                                        type="button"
                                        variant="primary"
                                        disabled={form.processing}
                                        className="h-10 px-5 text-xs font-semibold gap-2 shadow-xs"
                                        onClick={() => handleOpenModal()}
                                    >
                                        <Save className="h-4 w-4" aria-hidden="true" />
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
