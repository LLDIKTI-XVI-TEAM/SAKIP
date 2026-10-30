import { useState, type FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

const aksiArsipClass = 'inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface p-0 text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20 md:h-8 md:w-8';

interface Arsip {
    id: string;
    nama_asli: string;
    keterangan: string | null;
    pengunggah: string;
    waktu: string;
    menggantikan_id: string | null;
    alasan_koreksi: string | null;
    digantikan: boolean;
    url: string;
}

export interface DokumenResmiProps {
    filters: { jadwal_id: string; periode_id: string; jenis: 'ra' | 'triwulan' };
    pilihan_jadwal: { id: string; label: string }[];
    pilihan_periode: { id: string; nama: string }[];
    jadwal: { id: string; tahun: number; status: string } | null;
    periode_jadwal_id: string | null;
    arsip: { data: Arsip[]; links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
    can: { unggah: boolean; unduh: boolean };
    alasan_unduh: string | null;
    batas_kb: number;
}

/** Form terikat konteks server agar file tidak terbawa ke jadwal atau periode lain. */
function UnggahArsip({ jadwalId, periodeJadwalId, pengganti, batasKb, onClose }: {
    jadwalId: string; periodeJadwalId: string | null; pengganti: Arsip | null; batasKb: number; onClose: () => void;
}) {
    const form = useForm({ jadwal_id: jadwalId, periode_jadwal_id: periodeJadwalId,
        file: null as File | null, keterangan: pengganti?.keterangan ?? '',
        menggantikan_id: pengganti?.id ?? null, alasan_koreksi: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/laporan/dokumen-resmi/arsip', {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash;
                if (typeof flash === 'object' && flash !== null && 'error' in flash && flash.error) return;
                onClose();
            },
            onError: () => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
        });
    };

    return <Modal isOpen onClose={() => { if (!form.processing) onClose(); }} title={pengganti ? 'Unggah berkas pengganti' : 'Unggah arsip PDF'}
        description={pengganti ? 'Berkas lama tetap tersimpan sebagai riwayat.' : 'Unggah PDF yang sudah ditandatangani. Arsip yang tersimpan tidak dapat dihapus.'}>
        <form onSubmit={submit} className="space-y-5" aria-busy={form.processing}>
            {pengganti && <p className="break-words text-sm text-muted">Menggantikan: <span className="font-medium text-ink">{pengganti.nama_asli}</span></p>}
            <Input type="file" id="arsip-file" label="Berkas PDF" required accept=".pdf,application/pdf" disabled={form.processing}
                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} error={form.errors.file}
                helperText={`PDF saja, maksimal ${new Intl.NumberFormat('id-ID').format(batasKb)} KB.`} />
            <Input id="arsip-keterangan" label="Keterangan dokumen (opsional)" maxLength={1000} disabled={form.processing}
                value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} error={form.errors.keterangan}
                helperText="Contoh: Rencana Aksi perubahan anggaran. Keterangan tidak dapat diubah setelah unggah." />
            {pengganti && <Input id="arsip-alasan" label="Alasan penggantian" required maxLength={1000} disabled={form.processing}
                value={form.data.alasan_koreksi} onChange={(event) => form.setData('alasan_koreksi', event.target.value)} error={form.errors.alasan_koreksi} />}
            {(['jadwal_id', 'periode_jadwal_id', 'menggantikan_id'] as const).map((field) => form.errors[field] && <p key={field} role="alert" className="text-sm text-danger">{form.errors[field]}</p>)}
            {form.progress && <p role="status" className="text-sm text-muted">Mengunggah {form.progress.percentage}%…</p>}
            <div className="flex flex-wrap justify-end gap-3">
                <Button type="button" variant="outline" disabled={form.processing} onClick={onClose}>Batal</Button>
                <Button type="submit" isLoading={form.processing}>Simpan arsip</Button>
            </div>
        </form>
    </Modal>;
}

