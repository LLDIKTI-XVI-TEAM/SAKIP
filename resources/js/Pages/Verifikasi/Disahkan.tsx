import type { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { RotateCcw, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { StatusPerhitunganBadge } from '@/Components/Pengukuran/StatusPerhitunganBadge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
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
    can?: { buka_kembali: boolean };
}

const defaultFilters: FilterVerifikasi = { renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const defaultSelected: PilihanFilterTerpilih = { renstra_id: null, periode_id: null, unit_id: null };
const tautanPaginasi = 'inline-flex items-center rounded-lg border border-border bg-surface px-3 py-2 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';
const tautanAksi = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';

function Data({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-muted">{label}</dt>
            <dd className="mt-0.5 break-words text-xs font-semibold text-ink">{children}</dd>
        </div>
    );
}

export default function PengukuranDisahkan({
    pengukurans = [],
    pagination,
    filters = defaultFilters,
    selected_filters = defaultSelected,
    can,
}: PengukuranDisahkanProps) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const labelUnit = useLabelUnit();
    // Capability tetap berasal dari server; absennya prop diperlakukan sebagai tanpa izin.
    const dapatBukaKembali = can?.buka_kembali === true;
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
    const aksi = (p: Pengukuran): ReactNode => {
        const iku = p.penugasan_indikator.indikator_kinerja;
        if (!dapatBukaKembali) return <span className="text-xs font-normal text-muted">Hanya baca</span>;

        return (
            <Link
                href={`/verifikasi/${p.id}`}
                aria-label={`Buka kembali pengukuran ${iku.kode} periode ${periode(p)}`}
                className={tautanAksi}
            >
                <RotateCcw className="h-3.5 w-3.5" aria-hidden="true" />
                Buka Kembali
            </Link>
        );
    };

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

                {!dapatBukaKembali && (
                    <div role="status" className="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
                        Anda tidak memiliki izin membuka kembali pengukuran. Halaman ini hanya dapat dibaca.
                    </div>
                )}

                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-2 border-b border-border px-4 py-4 sm:px-5">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold text-ink">Antrean buka kembali</h2>
                            <p className="mt-0.5 text-xs text-muted">Buka detail pengukuran untuk melakukan koreksi resmi.</p>
                        </div>
                        <Badge variant="muted" size="sm">{pagination.total} pengukuran</Badge>
                    </div>

                    <Filters key={JSON.stringify([filters, selected_filters])} filters={filters} selected={selected_filters} showStatus={false} lookupUrl="/verifikasi/disahkan/pilihan" actionUrl="/verifikasi/disahkan" />

                    {pengukurans.length === 0 ? (
                        <div role="status" className="px-5 py-10 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full border border-border bg-soft text-muted">
                                <Search className="h-6 w-6" aria-hidden="true" />
                            </div>
                            {pagination.total === 0 ? (
                                <>
                                    <p className="mt-3 text-sm font-semibold text-ink">Tidak ada pengukuran disahkan</p>
                                    <p className="mx-auto mt-1 max-w-md text-xs text-muted">
                                        {adaFilter
                                            ? 'Tidak ada pengukuran disahkan yang sesuai filter ini. Ubah atau reset filter untuk melihat antrean lain.'
                                            : 'Belum ada pengukuran yang disahkan dan tersedia untuk koreksi.'}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="mt-3 text-sm font-semibold text-ink">Tidak ada pengukuran di halaman ini</p>
                                    <p className="mx-auto mt-1 max-w-md text-xs text-muted">
                                        Halaman ini tidak memuat pengukuran, tetapi masih ada {pagination.total} pengukuran disahkan pada halaman lain.
                                        Gunakan tautan Sebelumnya di bawah untuk kembali ke halaman berisi pengukuran.
                                    </p>
                                </>
                            )}
                        </div>
                    ) : (
                        <>
                            <Table wrapperClassName="hidden md:block" aria-label="Daftar pengukuran yang telah disahkan">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-3 py-3">KODE &amp; INDIKATOR</TableHead>
                                        <TableHead className="px-3 py-3">{labelUnit.toUpperCase()}</TableHead>
                                        <TableHead className="px-3 py-3">PERIODE</TableHead>
                                        <TableHead className="px-3 py-3">VERSI RESMI</TableHead>
                                        <TableHead className="px-3 py-3 text-right">NILAI</TableHead>
                                        <TableHead className="px-3 py-3">STATUS</TableHead>
                                        <TableHead className="px-3 py-3 text-right">AKSI</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pengukurans.map((p) => {
                                        const iku = p.penugasan_indikator.indikator_kinerja;

                                        return (
                                            <TableRow key={p.id} className="hover:bg-soft transition-colors">
                                                <TableCell className="max-w-xs px-3 py-3 align-top">
                                                    <p className="text-xs font-bold text-ink">{iku.kode}</p>
                                                    <p className="mt-0.5 whitespace-normal break-words text-xs leading-relaxed text-muted">{iku.nama}</p>
                                                </TableCell>
                                                <TableCell className="px-3 py-3 align-top text-xs text-ink">{p.penugasan_indikator.unit_kerja.nama}</TableCell>
                                                <TableCell className="px-3 py-3 align-top text-xs text-ink">{periode(p)}</TableCell>
                                                <TableCell className="px-3 py-3 align-top text-xs text-ink">{versi(p)}</TableCell>
                                                <TableCell className="whitespace-nowrap px-3 py-3 text-right align-top tabular-nums text-ink">
                                                    <span className="block font-semibold">{nilai(p)}</span>
                                                    {p.status_perhitungan !== 'terhitung' && (
                                                        <span className="mt-1 block">
                                                            <StatusPerhitunganBadge status={p.status_perhitungan} size="sm" />
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-3 py-3 align-top">
                                                    <Badge status={p.status} />
                                                </TableCell>
                                                <TableCell className="px-3 py-3 text-right align-top">{aksi(p)}</TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>

                            <ul aria-label="Daftar pengukuran disahkan" className="divide-y divide-border md:hidden">
                                {pengukurans.map((p) => {
                                    const iku = p.penugasan_indikator.indikator_kinerja;

                                    return (
                                        <li key={p.id} className="space-y-3 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="text-xs font-bold text-primary">{iku.kode}</p>
                                                    <p className="mt-0.5 break-words text-sm font-semibold leading-snug text-ink">{iku.nama}</p>
                                                </div>
                                                <Badge status={p.status} size="sm" className="shrink-0" />
                                            </div>
                                            <dl className="grid grid-cols-2 gap-3">
                                                <Data label={labelUnit}>{p.penugasan_indikator.unit_kerja.nama}</Data>
                                                <Data label="Periode">{periode(p)}</Data>
                                                <Data label="Versi resmi">{versi(p)}</Data>
                                                <Data label="Nilai">
                                                    <span className="block">{nilai(p)}</span>
                                                    {p.status_perhitungan !== 'terhitung' && (
                                                        <span className="mt-1 block">
                                                            <StatusPerhitunganBadge status={p.status_perhitungan} size="sm" />
                                                        </span>
                                                    )}
                                                </Data>
                                            </dl>
                                            {dapatBukaKembali && (
                                                <Link
                                                    href={`/verifikasi/${p.id}`}
                                                    aria-label={`Buka kembali pengukuran ${iku.kode} periode ${periode(p)}`}
                                                    className={`${tautanAksi} w-full`}
                                                >
                                                    <RotateCcw className="h-3.5 w-3.5" aria-hidden="true" />
                                                    Buka Kembali
                                                </Link>
                                            )}
                                            {!dapatBukaKembali && <p className="text-xs font-normal text-muted">Hanya baca</p>}
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    )}

                    {pagination.last_page > 1 && (
                        <nav aria-label="Halaman pengukuran disahkan" className="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3">
                            {pagination.prev_page_url
                                ? <Link href={pagination.prev_page_url} preserveScroll className={tautanPaginasi}>Sebelumnya</Link>
                                : <span aria-hidden="true" />}
                            <p className="text-xs text-muted">Halaman {pagination.current_page} dari {pagination.last_page} · {pagination.total} baris</p>
                            {pagination.next_page_url
                                ? <Link href={pagination.next_page_url} preserveScroll className={tautanPaginasi}>Berikutnya</Link>
                                : <span aria-hidden="true" />}
                        </nav>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
