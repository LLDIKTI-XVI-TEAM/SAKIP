import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { 
    ArrowLeft, 
    CheckCircle, 
    XCircle, 
    FileText, 
    ExternalLink, 
    Clock, 
    UserCheck, 
    ShieldCheck, 
    AlertCircle,
    Building2,
    Calendar
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Textarea } from '@/Components/Textarea';

interface VerifikasiShowProps {
    pengukuran: any;
}

export default function VerifikasiShow({ pengukuran }: VerifikasiShowProps) {
    const [showKembalikanModal, setShowKembalikanModal] = useState(false);
    const [catatanRevisi, setCatatanRevisi] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const iku = pengukuran.penugasan_indikator?.indikator_kinerja;
    const unit = pengukuran.penugasan_indikator?.unit_kerja;
    const pic = pengukuran.penugasan_indikator?.pic;

    const handleSahkan = () => {
        if (confirm('Apakah Anda yakin ingin mengesahkan capaian kinerja ini secara resmi? Data akan dibekukan ke dalam snapshot imutabel.')) {
            setIsSubmitting(true);
            router.post(`/verifikasi/${pengukuran.id}/sahkan`, {}, {
                onFinish: () => setIsSubmitting(false),
            });
        }
    };

    const handleKembalikan = (e: React.FormEvent) => {
        e.preventDefault();
        if (!catatanRevisi || catatanRevisi.length < 10) {
            alert('Harap masukkan catatan perbaikan yang jelas (minimal 10 karakter).');
            return;
        }

        setIsSubmitting(true);
        router.post(`/verifikasi/${pengukuran.id}/kembalikan`, {
            catatan: catatanRevisi,
        }, {
            onFinish: () => {
                setIsSubmitting(false);
                setShowKembalikanModal(false);
            },
        });
    };

    const isAlreadyRatified = pengukuran.status === 'disahkan';

    return (
        <AuthenticatedLayout
            title="Reviu & Verifikasi Capaian Kinerja"
            breadcrumbs={[
                { label: 'Verifikasi Kinerja', href: '/verifikasi' },
                { label: `Reviu ${iku?.kode}` },
            ]}
        >
            <Head title={`Reviu ${iku?.kode}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Top Nav */}
                <div className="flex items-center justify-between">
                    <Link
                        href="/verifikasi"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Kembali ke Antrean Verifikasi
                    </Link>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-slate-500">Status Capaian:</span>
                        <Badge status={pengukuran.status} />
                    </div>
                </div>

                {/* Status Snapshot Banner if Disahkan */}
                {isAlreadyRatified && (
                    <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3 text-emerald-900 text-xs">
                        <ShieldCheck className="w-5 h-5 text-emerald-600 shrink-0" />
                        <div>
                            <span className="font-bold">Kinerja ini telah resmi Disahkan!</span>{' '}
                            Snapshot historis tersimpan secara imutabel. Disahkan pada{' '}
                            {new Date(pengukuran.disahkan_pada).toLocaleString('id-ID')}
                        </div>
                    </div>
                )}

                {/* Ringkasan Indikator */}
                <Card>
                    <CardHeader>
                        <div>
                            <span className="text-xs font-bold text-[#122E92] bg-blue-50 px-2.5 py-0.5 rounded mr-2">
                                {iku?.kode}
                            </span>
                            <span className="text-xs text-slate-500">
                                {unit?.nama}
                            </span>
                        </div>
                        <div className="text-xs text-slate-400">
                            Diajukan oleh: <span className="font-semibold text-slate-700">{pic?.name}</span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <h2 className="text-lg font-bold text-slate-900">
                            {iku?.nama}
                        </h2>

                        {iku?.definisi_operasional && (
                            <p className="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 leading-relaxed">
                                <strong className="text-slate-700">Definisi Operasional:</strong> {iku?.definisi_operasional}
                            </p>
                        )}

                        {/* Metrik Target vs Realisasi */}
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                            <div className="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <span className="text-xs text-slate-500 font-semibold block uppercase">
                                    Target Triwulan {pengukuran.periode_jadwal?.triwulan}
                                </span>
                                <span className="text-2xl font-bold text-slate-900 mt-1 block">
                                    {pengukuran.target} <span className="text-xs font-normal text-slate-500">{iku?.satuan}</span>
                                </span>
                            </div>

                            <div className="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <span className="text-xs text-slate-500 font-semibold block uppercase">
                                    Realisasi Dilaporkan
                                </span>
                                <span className="text-2xl font-bold text-slate-900 mt-1 block">
                                    {pengukuran.realisasi !== null ? pengukuran.realisasi : '-'} <span className="text-xs font-normal text-slate-500">{iku?.satuan}</span>
                                </span>
                            </div>

                            <div className="p-4 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50/50 border border-blue-200">
                                <span className="text-xs text-blue-700 font-semibold block uppercase">
                                    Persentase Capaian
                                </span>
                                <span className="text-2xl font-extrabold text-[#122E92] mt-1 block">
                                    {pengukuran.capaian_persen !== null ? `${pengukuran.capaian_persen}%` : '-'}
                                </span>
                                <span className="text-[10px] text-slate-500 block mt-0.5">
                                    Formula: {iku?.tipe_perhitungan}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Dokumen Bukti Dukung */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Dokumen & Tautan Bukti Dukung ({pengukuran.bukti_dukungs?.length || 0})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pengukuran.bukti_dukungs && pengukuran.bukti_dukungs.length > 0 ? (
                            <div className="divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden">
                                {pengukuran.bukti_dukungs.map((b: any) => (
                                    <div key={b.id} className="p-3 bg-white flex items-center justify-between text-xs hover:bg-slate-50">
                                        <div className="flex items-center gap-2.5">
                                            <FileText className="w-4 h-4 text-[#122E92]" />
                                            <div>
                                                <div className="font-semibold text-slate-800">{b.nama_file}</div>
                                                <div className="text-[11px] text-slate-400">
                                                    {b.keterangan || 'Bukti Dukung'} • {b.tipe_file?.toUpperCase()}
                                                </div>
                                            </div>
                                        </div>
                                        {b.download_url && (
                                            <a
                                                href={b.download_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 px-3 py-1 bg-[#122E92]/10 hover:bg-[#122E92]/20 text-[#122E92] rounded font-semibold text-xs transition-colors"
                                            >
                                                Buka Berkas
                                                <ExternalLink className="w-3.5 h-3.5" />
                                            </a>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="p-4 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-800 flex items-center gap-2">
                                <AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
                                PIC tidak melampirkan file dokumen fisik atau tautan bukti dukung pada pengajuan ini.
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Analisis Kendala & Solusi PIC */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Catatan Analisis Kinerja dari PIC</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-xs">
                        <div>
                            <span className="font-bold text-slate-700 block mb-1">
                                Identifikasi Kendala & Hambatan:
                            </span>
                            <p className="text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 whitespace-pre-wrap">
                                {pengukuran.kendala || 'Tidak ada catatan kendala dilaporkan.'}
                            </p>
                        </div>

                        <div>
                            <span className="font-bold text-slate-700 block mb-1">
                                Tindak Lanjut yang Dilakukan:
                            </span>
                            <p className="text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 whitespace-pre-wrap">
                                {pengukuran.tindak_lanjut || 'Tidak ada catatan tindak lanjut dilaporkan.'}
                            </p>
                        </div>

                        <div>
                            <span className="font-bold text-slate-700 block mb-1">
                                Rencana Strategi Perbaikan:
                            </span>
                            <p className="text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 whitespace-pre-wrap">
                                {pengukuran.strategi || 'Tidak ada catatan strategi dilaporkan.'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Action Bar (Only if not already ratified) */}
                {!isAlreadyRatified && (
                    <div className="p-5 bg-white border border-slate-200 rounded-xl shadow-xs flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Keputusan Tim Perencanaan
                            </h4>
                            <p className="text-xs text-slate-500">
                                Kembalikan jika berkas tidak sesuai, atau sahkan secara resmi jika valid.
                            </p>
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="danger"
                                onClick={() => setShowKembalikanModal(true)}
                                disabled={isSubmitting}
                            >
                                <XCircle className="w-4 h-4 mr-1" />
                                Kembalikan ke PIC (Revisi)
                            </Button>

                            <Button
                                type="button"
                                variant="primary"
                                onClick={handleSahkan}
                                isLoading={isSubmitting}
                            >
                                <CheckCircle className="w-4 h-4 mr-1" />
                                Sahkan Kinerja Resmi
                            </Button>
                        </div>
                    </div>
                )}

                {/* Modal Kembalikan Catatan Revisi */}
                {showKembalikanModal && (
                    <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 animate-scale-in">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <XCircle className="w-5 h-5 text-rose-600" />
                                    Kembalikan Pengukuran ke PIC
                                </h3>
                                <button
                                    onClick={() => setShowKembalikanModal(false)}
                                    className="text-slate-400 hover:text-slate-600 text-sm font-bold"
                                >
                                    ✕
                                </button>
                            </div>

                            <form onSubmit={handleKembalikan} className="space-y-4">
                                <p className="text-xs text-slate-600">
                                    Tuliskan catatan perbaikan atau koreksi yang wajib dipenuhi oleh PIC sebelum mengajukan kembali:
                                </p>

                                <Textarea
                                    rows={4}
                                    placeholder="Contoh: Bukti dukung SK belum ditandatangani, angka realisasi TW1 perlu diverifikasi ulang..."
                                    value={catatanRevisi}
                                    onChange={(e) => setCatatanRevisi(e.target.value)}
                                    required
                                />

                                <div className="flex items-center justify-end gap-3 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShowKembalikanModal(false)}
                                        disabled={isSubmitting}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="danger"
                                        size="sm"
                                        isLoading={isSubmitting}
                                    >
                                        Kirim Catatan & Kembalikan
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
