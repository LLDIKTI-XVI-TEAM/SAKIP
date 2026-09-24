import React, { useState, useEffect, useMemo } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    AppWindow,
    Sliders,
    FileText,
    Save,
    RotateCcw,
    CheckCircle2,
    Clock,
    User,
    Globe,
    Mail,
    Phone,
    MapPin,
    AlertCircle,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Select } from '@/Components/Select';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import type { PengaturanIndexProps } from '@/types/pengaturan';
import type { SharedPageProps } from '@/types/auth';

type TabKey = 'instansi' | 'aplikasi' | 'tampilan' | 'laporan';

interface PengaturanFormData {
    'instansi.nama': string;
    'instansi.alamat': string;
    'instansi.telepon': string;
    'instansi.surel': string;
    'instansi.laman': string;
    'instansi.logo': string;
    'aplikasi.nama': string;
    'aplikasi.label_unit': string;
    'tampilan.zona_waktu': string;
    'tampilan.format_tanggal': string;
    'tampilan.format_angka': string;
    'laporan.header': string;
    'laporan.footer': string;
    alasan: string;
}

export default function PengaturanIndex({ grouped, values }: PengaturanIndexProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const [activeTab, setActiveTab] = useState<TabKey>('instansi');
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [auditReason, setAuditReason] = useState('');
    const [auditError, setAuditError] = useState<string | undefined>();
    const [logoError, setLogoError] = useState(false);

    const buildBaseline = (vals: Record<string, string | null>): Omit<PengaturanFormData, 'alasan'> => ({
        'instansi.nama': vals['instansi.nama'] || '',
        'instansi.alamat': vals['instansi.alamat'] || '',
        'instansi.telepon': vals['instansi.telepon'] || '',
        'instansi.surel': vals['instansi.surel'] || '',
        'instansi.laman': vals['instansi.laman'] || '',
        'instansi.logo': vals['instansi.logo'] || '',
        'aplikasi.nama': vals['aplikasi.nama'] || '',
        'aplikasi.label_unit': vals['aplikasi.label_unit'] || '',
        'tampilan.zona_waktu': vals['tampilan.zona_waktu'] || 'Asia/Makassar',
        'tampilan.format_tanggal': vals['tampilan.format_tanggal'] || 'd F Y',
        'tampilan.format_angka': vals['tampilan.format_angka'] || 'id_ID',
        'laporan.header': vals['laporan.header'] || '',
        'laporan.footer': vals['laporan.footer'] || '',
    });

    const [savedBaseline, setSavedBaseline] = useState<Omit<PengaturanFormData, 'alasan'>>(() => buildBaseline(values));

    const form = useForm<PengaturanFormData>({
        ...savedBaseline,
        alasan: '',
    });

    const formKeys = Object.keys(savedBaseline) as (keyof typeof savedBaseline)[];
    const hasChanges = formKeys.some((key) => form.data[key] !== savedBaseline[key]);

    const updatedTimestamps = useMemo(() => {
        const map: Record<string, string | null> = {};
        Object.values(grouped).flat().forEach((item) => {
            map[item.kunci] = item.updated_at || null;
        });
        return map;
    }, [grouped]);

    useEffect(() => {
        setLogoError(false);
    }, [form.data['instansi.logo']]);

    useEffect(() => {
        const nextBaseline = buildBaseline(values);
        setSavedBaseline(nextBaseline);
        form.setDefaults({
            ...nextBaseline,
            alasan: '',
        });
    }, [values]);

    const updateField = (field: keyof PengaturanFormData, value: string) => {
        form.setData((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const handleSaveClick = (e: React.FormEvent) => {
        e.preventDefault();
        setAuditReason('');
        setAuditError(undefined);
        setIsConfirmOpen(true);
    };

    const handleConfirmSubmit = () => {
        const trimmedReason = auditReason.trim();
        if (trimmedReason.length < 5) {
            setAuditError('Harap berikan alasan pembaruan minimal 5 karakter untuk catatan audit.');
            return;
        }

        const dirtyData: Record<string, unknown> = {
            alasan: trimmedReason,
        };
        const expectedTimestamps: Record<string, string | null> = {};

        formKeys.forEach((key) => {
            if (form.data[key] !== savedBaseline[key]) {
                dirtyData[key] = form.data[key];
                if (updatedTimestamps[key]) {
                    expectedTimestamps[key] = updatedTimestamps[key];
                }
            }
        });

        if (Object.keys(expectedTimestamps).length > 0) {
            dirtyData.expected_updated_at = expectedTimestamps;
        }

        form.transform(() => dirtyData);

        form.put('/pengaturan', {
            preserveScroll: true,
            onSuccess: () => {
                setIsConfirmOpen(false);
                setAuditReason('');
                setSavedBaseline({ ...form.data });
                form.setDefaults({
                    ...form.data,
                    alasan: '',
                });
            },
            onError: () => {
                setIsConfirmOpen(false);
            },
        });
    };

    const handleReset = () => {
        form.setData({
            ...savedBaseline,
            alasan: '',
        });
        form.clearErrors();
    };

    // Calculate metadata summary for current tab
    const currentGroupItems = grouped[activeTab] || [];
    const latestUpdateItem = currentGroupItems
        .filter((item) => item.updated_at)
        .sort((a, b) => (b.updated_at && a.updated_at ? b.updated_at.localeCompare(a.updated_at) : 0))[0];

    const tabs: { key: TabKey; label: string; icon: React.ComponentType<{ className?: string }> }[] = [
        { key: 'instansi', label: 'Identitas Instansi', icon: Building2 },
        { key: 'aplikasi', label: 'Identitas Aplikasi', icon: AppWindow },
        { key: 'tampilan', label: 'Preferensi Tampilan', icon: Sliders },
        { key: 'laporan', label: 'Format Laporan', icon: FileText },
    ];

    return (
        <AuthenticatedLayout
            title="Pengaturan Sistem"
            breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Pengaturan' }]}
        >
            <Head title="Pengaturan Sistem" />

            <div className="space-y-5">
                {/* Flash Success Notification */}
                {flash?.success && (
                    <div className="flex items-center gap-3 p-4 rounded-xl bg-success/10 border border-success/20 text-success-dark">
                        <CheckCircle2 className="w-5 h-5 shrink-0 text-success" />
                        <span className="text-sm font-medium">{flash.success}</span>
                    </div>
                )}

                {/* Form General Error Notification */}
                {Object.keys(form.errors).length > 0 && (
                    <div className="flex items-start gap-3 p-4 rounded-xl bg-danger/10 border border-danger/20 text-danger-dark">
                        <AlertCircle className="w-5 h-5 shrink-0 text-danger mt-0.5" />
                        <div className="text-sm">
                            <p className="font-semibold">Terdapat kesalahan pengisian data pengaturan:</p>
                            <ul className="mt-1 list-disc list-inside space-y-0.5">
                                {Object.entries(form.errors).map(([key, err]) => (
                                    <li key={key}>{err}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Tab Navigation & Status Info */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-border gap-2 pb-px">
                    <div className="flex overflow-x-auto no-scrollbar gap-1 sm:gap-2">
                        {tabs.map((tab) => {
                            const Icon = tab.icon;
                            const isActive = activeTab === tab.key;
                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-all whitespace-nowrap ${
                                        isActive
                                            ? 'border-primary text-primary bg-primary/5 rounded-t-lg'
                                            : 'border-transparent text-muted hover:text-ink hover:border-border'
                                    }`}
                                >
                                    <Icon className={`w-4 h-4 ${isActive ? 'text-primary' : 'text-muted'}`} />
                                    <span>{tab.label}</span>
                                </button>
                            );
                        })}
                    </div>

                    {(hasChanges || latestUpdateItem?.updated_at) && (
                        <div className="flex items-center gap-2 pb-1.5 sm:pb-0 shrink-0">
                            {hasChanges && (
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-warning/15 text-warning-dark border border-warning/30">
                                    <span className="w-1.5 h-1.5 rounded-full bg-warning" aria-hidden="true" />
                                    Ada Perubahan Belum Disimpan
                                </span>
                            )}
                            {latestUpdateItem?.updated_at && (
                                <div className="hidden lg:flex items-center gap-1.5 text-xs text-muted bg-surface px-2.5 py-1 rounded-lg border border-border shadow-2xs">
                                    <Clock className="w-3.5 h-3.5 text-primary" />
                                    <span>Terakhir diubah: {new Date(latestUpdateItem.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                    {latestUpdateItem.updated_by && (
                                        <>
                                            <span className="text-border">|</span>
                                            <User className="w-3.5 h-3.5 text-primary" />
                                            <span>{latestUpdateItem.updated_by.nama}</span>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <form onSubmit={handleSaveClick}>
                    {/* Tab: Identitas Instansi */}
                    {activeTab === 'instansi' && (
                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <div className="lg:col-span-7 space-y-5">
                                <Card className="p-6">
                                    <div className="mb-4 pb-3 border-b border-border">
                                        <h2 className="text-base font-semibold text-ink">Informasi Lembaga</h2>
                                        <p className="text-xs text-muted">Identitas instansi yang digunakan pada kop dan dokumen resmi.</p>
                                    </div>

                                    <div className="space-y-4">
                                        <Input
                                            id="instansi.nama"
                                            name="instansi.nama"
                                            label="Nama Instansi"
                                            required
                                            value={form.data['instansi.nama']}
                                            onChange={(e) => updateField('instansi.nama', e.target.value)}
                                            error={form.errors['instansi.nama']}
                                            helperText="Nama lengkap lembaga atau institusi pemerintah."
                                        />

                                        <Textarea
                                            id="instansi.alamat"
                                            name="instansi.alamat"
                                            label="Alamat Kantor"
                                            rows={3}
                                            value={form.data['instansi.alamat']}
                                            onChange={(e) => updateField('instansi.alamat', e.target.value)}
                                            error={form.errors['instansi.alamat']}
                                            helperText="Alamat domisili operasional kantor pusat instansi."
                                        />

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <Input
                                                id="instansi.telepon"
                                                name="instansi.telepon"
                                                label="Nomor Telepon"
                                                value={form.data['instansi.telepon']}
                                                onChange={(e) => updateField('instansi.telepon', e.target.value)}
                                                error={form.errors['instansi.telepon']}
                                                placeholder="(0435) 821123"
                                            />

                                            <Input
                                                id="instansi.surel"
                                                name="instansi.surel"
                                                label="Surel / Email Resmi"
                                                type="email"
                                                value={form.data['instansi.surel']}
                                                onChange={(e) => updateField('instansi.surel', e.target.value)}
                                                error={form.errors['instansi.surel']}
                                                placeholder="lldikti16@kemdikbud.go.id"
                                            />
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <Input
                                                id="instansi.laman"
                                                name="instansi.laman"
                                                label="Laman / Website Resmi"
                                                type="url"
                                                value={form.data['instansi.laman']}
                                                onChange={(e) => updateField('instansi.laman', e.target.value)}
                                                error={form.errors['instansi.laman']}
                                                placeholder="https://lldikti16.kemdikbud.go.id"
                                            />

                                            <Input
                                                id="instansi.logo"
                                                name="instansi.logo"
                                                label="Path / URL Logo"
                                                value={form.data['instansi.logo']}
                                                onChange={(e) => updateField('instansi.logo', e.target.value)}
                                                error={form.errors['instansi.logo']}
                                                placeholder="/img/dikti16-favicon-blue-150x150.png"
                                                helperText="Jalur aset logo instansi untuk kop surat."
                                            />
                                        </div>
                                    </div>
                                </Card>
                            </div>

                            {/* Live Preview Card */}
                            <div className="lg:col-span-5">
                                <Card className="p-6 bg-gradient-to-br from-surface to-soft border-border sticky top-6">
                                    <div className="mb-4 pb-3 border-b border-border">
                                        <h3 className="text-sm font-semibold text-ink">Pratinjau Identitas</h3>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="flex items-center gap-4 p-4 rounded-xl bg-surface border border-border shadow-xs">
                                            <div className="w-14 h-14 rounded-xl bg-soft border border-border flex items-center justify-center p-2 shrink-0">
                                                {form.data['instansi.logo'] && !logoError ? (
                                                    <img
                                                        src={form.data['instansi.logo']}
                                                        alt={`Logo ${form.data['instansi.nama'] || 'Instansi'}`}
                                                        className="max-w-full max-h-full object-contain"
                                                        onError={() => setLogoError(true)}
                                                        onLoad={() => setLogoError(false)}
                                                    />
                                                ) : (
                                                    <Building2 className="w-6 h-6 text-muted" />
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <h4 className="text-sm font-bold text-ink truncate font-sans">
                                                    {form.data['instansi.nama'] || 'Nama Instansi'}
                                                </h4>
                                                <p className="text-xs text-muted truncate">
                                                    {form.data['aplikasi.nama'] || 'SAKIP LLDIKTI XVI'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="space-y-2 text-xs text-ink/80 bg-surface/70 p-4 rounded-xl border border-border">
                                            <div className="flex items-start gap-2">
                                                <MapPin className="w-4 h-4 text-primary shrink-0 mt-0.5" />
                                                <span>{form.data['instansi.alamat'] || 'Alamat instansi belum diatur.'}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Phone className="w-4 h-4 text-primary shrink-0" />
                                                <span>{form.data['instansi.telepon'] || '-'}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Mail className="w-4 h-4 text-primary shrink-0" />
                                                <span>{form.data['instansi.surel'] || '-'}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Globe className="w-4 h-4 text-primary shrink-0" />
                                                <span>{form.data['instansi.laman'] || '-'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>
                    )}

                    {/* Tab: Identitas Aplikasi */}
                    {activeTab === 'aplikasi' && (
                        <div className="max-w-3xl space-y-5">
                            <Card className="p-6">
                                <div className="mb-4 pb-3 border-b border-border">
                                    <h2 className="text-base font-semibold text-ink">Branding & Penamaan Aplikasi</h2>
                                    <p className="text-xs text-muted">Konfigurasi nama sistem dan nomenklatur unit organisasi.</p>
                                </div>

                                <div className="space-y-4">
                                    <Input
                                        id="aplikasi.nama"
                                        name="aplikasi.nama"
                                        label="Nama Aplikasi"
                                        required
                                        value={form.data['aplikasi.nama']}
                                        onChange={(e) => updateField('aplikasi.nama', e.target.value)}
                                        error={form.errors['aplikasi.nama']}
                                        helperText="Nama yang tampil pada tajuk halaman dan tab peramban."
                                    />

                                    <Input
                                        id="aplikasi.label_unit"
                                        name="aplikasi.label_unit"
                                        label="Label Nomenklatur Unit Kerja"
                                        required
                                        value={form.data['aplikasi.label_unit']}
                                        onChange={(e) => updateField('aplikasi.label_unit', e.target.value)}
                                        error={form.errors['aplikasi.label_unit']}
                                        helperText="Sebutan tingkat organisasi (contoh: Unit Kerja, Satuan Kerja, Divisi, atau Biro)."
                                    />
                                </div>
                            </Card>
                        </div>
                    )}

                    {/* Tab: Preferensi Tampilan */}
                    {activeTab === 'tampilan' && (
                        <div className="max-w-3xl space-y-5">
                            <Card className="p-6">
                                <div className="mb-4 pb-3 border-b border-border">
                                    <h2 className="text-base font-semibold text-ink">Preferensi Format & Regional</h2>
                                    <p className="text-xs text-muted">Penyesuaian zona waktu, format penanggalan, dan pemformatan angka.</p>
                                </div>

                                <div className="space-y-4">
                                    <Select
                                        id="tampilan.zona_waktu"
                                        name="tampilan.zona_waktu"
                                        label="Zona Waktu Standar"
                                        required
                                        value={form.data['tampilan.zona_waktu']}
                                        onChange={(e) => updateField('tampilan.zona_waktu', e.target.value)}
                                        error={form.errors['tampilan.zona_waktu']}
                                        helperText="Standar zona waktu yang digunakan untuk pencatatan timestamp dan pelaporan."
                                    >
                                        <option value="Asia/Jakarta">WIB - Waktu Indonesia Barat (Asia/Jakarta)</option>
                                        <option value="Asia/Makassar">WITA - Waktu Indonesia Tengah (Asia/Makassar)</option>
                                        <option value="Asia/Jayapura">WIT - Waktu Indonesia Timur (Asia/Jayapura)</option>
                                        <option value="UTC">UTC - Universal Coordinated Time</option>
                                    </Select>

                                    <Select
                                        id="tampilan.format_tanggal"
                                        name="tampilan.format_tanggal"
                                        label="Format Tanggal Standar"
                                        required
                                        value={form.data['tampilan.format_tanggal']}
                                        onChange={(e) => updateField('tampilan.format_tanggal', e.target.value)}
                                        error={form.errors['tampilan.format_tanggal']}
                                        helperText="Format visual tanggal pada tabel dan halaman detail."
                                    >
                                        <option value="d F Y">22 September 2026 (d F Y)</option>
                                        <option value="d/m/Y">22/09/2026 (d/m/Y)</option>
                                        <option value="Y-m-d">2026-09-22 (Y-m-d - ISO)</option>
                                    </Select>

                                    <Select
                                        id="tampilan.format_angka"
                                        name="tampilan.format_angka"
                                        label="Format Pemisah Angka & Desimal"
                                        required
                                        value={form.data['tampilan.format_angka']}
                                        onChange={(e) => updateField('tampilan.format_angka', e.target.value)}
                                        error={form.errors['tampilan.format_angka']}
                                        helperText="Pemisah ribuan dan desimal untuk capaian target kinerja."
                                    >
                                        <option value="id_ID">Indonesia (1.234.567,89 - id_ID)</option>
                                        <option value="en_US">Internasional (1,234,567.89 - en_US)</option>
                                    </Select>
                                </div>
                            </Card>
                        </div>
                    )}

                    {/* Tab: Format Laporan */}
                    {activeTab === 'laporan' && (
                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <div className="lg:col-span-7 space-y-5">
                                <Card className="p-6">
                                    <div className="mb-4 pb-3 border-b border-border">
                                        <h2 className="text-base font-semibold text-ink">Header & Footer Laporan Resmi</h2>
                                        <p className="text-xs text-muted">Teks baku yang dicetak di bagian atas (kop) dan bawah lembar laporan cetak.</p>
                                    </div>

                                    <div className="space-y-4">
                                        <Textarea
                                            id="laporan.header"
                                            name="laporan.header"
                                            label="Header / Kop Laporan"
                                            rows={4}
                                            value={form.data['laporan.header']}
                                            onChange={(e) => updateField('laporan.header', e.target.value)}
                                            error={form.errors['laporan.header']}
                                            helperText="Dapat terdiri dari beberapa baris kementerian dan lembaga."
                                        />

                                        <Textarea
                                            id="laporan.footer"
                                            name="laporan.footer"
                                            label="Footer / Catatan Kaki Laporan"
                                            rows={3}
                                            value={form.data['laporan.footer']}
                                            onChange={(e) => updateField('laporan.footer', e.target.value)}
                                            error={form.errors['laporan.footer']}
                                            helperText="Teks keterangan legalitas atau sumber sistem pada bagian bawah cetakan."
                                        />
                                    </div>
                                </Card>
                            </div>

                            {/* Live Document Preview */}
                            <div className="lg:col-span-5">
                                <Card className="p-6 bg-surface border-border shadow-md sticky top-6">
                                    <div className="mb-4 pb-2 border-b border-border flex items-center justify-between">
                                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted">Simulasi Halaman Cetak</h3>
                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-soft text-muted border border-border">Dokumen PDF</span>
                                    </div>

                                    <div className="p-5 border border-border/80 rounded-lg bg-surface text-center space-y-6 min-h-[300px] flex flex-col justify-between shadow-2xs">
                                        {/* Header */}
                                        <div className="border-b-2 border-ink pb-3 whitespace-pre-line text-xs font-bold uppercase tracking-tight text-ink">
                                            {form.data['laporan.header'] || 'KOP DOKUMEN LAPORAN'}
                                        </div>

                                        {/* Mock Body */}
                                        <div className="text-left text-xs text-muted/60 space-y-2 py-4">
                                            <div className="h-3 bg-soft rounded-sm w-3/4 mx-auto" />
                                            <div className="h-2.5 bg-soft rounded-sm w-full" />
                                            <div className="h-2.5 bg-soft rounded-sm w-5/6" />
                                            <div className="h-2.5 bg-soft rounded-sm w-2/3" />
                                        </div>

                                        {/* Footer */}
                                        <div className="border-t border-border pt-3 text-[11px] text-muted italic">
                                            {form.data['laporan.footer'] || 'Catatan kaki laporan dicetak di sini.'}
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>
                    )}

                    {/* Actions Bar */}
                    <div className="mt-8 pt-4 border-t border-border flex flex-wrap items-center justify-between gap-4">
                        <div className="text-xs text-muted">
                            Semua pembaruan tercatat dalam riwayat audit (audit trail) sistem.
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleReset}
                                disabled={!hasChanges || form.processing}
                                className="flex items-center gap-1.5"
                            >
                                <RotateCcw className="w-4 h-4" />
                                <span>Kembalikan</span>
                            </Button>

                            <Button
                                type="submit"
                                variant="primary"
                                disabled={!hasChanges || form.processing}
                                className="flex items-center gap-1.5"
                            >
                                <Save className="w-4 h-4" />
                                <span>{form.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}</span>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>

            {/* Modal Konfirmasi Catatan Audit */}
            <AuditReasonModal
                open={isConfirmOpen}
                title="Konfirmasi Perubahan Pengaturan"
                description="Perubahan pengaturan sistem bersifat sensitif. Mohon masukkan justifikasi atau dasar perubahan untuk dicatat dalam audit trail."
                reason={auditReason}
                error={auditError}
                busy={form.processing}
                confirmLabel="Konfirmasi & Simpan"
                onReasonChange={setAuditReason}
                onClose={() => setIsConfirmOpen(false)}
                onConfirm={handleConfirmSubmit}
            />
        </AuthenticatedLayout>
    );
}
