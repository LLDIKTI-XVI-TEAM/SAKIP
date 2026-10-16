import { useState, type FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Select } from '@/Components/Select';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

export interface PersetujuanRekomendasi {
    id: string;
    isi: string;
    rujukan_sumber: string | null;
    disetujui_oleh: string;
    disetujui_oleh_role: string;
    disetujui_pada: string;
    persetujuan_cadangan: boolean;
}

export interface BarisRekomendasi {
    indikator: { id: string; kode: string; nama: string; unit: string };
    tahun: number;
    periode_id: string;
    berlaku: PersetujuanRekomendasi | null;
    draf: { id: string; isi: string; rujukan_sumber: string | null; versi: number; penyusun: string } | null;
    riwayat: PersetujuanRekomendasi[];
}

interface PilihanJadwal {
    id: string;
    tahun: number;
    status: 'aktif' | 'ditutup';
    periode: { id: string; nama_periode: string }[];
}

interface RekomendasiIndexProps {
    pilihan_jadwal: PilihanJadwal[];
    filters: { jadwal_id: string | null; periode_id: string | null };
    persetujuan_cadangan: boolean;
    rekomendasi: BarisRekomendasi[];
    errors?: { jadwal_id?: string; periode_id?: string };
}

const namaPeran: Record<string, string> = { pimpinan: 'Pimpinan', perencanaan: 'Perencanaan', superadmin: 'Superadmin', admin: 'Admin', pegawai: 'Pegawai' };
const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';

function Persetujuan({ data }: { data: PersetujuanRekomendasi }) {
    const formatTanggal = useFormatTanggal();

    return (
        <div className="space-y-1">
            <p className="whitespace-pre-line break-words text-sm text-ink">{data.isi}</p>
            <div className="flex flex-wrap items-center gap-1.5">
                {data.persetujuan_cadangan
                    ? <Badge variant="warning" size="sm">Persetujuan cadangan · {namaPeran[data.disetujui_oleh_role] ?? data.disetujui_oleh_role}</Badge>
                    : <Badge variant="success" size="sm">Disetujui Pimpinan</Badge>}
                <span className="text-xs text-muted">{data.disetujui_oleh} · {formatTanggal(data.disetujui_pada, { withTime: true })}</span>
            </div>
            {data.rujukan_sumber && <p className="text-xs text-muted">Sumber: {data.rujukan_sumber}</p>}
        </div>
    );
}

