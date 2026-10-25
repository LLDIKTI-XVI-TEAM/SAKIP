import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, FileText, MoreHorizontal, Pencil, Plus, Search } from 'lucide-react';
import { ActionMenu, type ActionMenuItem } from '@/Components/ActionMenu';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { HoverScrollText } from '@/Components/HoverScrollText';
import { Input } from '@/Components/Input';
import { RentangPeriode } from '@/Components/Periode/RentangPeriode';
import { Pagination } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import {
    statusKegiatan,
    statusKegiatanConfig,
    type KegiatanIndexItem,
    type KegiatanIndexProps,
    type StatusTujuan,
} from '@/types/kegiatan';
import { KegiatanModal } from './Partials/KegiatanModal';
import { StatusKegiatanModal, aksiStatusKegiatan } from './Partials/StatusKegiatanModal';

/** Item menu baris murni dari capability server; tanpa item, menu tidak dirender. */
function aksiBaris(item: KegiatanIndexItem, pilih: (status: StatusTujuan) => void): ActionMenuItem[] {
    const href = `/kegiatan/${item.id}/edit`;
    const buka: ActionMenuItem[] = item.can.ubah_rencana
        ? [{ key: 'ubah', label: 'Ubah', icon: Pencil, ariaLabel: `Ubah rencana kegiatan dan klaim rencana aksi ${item.nama}`, href }]
        : item.can.update ? [{ key: 'buka', label: 'Buka', icon: FileText, ariaLabel: `Buka kegiatan ${item.nama}`, href }] : [];

    return [...buka, ...item.can.ubah_status.map((status) => ({ key: status, ...aksiStatusKegiatan[status], onSelect: () => pilih(status) }))];
}

