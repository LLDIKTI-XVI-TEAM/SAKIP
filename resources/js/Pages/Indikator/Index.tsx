import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { 
    TrendingUp, 
    TrendingDown, 
    Search, 
    Building2, 
    Calendar, 
    Filter, 
    Info, 
    X, 
    Eye, 
    Award, 
    Calculator,
    Layers,
    User as UserIcon,
    ArrowUpRight
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';

interface IndikatorDetail {
    id: number;
    kode: string;
    nama: string;
    definisi_operasional: string;
    satuan: string;
    tipe_perhitungan: 'naik_baik' | 'turun_baik' | string;
    jenis_agregasi: string;
    sasaran_kode: string;
    sasaran_deskripsi: string;
    unit_id: number;
    unit_nama: string;
    unit_kode: string;
    pic_nama: string;
    tahun: number;
    target_tahunan: number;
    target_tw1: number;
    target_tw2: number;
    target_tw3: number;
    target_tw4: number;
    is_aktif: boolean;
}

interface UnitOption {
    id: number;
    nama: string;
    kode: string;
}

interface IndikatorIndexProps {
    indikatorList: IndikatorDetail[];
    units: UnitOption[];
    tahunAktif: number;
}

export default function IndikatorIndex({
    indikatorList,
    units,
    tahunAktif,
}: IndikatorIndexProps) {
    const [search, setSearch] = useState('');
    const [selectedUnit, setSelectedUnit] = useState<string>('all');
    const [selectedTipe, setSelectedTipe] = useState<string>('all');
    const [selectedIndikatorModal, setSelectedIndikatorModal] = useState<IndikatorDetail | null>(null);

    const filteredList = useMemo(() => {
        return indikatorList.filter((item) => {
            const matchSearch =
                item.nama.toLowerCase().includes(search.toLowerCase()) ||
                item.kode.toLowerCase().includes(search.toLowerCase()) ||
                item.definisi_operasional.toLowerCase().includes(search.toLowerCase()) ||
                item.unit_nama.toLowerCase().includes(search.toLowerCase());

            const matchUnit =
                selectedUnit === 'all' || item.unit_id.toString() === selectedUnit;

            const matchTipe =
                selectedTipe === 'all' || item.tipe_perhitungan === selectedTipe;

            return matchSearch && matchUnit && matchTipe;
        });
    }, [indikatorList, search, selectedUnit, selectedTipe]);

    const stats = useMemo(() => {
        const total = indikatorList.length;
        const naikBaik = indikatorList.filter((i) => i.tipe_perhitungan === 'naik_baik').length;
        const turunBaik = indikatorList.filter((i) => i.tipe_perhitungan === 'turun_baik').length;
        const uniqueUnits = new Set(indikatorList.map((i) => i.unit_id)).size;

        return { total, naikBaik, turunBaik, uniqueUnits };
    }, [indikatorList]);

    return (
        <AuthenticatedLayout
            title="Indikator Kinerja Utama (IKU)"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Perencanaan Kinerja' },
                { label: 'Indikator Kinerja (IKU)' },
            ]}
        >
            <Head title="Indikator Kinerja Utama - SAKIP LLDIKTI XVI" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Banner */}
                <div className="bg-gradient-to-r from-[#122E92] via-[#1a38a0] to-[#122E92] rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <TrendingUp className="w-6 h-6 text-[#D6AC48]" />
                            <h1 className="text-xl font-bold tracking-tight">
                                Indikator Kinerja Utama (IKU) & Target Triwulanan
                            </h1>
                        </div>
                        <p className="text-sm text-blue-100/90 max-w-2xl">
                            Daftar tolok ukur keberhasilan strategis LLDIKTI Wilayah XVI Tahun {tahunAktif}. Dilengkapi definisi operasional, formula perhitungan capaian, dan target berkala per triwulan.
                        </p>
                    </div>

                    <div className="px-4 py-2 bg-white/10 backdrop-blur-xs rounded-lg border border-white/20 text-xs font-semibold shrink-0">
                        Tahun Kinerja: <span className="text-[#D6AC48] text-sm font-bold ml-1">{tahunAktif}</span>
                    </div>
                </div>

                {/* Summary Metrics Bar */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Card className="border-slate-200">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <div className="text-xs font-medium text-slate-500">Total IKU</div>
                                <div className="text-2xl font-bold text-[#122E92] mt-0.5">{stats.total}</div>
                            </div>
                            <Award className="w-8 h-8 text-blue-200" />
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <div className="text-xs font-medium text-slate-500">Tipe Naik Baik</div>
                                <div className="text-2xl font-bold text-emerald-600 mt-0.5">{stats.naikBaik}</div>
                            </div>
                            <TrendingUp className="w-8 h-8 text-emerald-200" />
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <div className="text-xs font-medium text-slate-500">Tipe Turun Baik</div>
                                <div className="text-2xl font-bold text-amber-600 mt-0.5">{stats.turunBaik}</div>
                            </div>
                            <TrendingDown className="w-8 h-8 text-amber-200" />
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <div className="text-xs font-medium text-slate-500">Unit Pengampu</div>
                                <div className="text-2xl font-bold text-[#D6AC48] mt-0.5">{stats.uniqueUnits}</div>
                            </div>
                            <Building2 className="w-8 h-8 text-amber-200" />
                        </CardContent>
                    </Card>
                </div>

                {/* Filter & Kontrol Pencarian */}
                <Card className="border-slate-200">
                    <CardContent className="p-4">
                        <div className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                            <div className="flex-1 relative">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <Input
                                    type="text"
                                    placeholder="Cari kode IKU, nama indikator, definisi, atau unit..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 text-sm"
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Unit:</span>
                                    <select
                                        value={selectedUnit}
                                        onChange={(e) => setSelectedUnit(e.target.value)}
                                        className="text-xs rounded-md border-slate-200 py-1.5 px-2.5 bg-white text-slate-700 focus:border-[#122E92] focus:ring-[#122E92]"
                                    >
                                        <option value="all">Semua Unit</option>
                                        {units.map((u) => (
                                            <option key={u.id} value={u.id.toString()}>
                                                {u.nama}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Formula:</span>
                                    <select
                                        value={selectedTipe}
                                        onChange={(e) => setSelectedTipe(e.target.value)}
                                        className="text-xs rounded-md border-slate-200 py-1.5 px-2.5 bg-white text-slate-700 focus:border-[#122E92] focus:ring-[#122E92]"
                                    >
                                        <option value="all">Semua Formula</option>
                                        <option value="naik_baik">Naik Baik</option>
                                        <option value="turun_baik">Turun Baik</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabel Master IKU & Target Berkala */}
                <Card className="border-slate-200 shadow-xs">
                    <CardHeader className="bg-slate-50/70 border-b border-slate-200 px-6 py-4 flex flex-row items-center justify-between">
                        <CardTitle className="text-base font-semibold text-slate-800 flex items-center gap-2">
                            <span>Daftar Indikator Kinerja Utama (IKU)</span>
                            <span className="text-xs px-2.5 py-0.5 rounded-full bg-blue-50 text-[#122E92] font-semibold border border-blue-200">
                                {filteredList.length} Indikator
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 text-xs">
                                <tr>
                                    <th className="px-6 py-3.5">Kode & Indikator</th>
                                    <th className="px-6 py-3.5">Unit Penanggung Jawab</th>
                                    <th className="px-6 py-3.5">Formula / Satuan</th>
                                    <th className="px-6 py-3.5 text-center">Target Tahunan</th>
                                    <th className="px-6 py-3.5 text-center bg-blue-50/30">TW 1</th>
                                    <th className="px-6 py-3.5 text-center bg-blue-50/30">TW 2</th>
                                    <th className="px-6 py-3.5 text-center bg-blue-50/30">TW 3</th>
                                    <th className="px-6 py-3.5 text-center bg-blue-50/30">TW 4</th>
                                    <th className="px-6 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredList.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-6 py-12 text-center text-slate-400">
                                            Tidak ada indikator yang sesuai dengan filter pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    filteredList.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/70 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-start gap-2">
                                                    <span className="font-mono text-xs font-bold bg-[#122E92] text-white px-2 py-0.5 rounded shrink-0">
                                                        {item.kode}
                                                    </span>
                                                    <div>
                                                        <div className="font-medium text-slate-900 max-w-sm">
                                                            {item.nama}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                                                            <Layers className="w-3 h-3" />
                                                            {item.sasaran_kode}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-1.5">
                                                    <Building2 className="w-3.5 h-3.5 text-[#D6AC48] shrink-0" />
                                                    <div>
                                                        <div className="text-xs font-medium text-slate-800">
                                                            {item.unit_nama}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400">
                                                            PIC: {item.pic_nama}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                {item.tipe_perhitungan === 'naik_baik' ? (
                                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                                        <TrendingUp className="w-3 h-3 text-emerald-600" />
                                                        Naik Baik ({item.satuan})
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                                        <TrendingDown className="w-3 h-3 text-amber-600" />
                                                        Turun Baik ({item.satuan})
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 text-center font-bold text-[#122E92]">
                                                {item.target_tahunan} {item.satuan}
                                            </td>

                                            <td className="px-6 py-4 text-center text-xs font-medium text-slate-700 bg-blue-50/20">
                                                {item.target_tw1}
                                            </td>
                                            <td className="px-6 py-4 text-center text-xs font-medium text-slate-700 bg-blue-50/20">
                                                {item.target_tw2}
                                            </td>
                                            <td className="px-6 py-4 text-center text-xs font-medium text-slate-700 bg-blue-50/20">
                                                {item.target_tw3}
                                            </td>
                                            <td className="px-6 py-4 text-center text-xs font-medium text-slate-700 bg-blue-50/20">
                                                {item.target_tw4}
                                            </td>

                                            <td className="px-6 py-4 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => setSelectedIndikatorModal(item)}
                                                    className="text-xs text-blue-700 hover:bg-blue-50 flex items-center gap-1 ml-auto"
                                                >
                                                    <Eye className="w-3.5 h-3.5" />
                                                    Detail
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            {/* MODAL: Detail IKU & Formula Perhitungan */}
            {selectedIndikatorModal && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-[#122E92] text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Award className="w-5 h-5 text-[#D6AC48]" />
                                <h3 className="font-semibold text-base">
                                    Detail Indikator: {selectedIndikatorModal.kode}
                                </h3>
                            </div>
                            <button
                                onClick={() => setSelectedIndikatorModal(null)}
                                className="text-white/80 hover:text-white"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div>
                                <h4 className="text-base font-bold text-slate-900 leading-snug">
                                    {selectedIndikatorModal.nama}
                                </h4>
                                <div className="flex items-center gap-2 mt-1.5 flex-wrap">
                                    <span className="px-2 py-0.5 bg-blue-50 text-[#122E92] border border-blue-200 text-xs font-semibold rounded">
                                        Sasaran: {selectedIndikatorModal.sasaran_kode}
                                    </span>
                                    <span className="text-xs text-slate-500">
                                        {selectedIndikatorModal.sasaran_deskripsi}
                                    </span>
                                </div>
                            </div>

                            <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-2 text-xs">
                                <div className="font-semibold text-slate-800 text-sm flex items-center gap-1.5">
                                    <Info className="w-4 h-4 text-[#122E92]" />
                                    Definisi Operasional
                                </div>
                                <p className="text-slate-600 leading-relaxed">
                                    {selectedIndikatorModal.definisi_operasional}
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div className="p-3 border border-slate-200 rounded-lg space-y-1">
                                    <span className="text-slate-400 font-medium">Unit Penanggung Jawab:</span>
                                    <div className="font-semibold text-slate-800 flex items-center gap-1.5 mt-0.5">
                                        <Building2 className="w-4 h-4 text-[#D6AC48]" />
                                        {selectedIndikatorModal.unit_nama}
                                    </div>
                                    <div className="text-[11px] text-slate-500">PIC: {selectedIndikatorModal.pic_nama}</div>
                                </div>

                                <div className="p-3 border border-slate-200 rounded-lg space-y-1">
                                    <span className="text-slate-400 font-medium">Formula & Karakteristik:</span>
                                    <div className="font-semibold text-slate-800 flex items-center gap-1.5 mt-0.5">
                                        <Calculator className="w-4 h-4 text-blue-600" />
                                        Tipe: {selectedIndikatorModal.tipe_perhitungan === 'naik_baik' ? 'Naik Baik' : 'Turun Baik'}
                                    </div>
                                    <div className="text-[11px] text-slate-500">Agregasi: {selectedIndikatorModal.jenis_agregasi} (Satuan: {selectedIndikatorModal.satuan})</div>
                                </div>
                            </div>

                            {/* Target Breakdown Table */}
                            <div>
                                <div className="text-xs font-semibold text-slate-700 mb-2">
                                    Target Kinerja Tahun {selectedIndikatorModal.tahun}
                                </div>
                                <div className="border border-slate-200 rounded-lg overflow-hidden text-xs">
                                    <table className="w-full text-center">
                                        <thead className="bg-slate-50 font-semibold border-b border-slate-200">
                                            <tr>
                                                <th className="py-2.5 px-3">Target Tahunan</th>
                                                <th className="py-2.5 px-3">TW 1</th>
                                                <th className="py-2.5 px-3">TW 2</th>
                                                <th className="py-2.5 px-3">TW 3</th>
                                                <th className="py-2.5 px-3">TW 4</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr className="font-medium text-slate-800">
                                                <td className="py-2.5 px-3 font-bold text-[#122E92]">
                                                    {selectedIndikatorModal.target_tahunan} {selectedIndikatorModal.satuan}
                                                </td>
                                                <td className="py-2.5 px-3">{selectedIndikatorModal.target_tw1}</td>
                                                <td className="py-2.5 px-3">{selectedIndikatorModal.target_tw2}</td>
                                                <td className="py-2.5 px-3">{selectedIndikatorModal.target_tw3}</td>
                                                <td className="py-2.5 px-3">{selectedIndikatorModal.target_tw4}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-[11px] text-blue-900 leading-relaxed">
                                <strong>Ketentuan SAKIP:</strong> Formula <em>{selectedIndikatorModal.tipe_perhitungan}</em> dievaluasi otomatis oleh kalkulator kinerja. Realisasi capaian triwulanan wajib disertai rencana aksi dan dokumen bukti dukung yang sah.
                            </div>

                            <div className="flex justify-end pt-2 border-t border-slate-100">
                                <Button
                                    variant="outline"
                                    onClick={() => setSelectedIndikatorModal(null)}
                                >
                                    Tutup
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
