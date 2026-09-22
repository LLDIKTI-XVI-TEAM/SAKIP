import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { 
    ListTodo, 
    Plus, 
    Search, 
    Filter, 
    Building2, 
    Calendar, 
    Clock, 
    CheckCircle2, 
    AlertCircle, 
    X, 
    Eye, 
    FileText, 
    ShieldCheck, 
    User as UserIcon,
    Layers,
    Send,
    ArrowRight,
    RotateCcw
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';

interface RencanaAksiItem {
    id: number | string;
    nama_rencana_aksi: string;
    uraian: string;
    tahun: number;
    indikator_kode: string;
    indikator_nama: string;
    unit_nama: string;
    unit_kode: string;
    unit_id?: string | null;
    penanggung_jawab_nama: string;
    status_alur: 'draft' | 'diajukan' | 'diverifikasi' | 'disahkan' | 'dikembalikan' | string;
    target_triwulan_1: string;
    target_triwulan_2: string;
    target_triwulan_3: string;
    target_triwulan_4: string;
    disahkan_at: string | null;
    disahkan_by_nama: string | null;
}

interface IndikatorOption {
    id: number | string;
    kode: string;
    nama: string;
}

interface UnitOption {
    id: number | string;
    kode: string;
    nama: string;
}

interface RencanaAksiIndexProps {
    rencanaAksiList: RencanaAksiItem[];
    indikatorOptions: IndikatorOption[];
    unitOptions: UnitOption[];
    tahunAktif: number;
    can: {
        create: boolean;
        verify: boolean;
        ratify: boolean;
    };
}

export default function RencanaAksiIndex({
    rencanaAksiList,
    indikatorOptions,
    unitOptions,
    tahunAktif,
    can,
}: RencanaAksiIndexProps) {
    const [search, setSearch] = useState('');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');
    const [selectedUnit, setSelectedUnit] = useState<string>('all');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [selectedRaModal, setSelectedRaModal] = useState<RencanaAksiItem | null>(null);
    const [mockNotification, setMockNotification] = useState<string | null>(null);

    const filteredList = useMemo(() => {
        return rencanaAksiList.filter((item) => {
            const matchSearch =
                (item.nama_rencana_aksi || '').toLowerCase().includes(search.toLowerCase()) ||
                (item.indikator_kode || '').toLowerCase().includes(search.toLowerCase()) ||
                (item.indikator_nama || '').toLowerCase().includes(search.toLowerCase()) ||
                (item.unit_nama || '').toLowerCase().includes(search.toLowerCase()) ||
                (item.uraian || '').toLowerCase().includes(search.toLowerCase());

            const matchStatus =
                selectedStatus === 'all' || item.status_alur === selectedStatus;

            const matchUnit =
                selectedUnit === 'all' ||
                item.unit_kode === selectedUnit ||
                (Boolean(item.unit_id) && item.unit_id === selectedUnit) ||
                (Boolean(item.unit_kode) && Boolean(selectedUnit) && item.unit_kode.toLowerCase() === selectedUnit.toLowerCase());

            return matchSearch && matchStatus && matchUnit;
        });
    }, [rencanaAksiList, search, selectedStatus, selectedUnit]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'disahkan':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                        Disahkan
                    </span>
                );
            case 'diverifikasi':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                        <Clock className="w-3.5 h-3.5 text-amber-600" />
                        Diverifikasi
                    </span>
                );
            case 'diajukan':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                        <Send className="w-3.5 h-3.5 text-blue-600" />
                        Diajukan
                    </span>
                );
            case 'dikembalikan':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200">
                        <RotateCcw className="w-3.5 h-3.5 text-rose-600" />
                        Dikembalikan
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        <FileText className="w-3.5 h-3.5 text-slate-500" />
                        Draft
                    </span>
                );
        }
    };

    const handleMockSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsCreateModalOpen(false);
        setMockNotification('Mockup: Dokumen Rencana Aksi berhasil disusun dan disimpan sebagai Draft.');
        setTimeout(() => setMockNotification(null), 4000);
    };

    return (
        <AuthenticatedLayout
            title="Rencana Aksi Kinerja"
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Perencanaan Kinerja' },
                { label: 'Rencana Aksi (RA)' },
            ]}
        >
            <Head title="Rencana Aksi Kinerja - SAKIP LLDIKTI XVI" />

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
                            <ListTodo className="w-6 h-6 text-[#D6AC48]" />
                            <h1 className="text-xl font-bold tracking-tight">
                                Rencana Aksi (RA) Kinerja Tahunan
                            </h1>
                        </div>
                        <p className="text-sm text-blue-100/90 max-w-2xl">
                            Rangkaian kegiatan dan tahapan strategis pencapaian IKU per triwulan. Rencana Aksi yang telah disahkan menjadi prasyarat pembukaan pengisian capaian kinerja.
                        </p>
                    </div>

                    <Button
                        onClick={() => setIsCreateModalOpen(true)}
                        className="bg-[#D6AC48] hover:bg-[#c49a37] text-slate-900 font-semibold shadow-sm shrink-0 flex items-center gap-2"
                    >
                        <Plus className="w-4 h-4" />
                        Susun Rencana Aksi
                    </Button>
                </div>

                {/* SAKIP Workflow Phase Visualizer */}
                <Card className="border-slate-200 bg-slate-50/50">
                    <CardContent className="p-4">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                            Alur Pengesahan Rencana Aksi SAKIP:
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
                            <div className="p-2.5 rounded-lg bg-white border border-slate-200 flex items-center gap-2 shadow-2xs">
                                <div className="w-6 h-6 rounded-full bg-slate-100 font-bold text-slate-700 flex items-center justify-center shrink-0">
                                    1
                                </div>
                                <div>
                                    <div className="font-semibold text-slate-800">Penyusunan PIC</div>
                                    <div className="text-[11px] text-slate-400">Tahapan & target triwulan</div>
                                </div>
                            </div>

                            <div className="p-2.5 rounded-lg bg-white border border-blue-200 flex items-center gap-2 shadow-2xs">
                                <div className="w-6 h-6 rounded-full bg-blue-100 font-bold text-blue-800 flex items-center justify-center shrink-0">
                                    2
                                </div>
                                <div>
                                    <div className="font-semibold text-blue-900">Pengajuan Resmi</div>
                                    <div className="text-[11px] text-blue-500">Diajukan ke Perencanaan</div>
                                </div>
                            </div>

                            <div className="p-2.5 rounded-lg bg-white border border-amber-200 flex items-center gap-2 shadow-2xs">
                                <div className="w-6 h-6 rounded-full bg-amber-100 font-bold text-amber-800 flex items-center justify-center shrink-0">
                                    3
                                </div>
                                <div>
                                    <div className="font-semibold text-amber-900">Verifikasi Reviu</div>
                                    <div className="text-[11px] text-amber-500">Reviu kualitas target</div>
                                </div>
                            </div>

                            <div className="p-2.5 rounded-lg bg-white border border-emerald-200 flex items-center gap-2 shadow-2xs">
                                <div className="w-6 h-6 rounded-full bg-emerald-100 font-bold text-emerald-800 flex items-center justify-center shrink-0">
                                    4
                                </div>
                                <div>
                                    <div className="font-semibold text-emerald-900">Pengesahan Akhir</div>
                                    <div className="text-[11px] text-emerald-500">Kunci gerbang pengukuran</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Filter & Kontrol */}
                <Card className="border-slate-200">
                    <CardContent className="p-4">
                        <div className="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                            <div className="flex-1 relative">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <Input
                                    type="text"
                                    placeholder="Cari nama rencana aksi, IKU terkait, atau unit..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 text-sm"
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Status:</span>
                                    <select
                                        value={selectedStatus}
                                        onChange={(e) => setSelectedStatus(e.target.value)}
                                        className="text-xs rounded-md border-slate-200 py-1.5 px-2.5 bg-white text-slate-700 focus:border-[#122E92] focus:ring-[#122E92]"
                                    >
                                        <option value="all">Semua Status</option>
                                        <option value="draft">Draft</option>
                                        <option value="diajukan">Diajukan</option>
                                        <option value="diverifikasi">Diverifikasi</option>
                                        <option value="disahkan">Disahkan</option>
                                        <option value="dikembalikan">Dikembalikan</option>
                                    </select>
                                </div>

                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-slate-500 font-medium whitespace-nowrap">Unit:</span>
                                    <select
                                        value={selectedUnit}
                                        onChange={(e) => setSelectedUnit(e.target.value)}
                                        className="text-xs rounded-md border-slate-200 py-1.5 px-2.5 bg-white text-slate-700 focus:border-[#122E92] focus:ring-[#122E92]"
                                    >
                                        <option value="all">Semua Unit</option>
                                        {unitOptions.map((u) => (
                                            <option key={u.id} value={u.kode}>
                                                {u.nama}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Daftar Kartu Rencana Aksi */}
                <div className="space-y-4">
                    {filteredList.length === 0 ? (
                        <Card className="border-slate-200">
                            <CardContent className="p-12 text-center text-slate-400">
                                Tidak ada Rencana Aksi yang sesuai dengan kriteria filter.
                            </CardContent>
                        </Card>
                    ) : (
                        filteredList.map((ra) => (
                            <Card key={ra.id} className="border-slate-200 hover:border-blue-200 shadow-xs transition-all">
                                <CardContent className="p-6">
                                    <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                                        <div className="space-y-2.5 flex-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="px-2 py-0.5 bg-[#122E92] text-white font-mono text-xs font-bold rounded">
                                                    {ra.indikator_kode}
                                                </span>
                                                <span className="text-xs font-medium text-slate-600">
                                                    {ra.indikator_nama}
                                                </span>
                                                <div className="ml-auto lg:ml-2">
                                                    {getStatusBadge(ra.status_alur)}
                                                </div>
                                            </div>

                                            <h3 className="text-base font-bold text-slate-900 leading-snug">
                                                {ra.nama_rencana_aksi}
                                            </h3>

                                            <p className="text-sm text-slate-600 leading-relaxed max-w-4xl">
                                                {ra.uraian}
                                            </p>

                                            <div className="flex items-center gap-4 text-xs text-slate-500 pt-1 flex-wrap">
                                                <div className="flex items-center gap-1.5 font-medium text-slate-700">
                                                    <Building2 className="w-3.5 h-3.5 text-[#D6AC48]" />
                                                    {ra.unit_nama}
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <UserIcon className="w-3.5 h-3.5 text-slate-400" />
                                                    PIC: {ra.penanggung_jawab_nama}
                                                </div>
                                                {ra.disahkan_at && (
                                                    <div className="flex items-center gap-1.5 text-emerald-700 font-medium">
                                                        <ShieldCheck className="w-3.5 h-3.5" />
                                                        Disahkan oleh {ra.disahkan_by_nama} pada {ra.disahkan_at}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className="shrink-0 flex items-center lg:flex-col gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setSelectedRaModal(ra)}
                                                className="text-xs flex items-center gap-1.5 w-full justify-center"
                                            >
                                                <Eye className="w-3.5 h-3.5" />
                                                Detail Tahapan
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Mini Quarter Steps Grid */}
                                    <div className="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="font-bold text-[#122E92] mb-1">Triwulan I</div>
                                            <div className="text-slate-600 line-clamp-2">{ra.target_triwulan_1}</div>
                                        </div>
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="font-bold text-[#122E92] mb-1">Triwulan II</div>
                                            <div className="text-slate-600 line-clamp-2">{ra.target_triwulan_2}</div>
                                        </div>
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="font-bold text-[#122E92] mb-1">Triwulan III</div>
                                            <div className="text-slate-600 line-clamp-2">{ra.target_triwulan_3}</div>
                                        </div>
                                        <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                            <div className="font-bold text-[#122E92] mb-1">Triwulan IV</div>
                                            <div className="text-slate-600 line-clamp-2">{ra.target_triwulan_4}</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>

            {/* MOCK MODAL: Susun Rencana Aksi Baru */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-[#122E92] text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ListTodo className="w-5 h-5 text-[#D6AC48]" />
                                <h3 className="font-semibold text-base">Susun Rencana Aksi Baru ({tahunAktif})</h3>
                            </div>
                            <button onClick={() => setIsCreateModalOpen(false)} className="text-white/80 hover:text-white">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleMockSubmit} className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Pilih Indikator Kinerja Utama (IKU) <span className="text-red-500">*</span>
                                </label>
                                <select className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]" required>
                                    <option value="">-- Pilih Indikator Terkait --</option>
                                    {indikatorOptions.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.kode} - {i.nama}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Judul Rencana Aksi <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Contoh: Bimbingan Teknis & Klinik Akreditasi PTS..."
                                    required
                                    className="text-sm"
                                    defaultValue="Workshop Penguatan SPMI dan Pendampingan Tata Kelola Akademik PTS"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Deskripsi & Strategi Pelaksanaan <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    rows={2}
                                    placeholder="Uraikan strategi pencapaian dan aktivitas utama..."
                                    className="w-full text-sm rounded-md border-slate-300 focus:border-[#122E92] focus:ring-[#122E92]"
                                    required
                                    defaultValue="Melatih tim penjaminan mutu internal (SPMI) pada 20 PTS percontohan untuk memenuhi standar akreditasi LAM/BAN-PT."
                                />
                            </div>

                            <div className="space-y-2 pt-2 border-t border-slate-100">
                                <span className="text-xs font-bold text-slate-800">Target Aktivitas Per Triwulan:</span>
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Triwulan I</label>
                                    <Input type="text" defaultValue="Penyusunan modul SPMI & pemetaan kebutuhan PTS" className="text-xs" />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Triwulan II</label>
                                    <Input type="text" defaultValue="Pelaksanaan pelatihan SPMI Tahap 1" className="text-xs" />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Triwulan III</label>
                                    <Input type="text" defaultValue="Pendampingan audit mutu internal (AMI) lapangan" className="text-xs" />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-semibold text-slate-600 mb-0.5">Triwulan IV</label>
                                    <Input type="text" defaultValue="Evaluasi peningkatan skor SPMI dan pelaporan akhir" className="text-xs" />
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsCreateModalOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    className="bg-[#122E92] hover:bg-[#0d226b] text-white font-semibold"
                                >
                                    Simpan Rencana Aksi (Mockup)
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL: Detail Rencana Aksi & Tahapan Aktivitas */}
            {selectedRaModal && (
                <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
                        <div className="px-6 py-4 bg-[#122E92] text-white flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ListTodo className="w-5 h-5 text-[#D6AC48]" />
                                <h3 className="font-semibold text-base">Detail Rencana Aksi</h3>
                            </div>
                            <button
                                onClick={() => setSelectedRaModal(null)}
                                className="text-white/80 hover:text-white"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div className="flex items-center justify-between gap-2 flex-wrap">
                                <span className="font-mono text-xs font-bold bg-blue-50 text-[#122E92] px-2.5 py-1 rounded border border-blue-200">
                                    {selectedRaModal.indikator_kode}
                                </span>
                                {getStatusBadge(selectedRaModal.status_alur)}
                            </div>

                            <div>
                                <h4 className="text-base font-bold text-slate-900">
                                    {selectedRaModal.nama_rencana_aksi}
                                </h4>
                                <p className="text-xs text-slate-500 mt-1">
                                    Indikator: {selectedRaModal.indikator_nama}
                                </p>
                            </div>

                            <div className="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs">
                                <div className="font-semibold text-slate-700 mb-1">Uraian Strategi:</div>
                                <p className="text-slate-600 leading-relaxed">{selectedRaModal.uraian}</p>
                            </div>

                            <div className="grid grid-cols-2 gap-3 text-xs">
                                <div className="p-3 border border-slate-200 rounded-lg">
                                    <span className="text-slate-400">Unit Organisasi:</span>
                                    <div className="font-semibold text-slate-800 mt-0.5">{selectedRaModal.unit_nama}</div>
                                </div>
                                <div className="p-3 border border-slate-200 rounded-lg">
                                    <span className="text-slate-400">PIC Penanggung Jawab:</span>
                                    <div className="font-semibold text-slate-800 mt-0.5">{selectedRaModal.penanggung_jawab_nama}</div>
                                </div>
                            </div>

                            {/* Detail 4 Quarters Breakdown */}
                            <div className="space-y-2 pt-2 border-t border-slate-100">
                                <div className="text-xs font-bold text-slate-800">Target Pelaksanaan Tiap Triwulan:</div>
                                <div className="space-y-2 text-xs">
                                    <div className="p-3 rounded-lg bg-blue-50/50 border border-blue-100">
                                        <strong className="text-[#122E92]">Triwulan I:</strong>
                                        <p className="text-slate-700 mt-0.5">{selectedRaModal.target_triwulan_1}</p>
                                    </div>
                                    <div className="p-3 rounded-lg bg-blue-50/50 border border-blue-100">
                                        <strong className="text-[#122E92]">Triwulan II:</strong>
                                        <p className="text-slate-700 mt-0.5">{selectedRaModal.target_triwulan_2}</p>
                                    </div>
                                    <div className="p-3 rounded-lg bg-blue-50/50 border border-blue-100">
                                        <strong className="text-[#122E92]">Triwulan III:</strong>
                                        <p className="text-slate-700 mt-0.5">{selectedRaModal.target_triwulan_3}</p>
                                    </div>
                                    <div className="p-3 rounded-lg bg-blue-50/50 border border-blue-100">
                                        <strong className="text-[#122E92]">Triwulan IV:</strong>
                                        <p className="text-slate-700 mt-0.5">{selectedRaModal.target_triwulan_4}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end pt-2 border-t border-slate-100">
                                <Button
                                    variant="outline"
                                    onClick={() => setSelectedRaModal(null)}
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