export default function Index({
    kegiatan = { data: [], links: [], from: null, to: null, total: 0 },
    filters = { tahun: '', unit_id: '', periode_id: '', status: '' },
    unitOptions = [],
    periodeOptions = [],
    creatableUnitOptions = [],
    activePeriodeOptions = [],
    tahunDefault = new Date().getFullYear(),
    can = { create: false },
}: KegiatanIndexProps) {
    const { tahun = '', unit_id = '', periode_id = '', status = '' } = filters;
    const [search, setSearch] = useState({ tahun: tahun || String(tahunDefault), unit_id, periode_id, status });
    // Redirect simpan (POST) mempertahankan state Inertia; ikuti filter yang benar-benar diterapkan server.
    useEffect(() => {
        setSearch({ tahun: tahun || String(tahunDefault), unit_id, periode_id, status });
    }, [tahun, unit_id, periode_id, status, tahunDefault]);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [pilihan, setPilihan] = useState<{ baris: KegiatanIndexItem; status: StatusTujuan } | null>(null);
    // Tujuan fokus bila baris keluar dari hasil filter setelah status berubah.
    const daftarRef = useRef<HTMLElement>(null);
    const formatTanggal = useFormatTanggal();

    const hasActiveFilters = Boolean(
        (search.tahun && String(search.tahun) !== String(tahunDefault)) ||
        search.unit_id ||
        search.periode_id ||
        search.status ||
        (filters.tahun && String(filters.tahun) !== String(tahunDefault)) ||
        filters.unit_id ||
        filters.periode_id ||
        filters.status
    );

    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/kegiatan', search, { replace: true, preserveState: false });
    };

    const resetFilters = () => {
        setSearch({ tahun: String(tahunDefault), unit_id: '', periode_id: '', status: '' });
        router.get('/kegiatan', { tahun: tahunDefault }, { replace: true, preserveState: false });
    };

    const openCreateModal = () => setIsModalOpen(true);
    const closeModal = () => setIsModalOpen(false);

    return (
        <AuthenticatedLayout
            title="Kegiatan"
            breadcrumbs={[{ label: 'Kegiatan' }]}
            headerActions={
                can?.create && (
                    <Button size="sm" onClick={openCreateModal} className="gap-1.5 translate-y-1 sm:translate-y-1.5">
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Buat kegiatan
                    </Button>
                )
            }
        >
            <Head title="Kegiatan" />

            <div className="space-y-5">
                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={apply} className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <Input
                                id="filter_tahun"
                                label="Tahun"
                                type="number"
                                min={1}
                                max={9999}
                                placeholder={`Contoh: ${tahunDefault}`}
                                value={search.tahun}
                                onChange={(event) => setSearch({ ...search, tahun: event.target.value })}
                            />
                            <Select
                                id="filter_unit_id"
                                label="Unit"
                                hoverScroll
                                hoverScrollDuration="5s"
                                value={search.unit_id}
                                onChange={(event) => setSearch({ ...search, unit_id: event.target.value })}
                                options={[
                                    { value: '', label: 'Semua unit' },
                                    ...unitOptions.map((option) => ({ value: option.id, label: option.nama })),
                                ]}
                            />
                            <Select
                                id="filter_periode_id"
                                label="Periode"
                                value={search.periode_id}
                                onChange={(event) => setSearch({ ...search, periode_id: event.target.value })}
                                options={[
                                    { value: '', label: 'Semua periode' },
                                    ...periodeOptions.map((option) => ({ value: option.id, label: option.nama })),
                                ]}
                            />
                            <Select
                                id="filter_status"
                                label="Status"
                                value={search.status}
                                onChange={(event) => setSearch({ ...search, status: event.target.value })}
                                options={[{ value: '', label: 'Semua status' }, ...statusKegiatan]}
                            />
                            <div className="flex items-center gap-2">
                                <Button type="submit" variant="outline" className="h-[42px] gap-1.5">
                                    <Search className="h-4 w-4" aria-hidden="true" />
                                    Terapkan
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="h-[42px]"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <section ref={daftarRef} aria-label="Daftar kegiatan" tabIndex={-1} className="focus:outline-none">
                    <Card>
                        <Table>
                            <TableCaption className="sr-only">Daftar kegiatan</TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kegiatan</TableHead>
                                    <TableHead className="w-1/4 min-w-[220px]">Unit</TableHead>
                                    <TableHead className="w-28 whitespace-nowrap">Tahun / Periode</TableHead>
                                    <TableHead className="w-32 whitespace-nowrap">Tanggal rencana</TableHead>
                                    <TableHead className="w-28 whitespace-nowrap">Status</TableHead>
                                    <TableHead className="w-16 whitespace-nowrap text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {kegiatan.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="px-5 py-12 text-center text-muted">
                                            <CalendarDays className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                            <p className="mt-2 text-sm font-semibold text-ink">
                                                Tidak ada data kegiatan
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {hasActiveFilters
                                                    ? 'Coba sesuaikan atau bersihkan kata kunci filter pencarian Anda.'
                                                    : 'Belum ada rencana kegiatan pada unit yang dapat Anda akses.'}
                                            </p>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    kegiatan.data.map((item) => {
                                        const statusConfig = statusKegiatanConfig[item.status] ?? {
                                            label: item.status,
                                            variant: 'muted' as const,
                                        };
                                        const aksi = aksiBaris(item, (status) => setPilihan({ baris: item, status }));

                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell className="max-w-md">
                                                    <span className="text-sm font-semibold text-ink">{item.nama}</span>
                                                    {item.tujuan && <p className="mt-1 line-clamp-2 text-xs text-muted" title={item.tujuan}>{item.tujuan}</p>}
                                                </TableCell>
                                                <TableCell className="w-1/4 min-w-[220px] text-xs font-medium text-ink">
                                                    <HoverScrollText showTooltip={false} duration="0.8s">{item.unit?.nama ?? '-'}</HoverScrollText>
                                                </TableCell>
                                                <TableCell className="w-28 whitespace-nowrap">
                                                    <span className="font-mono text-xs font-semibold text-ink">{item.tahun}</span>
                                                    <p className="text-xs text-muted">{item.periode.nama}</p>
                                                    <RentangPeriode tanggal={item.periode.tanggal} tahun={item.tahun} />
                                                </TableCell>
                                                <TableCell className="w-32 whitespace-nowrap text-xs text-muted">
                                                    {item.tanggal_rencana ? (
                                                        <Tooltip content={`Tanggal rencana: ${formatTanggal(item.tanggal_rencana)}`}>
                                                            <span>{formatTanggal(item.tanggal_rencana)}</span>
                                                        </Tooltip>
                                                    ) : (
                                                        <span>-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="w-28 whitespace-nowrap">
                                                    <Badge variant={statusConfig.variant}>{statusConfig.label}</Badge>
                                                </TableCell>
                                                <TableCell className="w-16 whitespace-nowrap text-right">
                                                    {aksi.length > 0 && (
                                                        <ActionMenu
                                                            items={aksi}
                                                            trigger={<MoreHorizontal className="h-4 w-4" aria-hidden="true" />}
                                                            triggerLabel={`Aksi kegiatan ${item.nama}`}
                                                            triggerClassName="h-8 w-8 px-0"
                                                        />
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                        <Pagination
                            {...kegiatan}
                            ariaLabel="Halaman kegiatan"
                            className="border-t border-border px-5 py-3.5"
                        />
                    </Card>
                </section>
            </div>

            <KegiatanModal
                isOpen={isModalOpen}
                onClose={closeModal}
                unitOptions={creatableUnitOptions}
                periodeOptions={activePeriodeOptions}
                tahunDefault={tahunDefault}
            />

            {pilihan && (
                <StatusKegiatanModal
                    key={`${pilihan.baris.id}:${pilihan.status}`}
                    kegiatan={{ ...pilihan.baris, periode: pilihan.baris.periode.nama }}
                    status={pilihan.status}
                    opsiPeriodeTujuan={pilihan.baris.opsi_periode_tujuan}
                    fokusCadangan={daftarRef}
                    onClose={() => setPilihan(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