export default function DokumenResmi({ filters, pilihan_jadwal, pilihan_periode, jadwal, periode_jadwal_id, arsip, can, alasan_unduh, batas_kb }: DokumenResmiProps) {
    const form = useForm({ ...filters });
    const tanggal = useFormatTanggal();
    const [unggah, setUnggah] = useState<{ pengganti: Arsip | null } | null>(null);
    const filterBerubah = form.data.jadwal_id !== filters.jadwal_id || form.data.periode_id !== filters.periode_id || form.data.jenis !== filters.jenis;
    const konteksTersedia = Boolean(jadwal && (filters.jenis === 'ra' || periode_jadwal_id));
    const terapkan = (event: FormEvent) => {
        event.preventDefault();
        form.get('/laporan/dokumen-resmi', { preserveState: false, preserveScroll: true });
    };

    return <AuthenticatedLayout title="Dokumen Resmi" breadcrumbs={[{ label: 'Dokumen Resmi' }]}>
        <Head title="Dokumen Resmi" />
        <div className="space-y-6 font-sans">
            <Card className="p-5">
                <form onSubmit={terapkan} className="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy={form.processing}>
                    <Select id="dokumen-jadwal" label="Tahun / jadwal" value={form.data.jadwal_id} error={form.errors.jadwal_id} disabled={form.processing || !pilihan_jadwal.length}
                        onChange={(event) => form.setData({ ...form.data, jadwal_id: event.target.value, periode_id: '' })}>
                        <option value="">Pilih jadwal</option>
                        {pilihan_jadwal.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
                    </Select>
                    <Select id="dokumen-jenis" label="Jenis dokumen" value={form.data.jenis} error={form.errors.jenis} disabled={form.processing}
                        onChange={(event) => form.setData('jenis', event.target.value === 'ra' ? 'ra' : 'triwulan')}>
                        <option value="ra">Rencana Aksi tahunan</option><option value="triwulan">Laporan Kinerja Triwulan</option>
                    </Select>
                    {form.data.jenis === 'triwulan' && <Select id="dokumen-periode" label="Periode" value={form.data.periode_id} error={form.errors.periode_id}
                        disabled={form.processing || form.data.jadwal_id !== filters.jadwal_id} onChange={(event) => form.setData('periode_id', event.target.value)}>
                        <option value="">Pilih periode</option>
                        {pilihan_periode.map((item) => <option key={item.id} value={item.id}>{item.nama}</option>)}
                    </Select>}
                    <Button type="submit" className="h-[42px] justify-self-start" isLoading={form.processing} disabled={!form.data.jadwal_id}>Terapkan</Button>
                </form>
            </Card>
            {filterBerubah && <p role="status" className="text-sm text-muted">Terapkan pilihan terlebih dahulu untuk melihat dan mengunggah arsip pada jadwal yang dipilih.</p>}
            {alasan_unduh && <p id="arsip-akses" role="status" className="rounded-lg bg-soft p-4 text-sm text-ink">{alasan_unduh}</p>}
            <Card>
                <div className="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-base font-semibold text-ink">{filters.jenis === 'ra' ? 'Arsip Rencana Aksi tahunan' : 'Arsip Laporan Kinerja Triwulan'}</h2>
                        {jadwal && <p className="mt-1 text-sm text-muted">Tahun {jadwal.tahun}{filters.jenis === 'triwulan' ? ` · ${pilihan_periode.find((item) => item.id === filters.periode_id)?.nama ?? 'Pilih periode'}` : ''}{jadwal.status === 'ditutup' ? ' · Jadwal ditutup; unggah arsip tetap tersedia' : ''}</p>}
                    </div>
                    {can.unggah && <Button type="button" disabled={filterBerubah || !konteksTersedia || form.processing} onClick={() => setUnggah({ pengganti: null })}><Upload className="h-4 w-4" aria-hidden="true" />Unggah PDF</Button>}
                </div>
                {!konteksTersedia || !arsip.data.length ? <div className="px-5 py-12 text-center" role="status">
                    <FileText className="mx-auto mb-3 h-6 w-6 text-muted" aria-hidden="true" />
                    <p className="text-sm font-medium text-ink">{!konteksTersedia ? 'Pilih jadwal dan periode dokumen.' : 'Belum ada arsip PDF.'}</p>
                    <p className="mt-1 text-sm text-muted">{!pilihan_jadwal.length ? 'Belum ada jadwal aktif atau ditutup yang tersedia.' : 'Dokumen resmi baru dapat ditambahkan tanpa menghapus arsip sebelumnya.'}</p>
                </div> : <Table aria-label="Arsip PDF dokumen resmi" className="block md:table" wrapperClassName="overflow-visible">
                    <TableHeader className="hidden md:table-header-group"><TableRow><TableHead>Dokumen</TableHead><TableHead>Pengunggah / waktu</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader>
                    <TableBody className="block md:table-row-group">{arsip.data.map((item) => <TableRow key={item.id} className="block p-4 md:table-row md:p-0">
                        <TableCell className="block px-0 py-2 md:table-cell md:px-4 md:py-3.5">
                            <p className="break-words font-semibold">{item.nama_asli}</p>
                            {item.keterangan && <p className="mt-1 whitespace-pre-wrap break-words text-sm text-muted">{item.keterangan}</p>}
                            {item.digantikan && <Badge variant="muted" className="mt-2">Riwayat — sudah diganti</Badge>}
                            {item.alasan_koreksi && <p className="mt-2 break-words text-sm text-muted">Alasan penggantian: {item.alasan_koreksi}</p>}
                        </TableCell>
                        <TableCell className="block px-0 py-2 md:table-cell md:px-4 md:py-3.5"><p>{item.pengunggah}</p><time dateTime={item.waktu} className="mt-1 block text-muted">{tanggal(item.waktu, { withTime: true })}</time></TableCell>
                        <TableCell className="block px-0 py-2 md:table-cell md:px-4 md:py-3.5"><div className="flex flex-wrap items-center gap-3">
                            <Tooltip content="Unduh PDF" align="right">
                                {can.unduh && !filterBerubah ? <a href={item.url} aria-label={`Unduh PDF ${item.nama_asli}`} className={aksiArsipClass}><Download className="h-4 w-4" aria-hidden="true" /></a>
                                    : <Button type="button" variant="ghost" className={aksiArsipClass} disabled aria-label={`Unduh PDF ${item.nama_asli}`} aria-describedby={alasan_unduh ? 'arsip-akses' : undefined}><Download className="h-4 w-4" aria-hidden="true" /></Button>}
                            </Tooltip>
                            {can.unggah && !item.digantikan && <Tooltip content="Unggah pengganti" align="right"><Button type="button" variant="ghost" className={aksiArsipClass} disabled={filterBerubah || form.processing} aria-label={`Unggah pengganti ${item.nama_asli}`} onClick={() => setUnggah({ pengganti: item })}><Upload className="h-4 w-4" aria-hidden="true" /></Button></Tooltip>}
                        </div></TableCell>
                    </TableRow>)}</TableBody>
                </Table>}
                <Pagination {...arsip} ariaLabel="Halaman arsip PDF" className="border-t border-border px-5 py-4" />
            </Card>
        </div>
        {unggah && jadwal && <UnggahArsip key={`${jadwal.id}:${periode_jadwal_id}:${unggah.pengganti?.id ?? 'baru'}`} jadwalId={jadwal.id} periodeJadwalId={filters.jenis === 'ra' ? null : periode_jadwal_id}
            pengganti={unggah.pengganti} batasKb={batas_kb} onClose={() => setUnggah(null)} />}
    </AuthenticatedLayout>;
}
