import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Search, Eye, Paperclip, RotateCcw } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { RentangPeriode } from '@/Components/Periode/RentangPeriode';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Tooltip } from '@/Components/Tooltip';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import type { Pengukuran, PengukuranPagination } from '@/Pages/Pengukuran/types';
import { StatusPerhitunganBadge } from '@/Components/Pengukuran/StatusPerhitunganBadge';
import Pagination from '@/Pages/Pengukuran/Pagination';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import Filters, { type FilterVerifikasi, type PilihanFilterTerpilih } from './Filters';

interface VerifikasiIndexProps {
    pengukurans: Pengukuran[];
    pagination: PengukuranPagination;
    filters?: FilterVerifikasi;
    selected_filters?: PilihanFilterTerpilih;
}

const defaultFilters: FilterVerifikasi = { status: 'diajukan', renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const defaultSelected: PilihanFilterTerpilih = { renstra_id: null, periode_id: null, unit_id: null };

export default function VerifikasiIndex({ pengukurans = [], pagination, filters = defaultFilters, selected_filters = defaultSelected }: VerifikasiIndexProps) {
    const formatNilai = useFormatNilai();
    const labelUnit = useLabelUnit();

    return (
        <AuthenticatedLayout
            title="Verifikasi & Pengesahan Kinerja"
            breadcrumbs={[{ label: 'Verifikasi Kinerja' }]}
            headerActions={
                <Link
                    href="/verifikasi/disahkan"
                    aria-label="Buka antrean pengukuran disahkan untuk koreksi"
                    className="ml-auto inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                >
                    <RotateCcw className="h-4 w-4" aria-hidden="true" />
                    Pengukuran Disahkan
                </Link>
            }
        >
            <Head title="Verifikasi Kinerja" />

            <Card>
                <Filters key={JSON.stringify([filters, selected_filters])} filters={filters} selected={selected_filters} />
                {pengukurans.length === 0 ? (
                    <div role="status" className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center justify-center">
                            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-soft text-muted border border-border">
                                <Search className="h-6 w-6" aria-hidden="true" />
                            </div>
                            <p className="text-sm font-semibold text-ink">Tidak ada pengukuran sesuai filter</p>
                            <p className="mt-1 text-xs text-muted max-w-sm">
                                {filters.status === 'diajukan' ? 'Belum ada pengajuan menunggu verifikasi untuk pilihan ini.' : 'Belum ada pengukuran terverifikasi yang siap disahkan untuk pilihan ini.'} Ubah atau reset filter untuk melihat antrean lain.
                            </p>
                        </div>
                    </div>
                ) : <Table className="min-w-max whitespace-nowrap">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="px-6 py-3.5 text-left">KODE & INDIKATOR</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">{labelUnit.toUpperCase()} & PIC</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">TARGET</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">REALISASI</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">HASIL PERHITUNGAN</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">BUKTI DUKUNG</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">STATUS</TableHead>
                            <TableHead className="px-6 py-3.5 text-left">AKSI</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {pengukurans.map((p) => {
                                const iku = p.penugasan_indikator?.indikator_kinerja;
                                const unit = p.penugasan_indikator?.unit_kerja;
                                const pic = p.penugasan_indikator?.pic;

                                return (
                                    <TableRow key={p.id} className="hover:bg-soft transition-colors">
                                        <TableCell className="px-6 py-4">
                                            <div className="font-bold text-ink text-xs">
                                                {iku?.kode}
                                            </div>
                                            <div className="text-muted mt-0.5 leading-relaxed">
                                                {iku?.nama}
                                            </div>
                                            <div className="mt-1 text-xs text-muted">{p.periode_jadwal.nama_periode} {p.periode_jadwal.tahun}<RentangPeriode tanggal={p.periode_jadwal.tanggal} tahun={p.periode_jadwal.tahun} /></div>
                                        </TableCell>
                                        <TableCell className="px-6 py-4">
                                            <div className="font-medium text-ink">{unit?.nama}</div>
                                            <div className="text-[11px] text-muted mt-0.5">
                                                PIC: {pic?.nama || '-'}
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-medium text-ink">
                                            {p.tanpa_target_periode ? 'Tanpa target periode' : p.target === null ? 'Belum tersedia' : `${formatNilai(p.target, iku.desimal_tampilan)} ${iku.satuan}`}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-right font-semibold text-ink">
                                            {p.nilai === null ? '—' : `${formatNilai(p.nilai, iku.desimal_tampilan)} ${iku.satuan}`}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-left font-semibold text-ink">
                                            <StatusPerhitunganBadge status={p.status_perhitungan} />
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-center">
                                            {p.bukti_count > 0 ? (
                                                <span className="inline-flex items-center gap-1 font-semibold text-primary">
                                                    <Paperclip className="w-3.5 h-3.5" />
                                                    {p.bukti_count} Dokumen
                                                </span>
                                            ) : (
                                                <span className="text-muted">Tanpa bukti</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-center">
                                            <div className="flex flex-col items-center gap-1">
                                                <Badge status={p.status} />
                                                {p.jalur_pengajuan && <span className="text-xs text-muted">{p.jalur_pengajuan === 'pic' ? 'Jalur PIC' : 'Jalur Perencanaan'}</span>}
                                                {p.self_approval && <span className="text-[11px] font-medium text-info-dark">Persetujuan sendiri</span>}
                                                {p.reviu_terlambat && <span className="text-[11px] font-medium text-warning-dark">Reviu terlambat</span>}
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-6 py-4 text-left">
                                            {p.can.view && (
                                                <Tooltip content="Lihat pengajuan" align="right">
                                                    <Link
                                                        href={`/verifikasi/${p.id}`}
                                                        aria-label={`Lihat pengajuan ${iku?.kode ?? 'pengukuran'}`}
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-surface text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                                                    >
                                                        <Eye aria-hidden="true" className="h-4 w-4" />
                                                    </Link>
                                                </Tooltip>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                    </TableBody>
                </Table>}
                <Pagination pagination={pagination} />
            </Card>
        </AuthenticatedLayout>
    );
}
