import React, { useMemo } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { 
    Calculator, 
    UploadCloud, 
    Link as LinkIcon, 
    FileText, 
    AlertTriangle, 
    CheckCircle2, 
    ArrowLeft,
    Send,
    Save,
    ExternalLink,
    Clock
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';

interface PengukuranEditProps {
    pengukuran: any;
}

export default function PengukuranEdit({ pengukuran }: PengukuranEditProps) {
    const iku = pengukuran.penugasan_indikator?.indikator_kinerja;
    const isLocked = !['draft', 'dikembalikan'].includes(pengukuran.status);

    const { data, setData, post, processing, errors } = useForm({
        realisasi: pengukuran.realisasi !== null ? String(pengukuran.realisasi) : '',
        kendala: pengukuran.kendala || '',
        tindak_lanjut: pengukuran.tindak_lanjut || '',
        strategi: pengukuran.strategi || '',
        file_bukti: null as File | null,
        url_bukti: '',
        keterangan_bukti: '',
        action: 'draft',
    });

    // Real-time live calculation in React
    const calculatedCapaian = useMemo(() => {
        if (!data.realisasi || isNaN(Number(data.realisasi))) {
            return null;
        }

        const realisasi = parseFloat(data.realisasi);
        const target = parseFloat(pengukuran.target);

        if (target <= 0) {
            return realisasi > 0 ? 100 : 0;
        }

        if (iku?.tipe_perhitungan === 'turun_baik') {
            const res = ((2 * target - realisasi) / target) * 100;
            return Math.max(0, Math.round(res * 100) / 100);
        } else {
            const res = (realisasi / target) * 100;
            return Math.max(0, Math.round(res * 100) / 100);
        }
    }, [data.realisasi, pengukuran.target, iku?.tipe_perhitungan]);

    const isUnderperforming = calculatedCapaian !== null && calculatedCapaian < 100.0;

    const handleSubmit = (actionType: 'draft' | 'ajukan') => {
        data.action = actionType;
        post(`/pengukuran/${pengukuran.id}`, {
            preserveScroll: true,
        });
    };

    // Find if there is a rejection note from previous review
    const lastRejection = pengukuran.riwayats?.find((r: any) => r.status_ke === 'dikembalikan');

    return (
        <AuthenticatedLayout
            title="Form Pengisian Realisasi Kinerja"
            breadcrumbs={[
                { label: 'Pengukuran Kinerja', href: '/pengukuran' },
                { label: `Pengisian ${iku?.kode}` },
            ]}
        >
            <Head title={`Pengisian ${iku?.kode}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Back button */}
                <div className="flex items-center justify-between">
                    <Link
                        href="/pengukuran"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Kembali ke Daftar Pengukuran
                    </Link>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-slate-500">Status Saat Ini:</span>
                        <Badge status={pengukuran.status} />
                    </div>
                </div>

                {/* Catatan Revisi jika Dikembalikan */}
                {lastRejection && pengukuran.status === 'dikembalikan' && (
                    <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-3">
                        <AlertTriangle className="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                        <div className="flex-1">
                            <h4 className="text-xs font-bold text-rose-900 uppercase tracking-wide">
                                Catatan Perbaikan dari Tim Perencanaan
                            </h4>
                            <p className="text-xs text-rose-800 mt-1 whitespace-pre-wrap">
                                {lastRejection.catatan}
                            </p>
                            <span className="text-[10px] text-rose-500 mt-1 block">
                                Dikembalikan oleh {lastRejection.user?.name || 'Verifikator'} • {new Date(lastRejection.created_at).toLocaleString('id-ID')}
                            </span>
                        </div>
                    </div>
                )}

                {/* Lock banner if not draft */}
                {isLocked && (
                    <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center gap-3 text-blue-900 text-xs">
                        <Clock className="w-5 h-5 text-blue-600 shrink-0" />
                        <div>
                            <span className="font-semibold">Data sedang dalam status {pengukuran.status}.</span>{' '}
                            Formulir ini terkunci untuk pengeditan karena telah diajukan ke tim verifikator.
                        </div>
                    </div>
                )}

                {/* Info Indikator Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Indikator Kinerja Utama</CardTitle>
                        <span className="text-xs font-bold px-2.5 py-1 rounded bg-primary/10 text-primary">
                            {iku?.kode}
                        </span>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">
                                {iku?.nama}
                            </h2>
                            {iku?.definisi_operasional && (
                                <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                                    {iku?.definisi_operasional}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-100 text-xs">
                            <div className="p-2.5 bg-slate-50 rounded-lg">
                                <span className="text-slate-400 block text-[10px] uppercase font-semibold">
                                    Unit Penanggung Jawab
                                </span>
                                <span className="font-semibold text-slate-800">
                                    {pengukuran.penugasan_indikator?.unit_kerja?.nama}
                                </span>
                            </div>
                            <div className="p-2.5 bg-slate-50 rounded-lg">
                                <span className="text-slate-400 block text-[10px] uppercase font-semibold">
                                    Tipe Perhitungan Formula
                                </span>
                                <span className="font-semibold text-primary uppercase">
                                    {iku?.tipe_perhitungan}
                                </span>
                                <span className="text-slate-500 text-[10px] block">
                                    {iku?.tipe_perhitungan === 'turun_baik'
                                        ? 'Semakin kecil realisasi, capaian semakin tinggi'
                                        : 'Semakin besar realisasi, capaian semakin tinggi'}
                                </span>
                            </div>
                            <div className="p-2.5 bg-slate-50 rounded-lg">
                                <span className="text-slate-400 block text-[10px] uppercase font-semibold">
                                    Target Triwulan {pengukuran.periode_jadwal?.triwulan}
                                </span>
                                <span className="font-bold text-base text-slate-900">
                                    {pengukuran.target} {iku?.satuan}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Form Pengukuran */}
                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    {/* Realisasi & Live Calculator */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calculator className="w-4 h-4 text-primary" />
                                Realisasi & Simulasi Capaian
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
                                <div>
                                    <Input
                                        label={`Realisasi Capaian (${iku?.satuan})`}
                                        type="number"
                                        step="any"
                                        name="realisasi"
                                        placeholder="Masukkan angka realisasi..."
                                        value={data.realisasi}
                                        onChange={(e) => setData('realisasi', e.target.value)}
                                        error={errors.realisasi}
                                        disabled={isLocked}
                                        required
                                        helperText={`Target yang harus dipenuhi: ${pengukuran.target} ${iku?.satuan}`}
                                    />
                                </div>

                                {/* Live Calculation Result Card */}
                                <div className={`p-4 rounded-xl border transition-all ${
                                    calculatedCapaian === null
                                        ? 'bg-slate-50 border-slate-200'
                                        : calculatedCapaian >= 100
                                        ? 'bg-emerald-50/80 border-emerald-300'
                                        : calculatedCapaian >= 80
                                        ? 'bg-blue-50/80 border-blue-300'
                                        : 'bg-rose-50/80 border-rose-300'
                                }`}>
                                    <div className="text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        Hasil Kalkulasi Otomatis (Live Preview)
                                    </div>
                                    <div className="flex items-baseline gap-2 mt-1">
                                        <span className={`text-3xl font-extrabold ${
                                            calculatedCapaian === null
                                                ? 'text-slate-400'
                                                : calculatedCapaian >= 100
                                                ? 'text-emerald-700'
                                                : calculatedCapaian >= 80
                                                ? 'text-blue-700'
                                                : 'text-rose-700'
                                        }`}>
                                            {calculatedCapaian !== null ? `${calculatedCapaian}%` : '0.00%'}
                                        </span>
                                        {calculatedCapaian !== null && (
                                            <span className="text-xs font-semibold">
                                                {calculatedCapaian >= 100
                                                    ? 'Target Tercapai / Melampaui'
                                                    : calculatedCapaian >= 80
                                                    ? 'Cukup Baik'
                                                    : 'Perlu Perhatian Khusus'}
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-[11px] text-slate-500 mt-1 font-mono">
                                        Rumus: {iku?.tipe_perhitungan === 'turun_baik'
                                            ? `((2 * ${pengukuran.target} - ${data.realisasi || '0'}) / ${pengukuran.target}) * 100%`
                                            : `(${data.realisasi || '0'} / ${pengukuran.target}) * 100%`}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Bukti Dukung (Evidence) Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UploadCloud className="w-4 h-4 text-primary" />
                                Dokumen Bukti Dukung (Evidence)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Existing Evidence List */}
                            {pengukuran.bukti_dukungs && pengukuran.bukti_dukungs.length > 0 && (
                                <div className="space-y-2">
                                    <div className="text-xs font-semibold text-slate-700">
                                        Berkas Bukti Dukung Terlampir:
                                    </div>
                                    <div className="divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden">
                                        {pengukuran.bukti_dukungs.map((b: any) => (
                                            <div key={b.id} className="p-3 bg-white flex items-center justify-between text-xs hover:bg-slate-50">
                                                <div className="flex items-center gap-2">
                                                    <FileText className="w-4 h-4 text-primary" />
                                                    <div>
                                                        <div className="font-semibold text-slate-800">{b.nama_file}</div>
                                                        <div className="text-[10px] text-slate-400">{b.keterangan || 'Dokumen Bukti'}</div>
                                                    </div>
                                                </div>
                                                {b.download_url && (
                                                    <a
                                                        href={b.download_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 text-primary hover:underline font-medium text-xs"
                                                    >
                                                        Lihat Dokumen
                                                        <ExternalLink className="w-3 h-3" />
                                                    </a>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {!isLocked && (
                                <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-4">
                                    <div className="text-xs font-semibold text-slate-700">
                                        Unggah Bukti Dukung Baru
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        {/* File Upload Option */}
                                        <div>
                                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                                                Unggah File Fisik (PDF / Gambar / Excel)
                                            </label>
                                            <input
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx"
                                                onChange={(e) => setData('file_bukti', e.target.files?.[0] || null)}
                                                className="block w-full text-xs text-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90 file:cursor-pointer cursor-pointer border border-border rounded-lg p-1.5 bg-surface"
                                            />
                                            {errors.file_bukti && (
                                                <p className="mt-1 text-xs text-rose-600">{errors.file_bukti}</p>
                                            )}
                                        </div>

                                        {/* Cloud Link Option */}
                                        <div>
                                            <Input
                                                label="Atau Tautan Cloud (Google Drive / OneDrive)"
                                                type="url"
                                                placeholder="https://drive.google.com/..."
                                                value={data.url_bukti}
                                                onChange={(e) => setData('url_bukti', e.target.value)}
                                                error={errors.url_bukti}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Input
                                            label="Keterangan Singkat Bukti Dukung"
                                            placeholder="Contoh: SK Rektor, Laporan Akreditasi BAN-PT..."
                                            value={data.keterangan_bukti}
                                            onChange={(e) => setData('keterangan_bukti', e.target.value)}
                                            error={errors.keterangan_bukti}
                                        />
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Catatan Analisis & Kendala (Mandatory if Capaian < 100%) */}
                    <Card className={isUnderperforming ? 'border-amber-300 ring-1 ring-amber-200' : ''}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="w-4 h-4 text-primary" />
                                Analisis Kinerja & Tindak Lanjut
                            </CardTitle>
                            {isUnderperforming && (
                                <span className="text-xs font-bold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-md flex items-center gap-1">
                                    <AlertTriangle className="w-3.5 h-3.5" />
                                    Wajib Diisi (Capaian &lt; 100%)
                                </span>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Textarea
                                label="Identifikasi Kendala dan Permasalahan"
                                placeholder="Jelaskan hambatan atau tantangan operasional yang dihadapi selama periode berjalan..."
                                value={data.kendala}
                                onChange={(e) => setData('kendala', e.target.value)}
                                error={errors.kendala}
                                disabled={isLocked}
                                required={isUnderperforming}
                            />

                            <Textarea
                                label="Tindak Lanjut Yang Telah Dilakukan"
                                placeholder="Upaya dan penanganan konkrit yang sudah dijalankan untuk mengatasi kendala..."
                                value={data.tindak_lanjut}
                                onChange={(e) => setData('tindak_lanjut', e.target.value)}
                                error={errors.tindak_lanjut}
                                disabled={isLocked}
                                required={isUnderperforming}
                            />

                            <Textarea
                                label="Strategi dan Solusi Periode Berikutnya"
                                placeholder="Rencana strategi perbaikan untuk mencapai target pada triwulan berikutnya..."
                                value={data.strategi}
                                onChange={(e) => setData('strategi', e.target.value)}
                                error={errors.strategi}
                                disabled={isLocked}
                                required={isUnderperforming}
                            />
                        </CardContent>
                    </Card>

                    {/* Action Buttons */}
                    {!isLocked && (
                        <div className="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-200">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => handleSubmit('draft')}
                                isLoading={processing}
                            >
                                <Save className="w-4 h-4 mr-1" />
                                Simpan Sebagai Draft
                            </Button>

                            <Button
                                type="button"
                                variant="primary"
                                onClick={() => handleSubmit('ajukan')}
                                isLoading={processing}
                            >
                                <Send className="w-4 h-4 mr-1" />
                                Ajukan ke Tim Perencanaan
                            </Button>
                        </div>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
