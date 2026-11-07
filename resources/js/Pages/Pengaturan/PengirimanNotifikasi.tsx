import { Head, Link, useForm } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge, type BadgeVariant } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { Table, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive } from '@/Components/Table';

type Status = 'antre' | 'gagal' | 'dibatalkan' | 'diterima_provider' | 'perlu_rekonsiliasi';
type KodeHasil = 'kanal_nonaktif' | 'penerima_tidak_berhak' | 'kontak_tidak_tersedia' | 'perlu_rekonsiliasi' | 'provider_sementara' | 'provider_ditolak' | 'percobaan_habis';

interface FilterPengiriman {
    status: string;
    kanal: string;
    mulai: string;
    selesai: string;
}

export interface PengirimanNotifikasiProps {
    filters: FilterPengiriman;
    pengiriman: {
        data: {
            id: string;
            status: Status;
            label_status: string;
            kanal: 'email' | 'whatsapp';
            penerima_nama: string | null;
            simulasi: boolean;
            kode_hasil: KodeHasil | null;
            percobaan: number;
            created_at: string;
            dicoba_terakhir_pada: string | null;
        }[];
        links: PaginationLinkItem[];
        total: number;
        from: number | null;
        to: number | null;
    };
}

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'antre', label: 'Dalam antrean' },
    { value: 'diterima_provider', label: 'Diterima provider / simulasi' },
    { value: 'dibatalkan', label: 'Dibatalkan' },
    { value: 'gagal', label: 'Gagal' },
    { value: 'perlu_rekonsiliasi', label: 'Perlu rekonsiliasi' },
];

const statusBadge: Record<Status, BadgeVariant> = {
    antre: 'info',
    gagal: 'danger',
    dibatalkan: 'muted',
    diterima_provider: 'success',
    perlu_rekonsiliasi: 'warning',
};

const keteranganHasil: Record<KodeHasil, string> = {
    kanal_nonaktif: 'Kanal nonaktif',
    penerima_tidak_berhak: 'Penerima tidak memenuhi syarat',
    kontak_tidak_tersedia: 'Kontak belum tersedia atau tidak valid',
    perlu_rekonsiliasi: 'Hasil belum dapat dipastikan',
    provider_sementara: 'Gangguan sementara pada layanan',
    provider_ditolak: 'Ditolak layanan pengiriman',
    percobaan_habis: 'Batas percobaan tercapai',
};

/** Waktu operasional konsisten dengan filter tanggal server, terpisah dari preferensi tampilan. */
function waktuWita(value: string): string {
    return new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export default function PengirimanNotifikasi({ filters, pengiriman }: PengirimanNotifikasiProps) {
    const form = useForm<FilterPengiriman>({ status: filters.status ?? '', kanal: filters.kanal ?? '', mulai: filters.mulai ?? '', selesai: filters.selesai ?? '' });

    return (
        <AuthenticatedLayout title="Status Pengiriman Notifikasi" breadcrumbs={[{ label: 'Pengaturan', href: '/pengaturan' }, { label: 'Status Pengiriman' }]}>
            <Head title="Status Pengiriman Notifikasi" />
            <div className="space-y-5">
                <Link href="/pengaturan" className="inline-block rounded-sm text-sm font-medium text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/30">← Kembali ke pengaturan</Link>
                <p className="max-w-prose text-sm text-muted">Status diterima provider berarti pesan diterima layanan pengiriman, belum membuktikan pesan sampai. Data simulasi ditandai terpisah dan tidak menjadi bukti pengiriman nyata.</p>
                <Card className="p-5 sm:p-6">
                    <form onSubmit={(event) => { event.preventDefault(); form.get('/pengaturan/notifikasi/pengiriman', { preserveScroll: true, preserveState: 'errors' }); }} className="space-y-4">
                        <fieldset disabled={form.processing} className="grid gap-4 border-0 p-0 sm:grid-cols-2 xl:grid-cols-4">
                            <legend className="sr-only">Filter status pengiriman</legend>
                            <Select label="Status" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} options={statusOptions} error={form.errors.status} />
                            <Select label="Kanal" value={form.data.kanal} onChange={(e) => form.setData('kanal', e.target.value)} error={form.errors.kanal} options={[{ value: '', label: 'Semua kanal' }, { value: 'email', label: 'Email' }, { value: 'whatsapp', label: 'WhatsApp' }]} />
                            <Input label="Mulai tanggal (WITA)" type="date" value={form.data.mulai} onChange={(e) => form.setData('mulai', e.target.value)} error={form.errors.mulai} />
                            <Input label="Sampai tanggal (WITA)" type="date" value={form.data.selesai} onChange={(e) => form.setData('selesai', e.target.value)} error={form.errors.selesai} />
                        </fieldset>
                        <div className="flex flex-wrap items-center gap-4">
                            <Button type="submit" disabled={form.processing}>{form.processing ? 'Memuat…' : 'Terapkan filter'}</Button>
                            <Link href="/pengaturan/notifikasi/pengiriman" className="rounded-sm text-sm font-medium text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/30">Hapus filter</Link>
                            <p role="status" className="text-sm text-muted">{pengiriman.total} pengiriman</p>
                        </div>
                    </form>
                </Card>
                <Card className="overflow-hidden">
                    <Table className="block md:table">
                        <caption className="sr-only">Riwayat status pengiriman; seluruh waktu dalam WITA.</caption>
                        <TableHeader className="hidden md:table-header-group"><TableRow>
                            <TableHead>Dibuat (WITA)</TableHead><TableHead>Kanal</TableHead><TableHead>Penerima</TableHead><TableHead>Status</TableHead><TableHead>Upaya</TableHead>
                        </TableRow></TableHeader>
                        <TableBody className="block md:table-row-group">
                            {pengiriman.data.length === 0 && <TableEmptyRow colSpan={5} icon={Inbox} title="Belum ada pengiriman yang sesuai" description="Ubah filter atau periksa kembali setelah ada kejadian notifikasi." />}
                            {pengiriman.data.map((item) => (
                                <TableRow key={item.id} className="grid gap-3 px-4 py-5 md:table-row md:p-0">
                                    <TableCell className={tableCellResponsive}><TableCellLabel>Dibuat:</TableCellLabel><time dateTime={item.created_at}>{waktuWita(item.created_at)}</time></TableCell>
                                    <TableCell className={tableCellResponsive}><TableCellLabel>Kanal:</TableCellLabel>{item.kanal === 'email' ? 'Email' : 'WhatsApp'}{item.simulasi && <Badge variant="muted" size="sm" className="ml-2">Simulasi</Badge>}</TableCell>
                                    <TableCell className={`${tableCellResponsive} max-w-64 break-words`}><TableCellLabel>Penerima:</TableCellLabel>{item.penerima_nama ?? 'Nama penerima tidak tersedia'}</TableCell>
                                    <TableCell className={tableCellResponsive}><TableCellLabel>Status:</TableCellLabel><Badge variant={statusBadge[item.status]}>{item.label_status}</Badge>{item.kode_hasil && <p className="mt-1 text-muted">{keteranganHasil[item.kode_hasil]}</p>}</TableCell>
                                    <TableCell className={tableCellResponsive}><TableCellLabel>Upaya:</TableCellLabel>{item.percobaan}{item.dicoba_terakhir_pada && <p className="mt-1 text-muted">Terakhir {waktuWita(item.dicoba_terakhir_pada)}</p>}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination {...pengiriman} ariaLabel="Halaman status pengiriman" className="p-5" />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
