import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import { RotateCcw, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { StatusPerhitunganBadge } from '@/Components/Pengukuran/StatusPerhitunganBadge';
import {
    Table, TableActionLink, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive,
} from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { Pengukuran, PengukuranPagination } from '@/Pages/Pengukuran/types';
import Filters, { type FilterVerifikasi, type PilihanFilterTerpilih } from './Filters';

/**
 * Baris antrean disahkan memakai kontrak ringkas server: `versi_resmi` (metadata versi sah)
 * menggantikan `snapshot` yang hanya tersedia pada presenter detail.
 */
export type BarisDisahkan = Pengukuran & {
    versi_resmi?: { id: string; nomor: number; disahkan_pada: string | null } | null;
};

interface PengukuranDisahkanProps {
    pengukurans?: BarisDisahkan[];
    pagination: PengukuranPagination;
    filters?: FilterVerifikasi;
    selected_filters?: PilihanFilterTerpilih;
    /**
     * Capability server. Otorisasi buka kembali tetap ditegakkan server (403), sehingga
     * halaman tidak lagi bercabang berdasarkan prop ini.
     */
    can?: { buka_kembali: boolean };
}

const defaultFilters: FilterVerifikasi = { renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const defaultSelected: PilihanFilterTerpilih = { renstra_id: null, periode_id: null, unit_id: null };
const sel = `${tableCellResponsive} block align-top md:table-cell`;
const selKanan = `${sel} md:text-right`;

export default function PengukuranDisahkan({
    pengukurans = [],
    pagination,
    filters = defaultFilters,
    selected_filters = defaultSelected,
}: PengukuranDisahkanProps) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const labelUnit = useLabelUnit();
    const adaFilter = [filters.renstra_id, filters.tahun, filters.periode_id, filters.unit_id].some((value) => value !== '');

    const nilai = (p: Pengukuran): string => {
        if (p.nilai === null) return '—';
        const iku = p.penugasan_indikator.indikator_kinerja;
        const angka = formatNilai(p.nilai, iku.desimal_tampilan);

        return iku.satuan ? `${angka} ${iku.satuan}` : angka;
    };
    const periode = (p: Pengukuran): string => `${p.periode_jadwal.nama_periode} ${p.periode_jadwal.tahun}`;
    const versi = (p: BarisDisahkan): ReactNode => {
        // `versi_resmi` diutamakan; `snapshot` hanya fallback untuk payload lama yang masih membawanya.
        const resmi = p.versi_resmi ?? (p.snapshot
            ? { nomor: p.snapshot.nomor_pengajuan, disahkan_pada: p.snapshot.disahkan_pada }
            : null);

        if (!resmi) return <span className="font-normal text-muted">Belum ada versi sah</span>;

        return (
            <>
                Versi {resmi.nomor}
                {resmi.disahkan_pada && (
                    <span className="block text-xs font-normal text-muted">disahkan {formatTanggal(resmi.disahkan_pada)}</span>
                )}
            </>
        );
    };
    const halamanKosong = pagination.total > 0;

    return (
        <AuthenticatedLayout
            title="Pengukuran Disahkan"
            breadcrumbs={[{ label: 'Verifikasi Kinerja', href: '/verifikasi' }, { label: 'Pengukuran Disahkan' }]}
        >
            <Head title="Pengukuran Disahkan" />

            <div className="min-w-0 space-y-5">
                <p className="text-sm text-muted">
                    Antrean pengukuran yang telah disahkan. Koreksi dilakukan melalui buka kembali dari halaman detail pengukuran;
                    alasan wajib diisi dan tercatat pada audit log, sedangkan versi lama tetap tersimpan sebagai histori.
                </p>

                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-2 border-b border-border px-4 py-4 sm:px-5">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold text-ink">Antrean buka kembali</h2>
                            <p className="mt-0.5 text-xs text-muted">Buka detail pengukuran untuk melakukan koreksi resmi.</p>
                        </div>
                        <Badge variant="muted" size="sm">{pagination.total} pengukuran</Badge>
                    </div>

                    <Filters key={JSON.stringify([filters, selected_filters])} filters={filters} selected={selected_filters} showStatus={false} lookupUrl="/verifikasi/disahkan/pilihan" actionUrl="/verifikasi/disahkan" />

                    <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Daftar pengukuran yang telah disahkan">
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead className="whitespace-normal">KODE &amp; INDIKATOR</TableHead>
                                <TableHead>{labelUnit.toUpperCase()}</TableHead>
                                <TableHead>PERIODE</TableHead>
                                <TableHead>VERSI RESMI</TableHead>
                                <TableHead className="text-right">NILAI</TableHead>
                                <TableHead>STATUS</TableHead>
                                <TableHead className="text-right">AKSI</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {pengukurans.length === 0 ? (
                                <TableEmptyRow
                                    colSpan={7}
                                    icon={halamanKosong ? RotateCcw : Search}
                                    title={halamanKosong ? 'Tidak ada pengukuran di halaman ini' : 'Tidak ada pengukuran disahkan'}
                                    description={halamanKosong
                                        ? `Halaman ini tidak memuat pengukuran, tetapi masih ada ${pagination.total} pengukuran disahkan pada halaman lain. Gunakan tautan Sebelumnya di bawah untuk kembali ke halaman berisi pengukuran.`
                                        : adaFilter
                                            ? 'Tidak ada pengukuran disahkan yang sesuai filter ini. Ubah atau reset filter untuk melihat antrean lain.'
                                            : 'Belum ada pengukuran yang disahkan dan tersedia untuk koreksi.'}
                                />
                            ) : pengukurans.map((p) => {
                                const iku = p.penugasan_indikator.indikator_kinerja;

                                return (
                                    <TableRow key={p.id} className="block min-w-0 space-y-2 p-4 md:table-row md:space-y-0 md:p-0">
                                        <TableCell className={sel}>
                                            <p className="text-xs font-bold text-ink">{iku.kode}</p>
                                            <p className="mt-0.5 whitespace-normal break-words text-xs leading-relaxed text-muted">{iku.nama}</p>
                                        </TableCell>
                                        <TableCell className={sel}>
                                            <TableCellLabel>{labelUnit}</TableCellLabel>
                                            <span className="break-words text-xs text-ink">{p.penugasan_indikator.unit_kerja.nama}</span>
                                        </TableCell>
                                        <TableCell className={sel}>
                                            <TableCellLabel>Periode</TableCellLabel>
                                            <span className="text-xs text-ink">{periode(p)}</span>
                                        </TableCell>
                                        <TableCell className={sel}>
                                            {versi(p)}
                                        </TableCell>
                                        <TableCell className={selKanan}>
                                            <TableCellLabel>Nilai</TableCellLabel>
                                            <span className="block font-semibold tabular-nums text-ink">{nilai(p)}</span>
                                            {p.status_perhitungan !== 'terhitung' && (
                                                <span className="mt-1 block">
                                                    <StatusPerhitunganBadge status={p.status_perhitungan} size="sm" />
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className={sel}>
                                            <TableCellLabel>Status</TableCellLabel>
                                            <Badge status={p.status} />
                                        </TableCell>
                                        <TableCell className={selKanan}>
                                            <TableActionLink
                                                href={`/verifikasi/${p.id}`}
                                                label="Lihat detail untuk membuka kembali"
                                                tooltip="Lihat detail untuk membuka kembali"
                                                icon={RotateCcw}
                                            />
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    <Pagination pagination={pagination} label="pengukuran disahkan" />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
