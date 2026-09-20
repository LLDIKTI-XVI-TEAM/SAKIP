import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { 
    Layers, 
    ChevronDown, 
    ChevronRight, 
    Building2, 
    Plus, 
    FileText, 
    Calendar, 
    TrendingUp, 
    TrendingDown, 
    Award, 
    BookOpen,
    X,
    Info,
    CheckCircle2,
    Shield
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';

interface IndikatorItem {
    id: number;
    kode: string;
    nama: string;
    satuan: string;
    tipe_perhitungan: 'naik_baik' | 'turun_baik' | string;
    unit_nama: string;
    unit_kode: string;
    target_tahunan: number;
    tw1: number;
    tw2: number;
    tw3: number;
    tw4: number;
}

interface SasaranItem {
    id: number;
    kode: string;
    deskripsi: string;
    urutan: number;
    indikator: IndikatorItem[];
}

interface RenstraAktif {
    id: number;
    kode: string;
    nama: string;
    tahun_mulai: number;
    tahun_selesai: number;
    deskripsi: string;
    dasar_hukum: string;
    is_aktif: boolean;
    total_sasaran: number;
    total_indikator: number;
}

interface RiwayatRenstra {
    id: number;
    kode: string;
    nama: string;
    tahun_mulai: number;
    tahun_selesai: number;
    is_aktif: boolean;
    total_sasaran: number;
    total_indikator: number;
}

interface RenstraIndexProps {
    renstraAktif: RenstraAktif;
    sasaranStrategisList: SasaranItem[];
    riwayatRenstra: RiwayatRenstra[];
}

export default function RenstraIndex({
    renstraAktif,
    sasaranStrategisList,
    riwayatRenstra,
}: RenstraIndexProps) {
    const [expandedSasaran, setExpandedSasaran] = useState<Record<number, boolean>>({
        1: true,
        2: true,
        3: true,
    });

    const [isAddSasaranOpen, setIsAddSasaranOpen] = useState(false);
    const [isAddIndikatorOpen, setIsAddIndikatorOpen] = useState(false);
    const [selectedSasaranForIndikator, setSelectedSasaranForIndikator] = useState<number | null>(null);
    const [activeTab, setActiveTab] = useState<'cascading' | 'riwayat'>('cascading');

    // Local mock state for interactive feedback
    const [mockNotification, setMockNotification] = useState<string | null>(null);

    const toggleSasaran = (id: number) => {
        setExpandedSasaran((prev) => ({
            ...prev,
            [id]: !prev[id],
        }));
    };

    const handleMockSubmitSasaran = (e: React.FormEvent) => {
        e.preventDefault();
        setIsAddSasaranOpen(false);
        setMockNotification('Mockup: Sasaran Strategis baru berhasil ditambahkan ke kerangka cascading.');
        setTimeout(() => setMockNotification(null), 4000);
    };

    const handleMockSubmitIndikator = (e: React.FormEvent) => {
        e.preventDefault();
        setIsAddIndikatorOpen(false);
        setMockNotification('Mockup: Indikator Kinerja Utama baru berhasil ditambahkan.');
        setTimeout(() => setMockNotification(null), 4000);
    };

    return (
        <AuthenticatedLayout
            title="Rencana Strategis & Cascading Kinerja"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Perencanaan Kinerja' },
                { label: 'Renstra & Sasaran' },
            ]}
        >
            <Head title="Renstra & Cascading Kinerja - SAKIP LLDIKTI XVI" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Mock Alert Notification */}
                {mockNotification && (
                    <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center justify-between shadow-xs animate-in fade-in duration-200">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                            <span>{mockNotification}</span>
                        </div>
                        <button onClick={() => setMockNotification(null)} className="text-emerald-700 hover:text-emerald-900">
                            <X className="w-4 h-4" />
                        </button>
                    </div>
                )}

                {/* Header Banner */}
                <div className="bg-gradient-to-r from-[#122E92] via-[#1a38a0] to-[#122E92] rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Layers className="w-6 h-6 text-[#D6AC48]" />
                            <h1 className="text-xl font-bold tracking-tight">
                                Rencana Strategis & Pohon Kinerja (Cascading)
                            </h1>
                        </div>
                        <p className="text-sm text-blue-100/90 max-w-2xl">
                            Payung strategis 5 tahun LLDIKTI Wilayah XVI. Memetakan keselarasan Sasaran Strategis (SS) menuju Indikator Kinerja Utama (IKU) dan unit pelaksana.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            onClick={() => setIsAddSasaranOpen(true)}
                            className="bg-[#D6AC48] hover:bg-[#c49a37] text-slate-900 font-semibold shadow-sm flex items-center gap-2"
                        >
                            <Plus className="w-4 h-4" />
                            Tambah Sasaran
                        </Button>
                    </div>
                </div>

                {/* Tab Pilihan Tampilan */}
                <div className="flex border-b border-slate-200">
                    <button
                        onClick={() => setActiveTab('cascading')}
                        className={`px-5 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors ${
                            activeTab === 'cascading'
                                ? 'border-[#122E92] text-[#122E92]'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Layers className="w-4 h-4 text-[#D6AC48]" />
                        Renstra Aktif & Pohon Kinerja
                    </button>
                    <button
                        onClick={() => setActiveTab('riwayat')}
                        className={`px-5 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors ${
                            activeTab === 'riwayat'
                                ? 'border-[#122E92] text-[#122E92]'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        }`}
                    >
                        <Calendar className="w-4 h-4 text-slate-400" />
                        Histori Renstra Sebelumnya
                    </button>
                </div>

                {activeTab === 'cascading' ? (
                    <>
                        {/* Ringkasan Renstra Aktif */}
                        <Card className="border-blue-100 bg-linear-to-b from-blue-50/40 to-white shadow-xs">
                            <CardContent className="p-6">
                                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="px-2.5 py-0.5 rounded text-xs font-mono font-bold bg-[#122E92] text-white">
                                                {renstraAktif.kode}
                                            </span>
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                <span className="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500" />
                                                Berlaku Aktif
                                            </span>
                                            <span className="text-xs font-medium text-slate-500 flex items-center gap-1">
                                                <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                                Periode: {renstraAktif.tahun_mulai} - {renstraAktif.tahun_selesai}
                                            </span>
                                        </div>

                                        <h2 className="text-lg font-bold text-slate-900">
                                            {renstraAktif.nama}
                                        </h2>

                                        <p className="text-sm text-slate-600 leading-relaxed max-w-4xl">
                                            {renstraAktif.deskripsi}
                                        </p>

                                        <div className="bg-amber-50/80 border border-amber-200/80 rounded-lg p-3 text-xs text-amber-900 max-w-3xl flex items-start gap-2">
                                            <BookOpen className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                                            <div>
                                                <span className="font-semibold text-amber-900">Dasar Hukum Penetapan: </span>
                                                <span>{renstraAktif.dasar_hukum}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Quick Stats Widget */}
                                    <div className="grid grid-cols-2 gap-3 shrink-0">
                                        <div className="bg-white border border-slate-200 rounded-xl p-3 text-center min-w-[120px] shadow-2xs">
                                            <div className="text-2xl font-bold text-[#122E92]">
                                                {renstraAktif.total_sasaran}
                                            </div>
                                            <div className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mt-0.5">
                                                Sasaran Strategis
                                            </div>
                                        </div>
                                        <div className="bg-white border border-slate-200 rounded-xl p-3 text-center min-w-[120px] shadow-2xs">
                                            <div className="text-2xl font-bold text-[#D6AC48]">
                                                {renstraAktif.total_indikator}
                                            </div>
                                            <div className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mt-0.5">
                                                Indikator (IKU)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Hierarki Cascading Pohon Kinerja */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-base font-bold text-slate-800 flex items-center gap-2">
                                    <span>Pohon Kinerja (Cascading) Lembaga</span>
                                </h3>
                                <span className="text-xs text-slate-500">Klik sasaran untuk melihat rincian indikator</span>
                            </div>

                            {sasaranStrategisList.map((sasaran) => {
                                const isExpanded = !!expandedSasaran[sasaran.id];

                                return (
                                    <Card key={sasaran.id} className="border-slate-200 overflow-hidden shadow-xs">
                                        {/* Sasaran Strategis Header Bar */}
                                        <div 
                                            onClick={() => toggleSasaran(sasaran.id)}
                                            className="px-6 py-4 bg-slate-50 hover:bg-slate-100/70 border-b border-slate-200/80 cursor-pointer flex items-center justify-between transition-colors select-none"
                                        >
                                            <div className="flex items-center gap-3">
                                                <button className="text-slate-400 hover:text-slate-700">
                                                    {isExpanded ? (
                                                        <ChevronDown className="w-5 h-5 text-[#122E92]" />
                                                    ) : (
                                                        <ChevronRight className="w-5 h-5 text-slate-400" />
                                                    )}
                                                </button>
                                                <span className="px-2 py-0.5 bg-[#122E92] text-white text-xs font-mono font-bold rounded">
                                                    {sasaran.kode}
                                                </span>
                                                <h4 className="text-sm font-semibold text-slate-900 leading-snug">
                                                    {sasaran.deskripsi}
                                                </h4>
                                            </div>

                                            <div className="flex items-center gap-3 shrink-0">
                                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 text-[#122E92] font-semibold border border-blue-200">
                                                    {sasaran.indikator.length} IKU Terkait
                                                </span>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setSelectedSasaranForIndikator(sasaran.id);
                                                        setIsAddIndikatorOpen(true);
                                                    }}
                                                    className="text-xs text-blue-700 hover:bg-blue-50"
                                                >
                                                    <Plus className="w-3.5 h-3.5 mr-1" />
                                                    Tambah IKU
                                                </Button>
                                            </div>
                                        </div>

                                        {/* Body Daftar Indikator Cascading */}
                                        {isExpanded && (
                                            <CardContent className="p-0">
                                                <div className="overflow-x-auto">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="bg-slate-50/50 text-slate-500 font-semibold border-b border-slate-200 text-xs">
                                                            <tr>
                                                                <th className="px-6 py-3">Kode & Nama IKU</th>
                                                                <th className="px-6 py-3">Unit Pelaksana (Pokja)</th>
                                                                <th className="px-6 py-3">Tipe / Rumus</th>
                                                                <th className="px-6 py-3 text-center">Target Tahunan</th>
                                                                <th className="px-6 py-3 text-center">TW 1</th>
                                                                <th className="px-6 py-3 text-center">TW 2</th>
                                                                <th className="px-6 py-3 text-center">TW 3</th>
                                                                <th className="px-6 py-3 text-center">TW 4</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-slate-100">
                                                            {sasaran.indikator.map((iku) => (
                                                                <tr key={iku.id} className="hover:bg-slate-50/70 transition-colors">
                                                                    <td className="px-6 py-3.5">
                                                                        <div className="flex items-start gap-2">
                                                                            <span className="font-mono text-xs font-bold text-[#122E92] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100 shrink-0">
                                                                                {iku.kode}
                                                                            </span>
                                                                            <span className="font-medium text-slate-800 max-w-md">
                                                                                {iku.nama}
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-6 py-3.5">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <Building2 className="w-3.5 h-3.5 text-[#D6AC48] shrink-0" />
                                                                            <span className="text-xs font-medium text-slate-700">
                                                                                {iku.unit_nama}
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-6 py-3.5">
                                                                        {iku.tipe_perhitungan === 'naik_baik' ? (
                                                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                                                                <TrendingUp className="w-3 h-3 text-emerald-600" />
                                                                                Naik Baik ({iku.satuan})
                                                                            </span>
                                                                        ) : (
                                                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                                                                <TrendingDown className="w-3 h-3 text-amber-600" />
                                                                                Turun Baik ({iku.satuan})
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-6 py-3.5 text-center font-bold text-[#122E92]">
                                                                        {iku.target_tahunan} {iku.satuan}
                                                                    </td>
                                                                    <td className="px-6 py-3.5 text-center text-xs text-slate-600">
                                                                        {iku.tw1}
                                                                    </td>
                                                                    <td className="px-6 py-3.5 text-center text-xs text-slate-600">
                                                                        {iku.tw2}
                                                                    </td>
                                                                    <td className="px-6 py-3.5 text-center text-xs text-slate-600">
                                                                        {iku.tw3}
                                                                    </td>
                                                                    <td className="px-6 py-3.5 text-center text-xs text-slate-600">
                                                                        {iku.tw4}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </CardContent>
                                        )}
                                    </Card>
                                );
                            })}
                        </div>
                    </>
                ) : (
                    /* Tab Riwayat Renstra */
                    <Card className="border-slate-200">
                        <CardHeader className="bg-slate-50/70 border-b border-slate-200 px-6 py-4">
                            <CardTitle className="text-base font-semibold text-slate-800">
                                Arsip Rencana Strategis Sebelumnya
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                                    <tr>
                                        <th className="px-6 py-3.5">Kode</th>
                                        <th className="px-6 py-3.5">Nama Renstra</th>
                                        <th className="px-6 py-3.5">Rentang Periode</th>
                                        <th className="px-6 py-3.5">Jumlah Sasaran & IKU</th>
                                        <th className="px-6 py-3.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {riwayatRenstra.map((renstra) => (
                                        <tr key={renstra.id} className="hover:bg-slate-50">
                                            <td className="px-6 py-4 font-mono text-xs font-semibold text-slate-700">
                                                {renstra.kode}
                                            </td>
                                            <td className="px-6 py-4 font-medium text-slate-900">
                                                {renstra.nama}
                                            </td>
                                            <td className="px-6 py-4 text-xs text-slate-600">
                                                {renstra.tahun_mulai} - {renstra.tahun_selesai}
                                            </td>
                                            <td className="px-6 py-4 text-xs text-slate-600">
                                                {renstra.total_sasaran} Sasaran / {renstra.total_indikator} IKU
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs font-semibold border border-slate-200">
                                                    Arsip Histori
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* MOCK MODAL: Tambah Sasaran Strategis */}
            {isAddSasaranOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-[#122E92] text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Layers className="w-5 h-5 text-[#D6AC48]" />
                                <h3 className="font-semibold text-base">Tambah Sasaran Strategis Baru</h3>
                            </div>
                            <button onClick={() => setIsAddSasaranOpen(false)} className="text-white/80 hover:text-white">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleMockSubmitSasaran} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Kode Sasaran Strategis <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Contoh: SS-04"
                                    defaultValue="SS-04"
                                    required
                                    className="text-sm font-mono"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Deskripsi Sasaran Kinerja <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="Tuliskan pernyataan hasil/outcome strategis yang ingin dicapai..."
                                    className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                                    required
                                    defaultValue="Meningkatnya Sinergi Kemitraan Dunia Usaha dan Dunia Industri (DUDI) dengan Perguruan Tinggi Swasta"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Urutan Tampilan
                                </label>
                                <Input
                                    type="number"
                                    defaultValue={4}
                                    className="text-sm w-32"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsAddSasaranOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    className="bg-[#122E92] hover:bg-[#0d226b] text-white font-semibold"
                                >
                                    Simpan Sasaran (Mockup)
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MOCK MODAL: Tambah IKU */}
            {isAddIndikatorOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-[#122E92] text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Award className="w-5 h-5 text-[#D6AC48]" />
                                <h3 className="font-semibold text-base">Tambah Indikator Kinerja Utama (IKU)</h3>
                            </div>
                            <button onClick={() => setIsAddIndikatorOpen(false)} className="text-white/80 hover:text-white">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleMockSubmitIndikator} className="p-6 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Kode IKU <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Contoh: IKU-06"
                                    defaultValue="IKU-06"
                                    required
                                    className="text-sm font-mono"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Indikator Kinerja <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Tuliskan nama indikator..."
                                    defaultValue="Jumlah Kemitraan Strategis DUDI dengan PTS Binaan LLDIKTI XVI"
                                    required
                                    className="text-sm"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Satuan Ukur <span className="text-red-500">*</span>
                                    </label>
                                    <Input
                                        type="text"
                                        placeholder="%, Dokumen, Lembaga"
                                        defaultValue="MoU / PKS"
                                        required
                                        className="text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Tipe Perhitungan
                                    </label>
                                    <select className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]">
                                        <option value="naik_baik">Naik Baik (Semakin tinggi semakin baik)</option>
                                        <option value="turun_baik">Turun Baik (Semakin rendah semakin baik)</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Target Tahunan
                                    </label>
                                    <Input
                                        type="number"
                                        defaultValue={25}
                                        className="text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Unit Penanggung Jawab
                                    </label>
                                    <select className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]">
                                        <option>Pokja Akademik dan Kemahasiswaan</option>
                                        <option>Pokja Kelembagaan dan Sistem Informasi</option>
                                        <option>Pokja Sumber Daya Perguruan Tinggi</option>
                                        <option>Bagian Umum</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsAddIndikatorOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    className="bg-[#122E92] hover:bg-[#0d226b] text-white font-semibold"
                                >
                                    Simpan IKU (Mockup)
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
