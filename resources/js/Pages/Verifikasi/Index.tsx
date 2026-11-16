import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Search, Eye, RotateCcw } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { RentangPeriode } from '@/Components/Periode/RentangPeriode';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { TabTautan } from '@/Components/TabTautan';
import {
    Table, TableActionLink, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive,
} from '@/Components/Table';
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
    /** Jumlah pengukuran tiap antrean pada filter yang diterapkan; dihitung server dengan scope izin yang sama. */
    jumlah?: Record<StatusAntrean, number>;
    /** Capability tautan antrean Disahkan; dihitung server dari izin baca dan buka kembali. */
    can?: { buka_kembali?: boolean };
}

type StatusAntrean = 'diajukan' | 'diverifikasi';

const antrean: { status: StatusAntrean; label: string }[] = [
    { status: 'diajukan', label: 'Menunggu verifikasi' },
    { status: 'diverifikasi', label: 'Siap disahkan' },
];
const sel = `${tableCellResponsive} block align-top md:table-cell`;
const selKanan = `${sel} md:text-right`;
const defaultFilters: FilterVerifikasi = { status: 'diajukan', renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const defaultSelected: PilihanFilterTerpilih = { renstra_id: null, periode_id: null, unit_id: null };

export default function VerifikasiIndex({ pengukurans = [], pagination, filters = defaultFilters, selected_filters = defaultSelected, jumlah = { diajukan: 0, diverifikasi: 0 }, can }: VerifikasiIndexProps) {
    const formatNilai = useFormatNilai();
    const labelUnit = useLabelUnit();
    const statusAktif = filters.status ?? 'diajukan';
    // Pindah antrean membawa filter yang diterapkan, tanpa nomor halaman antrean sebelumnya.
    const tautanAntrean = (status: StatusAntrean): string => {
        const params = new URLSearchParams({ status });
        for (const kunci of ['renstra_id', 'tahun', 'periode_id', 'unit_id'] as const) {
            if (filters[kunci] !== '') params.set(kunci, filters[kunci]);
        }

        return `/verifikasi?${params}`;
    };

    return (
        <AuthenticatedLayout
            title="Verifikasi & Pengesahan Kinerja"
            breadcrumbs={[{ label: 'Verifikasi Kinerja' }]}
            headerActions={can?.buka_kembali === true ? (
                <Link
                    href="/verifikasi/disahkan"
                    aria-label="Buka antrean pengukuran disahkan untuk koreksi"
                    className="ml-auto inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                >
                    <RotateCcw className="h-4 w-4" aria-hidden="true" />
                    Pengukuran Disahkan
                </Link>
            ) : undefined}
        >
            <Head title="Verifikasi Kinerja" />

            <Card>
                <TabTautan
                    label="Status antrean"
                    satuan="pengukuran"
                    items={antrean.map(({ status, label }) => ({ href: tautanAntrean(status), label, jumlah: jumlah[status], aktif: statusAktif === status }))}
                />
                <Filters key={JSON.stringify([filters, selected_filters])} filters={filters} selected={selected_filters} />
                <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Antrean verifikasi pengukuran">
                    <TableHeader className="max-md:sr-only">
                        <TableRow>
                            <TableHead className="whitespace-normal">KODE &amp; INDIKATOR</TableHead>
                            <TableHead>{labelUnit.toUpperCase()}</TableHead>
                            <TableHead>PERIODE</TableHead>
                            <TableHead className="text-right">REALISASI</TableHead>
                            <TableHead>STATUS</TableHead>
                            <TableHead className="text-right">AKSI</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="block md:table-row-group">
                        {pengukurans.length === 0 ? (
                            <TableEmptyRow
                                colSpan={6}
                                icon={Search}
                                title="Tidak ada pengukuran sesuai filter"
                                description={`${statusAktif === 'diajukan' ? 'Belum ada pengajuan menunggu verifikasi untuk pilihan ini.' : 'Belum ada pengukuran terverifikasi yang siap disahkan untuk pilihan ini.'} Ubah atau reset filter untuk melihat antrean lain.`}
                            />
                        ) : pengukurans.map((p) => {
                            const iku = p.penugasan_indikator.indikator_kinerja;
                            const angka = p.nilai === null ? '-' : formatNilai(p.nilai, iku.desimal_tampilan);

                            return (
                                <TableRow key={p.id} className="block min-w-0 space-y-2 p-4 md:table-row md:space-y-0 md:p-0">
                                    <TableCell className={sel}>
                                        <p className="text-xs font-bold text-ink">{iku.kode}</p>
                                        {/* Rincian lain (PIC, target, bukti, jalur) dibaca di halaman detail agar baris tetap ringkas. */}
                                        <p title={iku.nama} className="mt-0.5 line-clamp-2 whitespace-normal break-words text-xs leading-relaxed text-muted">{iku.nama}</p>
                                    </TableCell>
                                    <TableCell className={sel}>
                                        <TableCellLabel>{labelUnit}</TableCellLabel>
                                        <span className="break-words text-xs text-ink">{p.penugasan_indikator.unit_kerja.nama}</span>
                                    </TableCell>
                                    <TableCell className={sel}>
                                        <TableCellLabel>Periode</TableCellLabel>
                                        <span className="text-xs text-ink md:whitespace-nowrap">{p.periode_jadwal.nama_periode} {p.periode_jadwal.tahun}<RentangPeriode tanggal={p.periode_jadwal.tanggal} tahun={p.periode_jadwal.tahun} /></span>
                                    </TableCell>
                                    <TableCell className={selKanan}>
                                        <TableCellLabel>Realisasi</TableCellLabel>
                                        <span className="font-semibold tabular-nums text-ink md:whitespace-nowrap">{p.nilai !== null && iku.satuan ? `${angka} ${iku.satuan}` : angka}</span>
                                        {p.status_perhitungan !== 'terhitung' && (
                                            <span className="mt-1 block">
                                                <StatusPerhitunganBadge status={p.status_perhitungan} size="sm" />
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className={sel}>
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 md:flex-col md:items-start md:whitespace-nowrap">
                                            <Badge status={p.status} />
                                            {p.reviu_terlambat && <span className="text-xs font-medium text-warning-dark">Reviu terlambat</span>}
                                        </div>
                                    </TableCell>
                                    <TableCell className={selKanan}>
                                        {p.can.view && (
                                            <TableActionLink
                                                href={`/verifikasi/${p.id}`}
                                                label={`Lihat pengajuan ${iku.kode}`}
                                                tooltip="Lihat pengajuan"
                                                icon={Eye}
                                            />
                                        )}
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
                <Pagination pagination={pagination} />
            </Card>
        </AuthenticatedLayout>
    );
}