/** Satu form untuk simpan draf dan setujui; keduanya memakai izin yang sama dan hanya berbeda target status. */
function FormRekomendasi({ baris, cadangan, onClose }: { baris: BarisRekomendasi; cadangan: boolean; onClose: () => void }) {
    const form = useForm({
        indikator_id: baris.indikator.id, tahun: baris.tahun, periode_id: baris.periode_id, status: 'draf',
        isi: baris.draf?.isi ?? baris.berlaku?.isi ?? '', rujukan_sumber: baris.draf?.rujukan_sumber ?? '', versi_draf: baris.draf?.versi ?? null,
        // Rekomendasi berlaku yang dilihat; server menolak bila sudah diganti pengguna lain.
        berlaku_id: baris.berlaku?.id ?? null,
    });
    // Konflik versi dan penolakan field di luar kontrak dikirim server dengan key di luar data form.
    const galat = form.errors as Record<string, string | undefined>;
    const galatUmum = galat.konflik ?? galat.periode_id ?? galat.rekomendasi_pimpinan ?? galat.status;
    const kirim = (status: 'draf' | 'disetujui') => {
        form.transform((data) => ({ ...data, status }));
        form.post('/rekomendasi', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal isOpen onClose={onClose} size="lg" title={`Rekomendasi ${baris.indikator.kode}`} description={baris.indikator.nama}
            footer={<div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>Batal</Button>
                <Button type="button" variant="outline" onClick={() => kirim('draf')} disabled={form.processing}>Simpan draf</Button>
                <Button type="button" onClick={() => kirim('disetujui')} isLoading={form.processing}>Setujui</Button>
            </div>}>
            <div className="space-y-4">
                {galatUmum && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">{galatUmum}</p>}
                {baris.draf && <p className="text-xs text-muted">Draf disusun oleh {baris.draf.penyusun}. Isi dapat diubah sebelum disimpan atau disetujui.</p>}
                {!baris.draf && baris.berlaku && <p className="text-xs text-muted">Perubahan membentuk rekomendasi baru; rekomendasi yang berlaku tetap tersimpan sebagai riwayat.</p>}
                <Textarea label="Isi rekomendasi" name="isi" rows={6} required value={form.data.isi} error={form.errors.isi} onChange={(event) => form.setData('isi', event.target.value)} />
                <Input label="Rujukan sumber (opsional)" name="rujukan_sumber" value={form.data.rujukan_sumber} error={form.errors.rujukan_sumber}
                    helperText="Contoh: Laporan Kinerja Triwulan I 2026, untuk rekomendasi yang dicatat dari dokumen resmi." onChange={(event) => form.setData('rujukan_sumber', event.target.value)} />
                {cadangan && <p className="rounded-lg border border-info/30 bg-info/10 p-3 text-sm text-info-dark">Persetujuan Anda dicatat sebagai persetujuan cadangan karena Anda bukan Pimpinan.</p>}
            </div>
        </Modal>
    );
}

function FilterPeriode({ pilihan, filters, errors }: { pilihan: PilihanJadwal[]; filters: RekomendasiIndexProps['filters']; errors: NonNullable<RekomendasiIndexProps['errors']> }) {
    const [jadwalId, setJadwalId] = useState(filters.jadwal_id ?? pilihan[0]?.id ?? '');
    const periodeAwal = (id: string) => pilihan.find((item) => item.id === id)?.periode[0]?.id ?? '';
    const [periodeId, setPeriodeId] = useState(filters.periode_id ?? periodeAwal(jadwalId));
    const tampilkan = (event: FormEvent) => {
        event.preventDefault();
        router.get('/rekomendasi', { jadwal_id: jadwalId, periode_id: periodeId }, { preserveScroll: true });
    };

    return (
        <form onSubmit={tampilkan} className="flex flex-wrap items-start gap-3 border-b border-border p-4">
            <div className="w-48">
                <Select label="Jadwal" error={errors.jadwal_id} value={jadwalId} onChange={(event) => { setJadwalId(event.target.value); setPeriodeId(periodeAwal(event.target.value)); }}
                    options={pilihan.map((item) => ({ value: item.id, label: `Tahun ${item.tahun}${item.status === 'ditutup' ? ' (ditutup)' : ''}` }))} />
            </div>
            <div className="w-48">
                <Select label="Periode" error={errors.periode_id} value={periodeId} onChange={(event) => setPeriodeId(event.target.value)}
                    options={(pilihan.find((item) => item.id === jadwalId)?.periode ?? []).map((item) => ({ value: item.id, label: item.nama_periode }))} />
            </div>
            <div className="mt-[1.625rem]">
                <Button type="submit" disabled={!jadwalId || !periodeId}>Tampilkan</Button>
            </div>
        </form>
    );
}

export default function RekomendasiIndex({ pilihan_jadwal, filters, persetujuan_cadangan, rekomendasi, errors = {} }: RekomendasiIndexProps) {
    const [terpilih, setTerpilih] = useState<BarisRekomendasi | null>(null);
    const jadwal = pilihan_jadwal.find((item) => item.id === filters.jadwal_id);
    const periode = jadwal?.periode.find((item) => item.id === filters.periode_id);
    const kosong = pilihan_jadwal.length === 0 ? 'Jadwal aktif belum tersedia' : 'Belum ada indikator pada periode ini';

    return (
        <AuthenticatedLayout title="Rekomendasi Pimpinan" breadcrumbs={[{ label: 'Rekomendasi Pimpinan' }]}>
            <Head title="Rekomendasi Pimpinan" />

            <p className="mb-4 text-sm text-muted">
                {periode && jadwal ? `${periode.nama_periode} ${jadwal.tahun} · ` : ''}Hanya rekomendasi yang disetujui yang tampil pada pengukuran periode berikutnya.
            </p>

            <Card>
                {pilihan_jadwal.length > 0 && <FilterPeriode pilihan={pilihan_jadwal} filters={filters} errors={errors} />}
                {/* Grid mobile: setiap indikator tampil sebagai kartu tanpa scroll horizontal. */}
                <Table wrapperClassName="overflow-x-visible" className="block md:table">
                    <caption className="sr-only">Rekomendasi Pimpinan per indikator pada periode terpilih</caption>
                    <TableHeader className="max-md:sr-only">
                        <TableRow>
                            <TableHead>Indikator</TableHead>
                            <TableHead>Rekomendasi berlaku</TableHead>
                            <TableHead>Draf</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="block md:table-row-group">
                        {rekomendasi.length === 0 ? (
                            <TableRow className="block md:table-row">
                                <TableCell colSpan={4} className="block py-12 text-center text-sm font-semibold text-ink md:table-cell">{kosong}</TableCell>
                            </TableRow>
                        ) : rekomendasi.map((baris) => {
                            const aksi = baris.draf ? 'Tinjau draf' : baris.berlaku ? 'Ubah rekomendasi' : 'Tulis rekomendasi';

                            return (
                                <TableRow key={baris.indikator.id} className="grid min-w-0 grid-cols-1 gap-3 p-4 md:table-row">
                                    <TableCell className={`${cellClass} max-w-xs break-words`}>
                                        <p className="text-sm font-semibold text-ink">{baris.indikator.kode}</p>
                                        <p className="text-xs text-muted">{baris.indikator.nama}</p>
                                        <p className="mt-1 text-xs text-muted">{baris.indikator.unit}</p>
                                    </TableCell>
                                    <TableCell className={cellClass}>
                                        <span className="mb-1 block text-xs font-semibold text-muted md:hidden">Rekomendasi berlaku</span>
                                        {baris.berlaku ? <Persetujuan data={baris.berlaku} /> : <span className="text-xs text-muted">Belum ada rekomendasi disetujui</span>}
                                        {baris.riwayat.length > 0 && (
                                            <details className="mt-2 text-xs">
                                                <summary className="cursor-pointer font-medium text-primary">Riwayat persetujuan ({baris.riwayat.length})</summary>
                                                <ul className="mt-2 space-y-3 border-l border-border pl-3">
                                                    {baris.riwayat.map((item) => <li key={item.id}><Persetujuan data={item} /></li>)}
                                                </ul>
                                            </details>
                                        )}
                                    </TableCell>
                                    <TableCell className={cellClass}>
                                        <span className="mb-1 block text-xs font-semibold text-muted md:hidden">Draf</span>
                                        {baris.draf ? (
                                            <div className="space-y-1">
                                                <p className="whitespace-pre-line break-words text-sm text-ink">{baris.draf.isi}</p>
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Badge variant="muted" size="sm">Menunggu persetujuan</Badge>
                                                    <span className="text-xs text-muted">{baris.draf.penyusun}</span>
                                                </div>
                                            </div>
                                        ) : <span className="text-xs text-muted">—</span>}
                                    </TableCell>
                                    <TableCell className={`${cellClass} md:text-right`}>
                                        <Button type="button" size="sm" variant={baris.draf ? 'primary' : 'outline'} className="whitespace-nowrap" aria-label={`${aksi} ${baris.indikator.kode}`} onClick={() => setTerpilih(baris)}>
                                            {aksi}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </Card>

            {terpilih && <FormRekomendasi key={terpilih.indikator.id} baris={terpilih} cadangan={persetujuan_cadangan} onClose={() => setTerpilih(null)} />}
        </AuthenticatedLayout>
    );
}
