import { useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Select } from '@/Components/Select';
import { Tooltip } from '@/Components/Tooltip';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import type { BarisPengukuran, KonteksPengukuran, PilihanJadwalPengukuran } from './types';
import { statusPerhitungan } from './types';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { useFormatNilai } from './formatNilai';

interface PengukuranIndexProps {
    konteks: KonteksPengukuran[];
    filters: { jadwal_id: string | null; periode_id: string | null };
    can: { selectPeriod: boolean };
    pilihan_jadwal?: PilihanJadwalPengukuran[];
    pengukurans: BarisPengukuran[];
    pagination: { links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
    /** Error bag Inertia: konteks filter yang ditolak server dikembalikan ke halaman ini. */
    errors?: { jadwal_id?: string; periode_id?: string };
}

const labelBaris = (baris: BarisPengukuran) => `${baris.indikator.kode} ${baris.periode.nama_periode} ${baris.tahun}`;

/** Satu form per baris agar status proses dan pesan gagal tidak bercampur antarbaris. */
function TombolIsi({ baris }: { baris: BarisPengukuran }) {
    const form = useForm({ jadwal_snapshot_id: baris.jadwal_snapshot_id, periode_id: baris.periode.id });
    const galat = Object.values(form.errors)[0];

    return (
        <div className="flex flex-col items-start gap-1">
            <Tooltip content="Isi" align="right">
                <Button type="button" size="sm" className="h-9 w-9 p-0" isLoading={form.processing}
                    onClick={() => form.post('/pengukuran/ensure-draft')} aria-label={`Isi Pengukuran ${labelBaris(baris)}`}>
                    {!form.processing && <Plus className="h-4 w-4" aria-hidden="true" />}
                </Button>
            </Tooltip>
            {galat && <p role="alert" className="max-w-56 text-left text-xs text-danger">{galat}</p>}
        </div>
    );
}

/** Pilihan jadwal/periode hanya dirender bila server memberi capability; server tetap memvalidasi pasangan. */
function FilterPeriode({ pilihan, konteks, filters, errors }: { pilihan: PilihanJadwalPengukuran[]; konteks: KonteksPengukuran[]; filters: PengukuranIndexProps['filters']; errors: NonNullable<PengukuranIndexProps['errors']> }) {
    // Konteks default dapat memuat jadwal aktif yang lewat penutupan; pilihan awal hanya dari jadwal yang ada di opsi.
    const awal = filters.jadwal_id ?? konteks.find((item) => item.periode && pilihan.some((opsi) => opsi.id === item.jadwal_id))?.jadwal_id ?? pilihan[0]?.id ?? '';
    const [jadwalId, setJadwalId] = useState(awal);
    const periodeAwal = (id: string) => pilihan.find((item) => item.id === id)?.periode[0]?.id ?? '';
    const [periodeId, setPeriodeId] = useState(filters.periode_id ?? konteks.find((item) => item.jadwal_id === awal)?.periode?.id ?? periodeAwal(awal));
    const periode = pilihan.find((item) => item.id === jadwalId)?.periode ?? [];
    const tampilkan = (event: FormEvent) => {
        event.preventDefault();
        router.get('/pengukuran', { jadwal_id: jadwalId, periode_id: periodeId }, { preserveScroll: true });
    };

    return (
        <form onSubmit={tampilkan} className="flex flex-wrap items-start gap-3 p-4">
            <div className="w-40">
                <Select label="Jadwal" error={errors.jadwal_id} value={jadwalId} onChange={(event) => { setJadwalId(event.target.value); setPeriodeId(periodeAwal(event.target.value)); }}
                    options={pilihan.map((item) => ({ value: item.id, label: `Tahun ${item.tahun}` }))} />
            </div>
            <div className="w-48">
                <Select label="Periode" error={errors.periode_id} value={periodeId} onChange={(event) => setPeriodeId(event.target.value)}
                    options={periode.map((item) => ({ value: item.id, label: item.nama_periode }))} />
            </div>
            {/* Rata atas + jarak setinggi label Select agar kotak dan tombol tetap sejajar saat pesan galat menambah tinggi kolom. */}
            <div className="mt-[1.625rem] flex flex-wrap gap-3">
                <Button type="submit" disabled={!jadwalId || !periodeId}>Tampilkan</Button>
                {filters.jadwal_id && <Button type="button" variant="outline" onClick={() => router.get('/pengukuran')}>Periode berjalan</Button>}
            </div>
        </form>
    );
}

function StatusBaris({ baris }: { baris: BarisPengukuran }) {
    if (baris.jenis === 'absent') {
        return <Badge variant="muted" size="sm">{baris.berlaku ? 'Belum Mengisi' : 'Tidak berlaku'}</Badge>;
    }
    const p = baris.pengukuran;

    return (
        <div className="flex flex-col items-start gap-1">
            <Badge status={p.status} size="sm" />
            <span className="text-xs text-muted">{statusPerhitungan[p.status_perhitungan]} · {p.bukti_count} bukti</span>
            {p.self_approval && <span className="text-xs font-medium text-info-dark">Persetujuan sendiri</span>}
            {p.reviu_terlambat && <span className="text-xs font-medium text-warning-dark">Reviu terlambat</span>}
        </div>
    );
}

function AksiBaris({ baris }: { baris: BarisPengukuran }) {
    if (baris.jenis === 'existing') {
        const label = ['draft', 'dikembalikan'].includes(baris.pengukuran.status) ? 'Buka Draft' : 'Detail';

        return (
            <Tooltip content={label} align="right">
                <Link href={`/pengukuran/${baris.pengukuran.id}/edit`} aria-label={`${label} ${labelBaris(baris)}`}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-surface text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25">
                    <Eye className="h-4 w-4" aria-hidden="true" />
                </Link>
            </Tooltip>
        );
    }

    return baris.can.create ? <TombolIsi baris={baris} /> : <span className="text-muted">—</span>;
}

export default function PengukuranIndex({ konteks, filters, can, pilihan_jadwal = [], pengukurans, pagination, errors = {} }: PengukuranIndexProps) {
    const formatNilai = useFormatNilai();
    const berjalan = konteks.filter((item) => item.periode);
    const kosong = konteks.length === 0 ? 'Jadwal aktif belum tersedia' : berjalan.length === 0 ? 'Pengisian belum dimulai' : 'Belum ada indikator pada periode ini';
    const nilai = (value: string | number | null, baris: BarisPengukuran) => value === null ? '—' : `${formatNilai(value, baris.indikator.desimal_tampilan)} ${baris.indikator.satuan}`;
    const target = (baris: BarisPengukuran) => {
        if (baris.jenis === 'absent' && !baris.berlaku) return '—';
        const data = baris.jenis === 'existing' ? baris.pengukuran : baris;
        return data.tanpa_target_periode ? 'Tanpa target periode' : data.target === null ? 'Belum tersedia' : nilai(data.target, baris);
    };
    const realisasi = (baris: BarisPengukuran) => baris.jenis === 'existing' ? nilai(baris.pengukuran.nilai, baris) : '—';

    return (
        <AuthenticatedLayout title="Pengukuran Kinerja" breadcrumbs={[{ label: 'Pengukuran Kinerja' }]}>
            <Head title="Pengukuran Kinerja" />

            {can.selectPeriod && pilihan_jadwal.length > 0 && (
                <Card className="mb-5">
                    <FilterPeriode pilihan={pilihan_jadwal} konteks={konteks} filters={filters} errors={errors} />
                </Card>
            )}

            <Card>
                <Table>
                    <caption className="sr-only">Daftar kewajiban pengukuran per indikator dan periode</caption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Indikator</TableHead>
                            <TableHead className="hidden lg:table-cell">Periode</TableHead>
                            <TableHead className="hidden text-right lg:table-cell">Target</TableHead>
                            <TableHead className="hidden text-right lg:table-cell">Realisasi</TableHead>
                            <TableHead className="hidden lg:table-cell">Status</TableHead>
                            <TableHead className="w-20 text-left lg:w-auto">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {pengukurans.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-12 text-center text-sm font-semibold text-ink">{kosong}</TableCell>
                            </TableRow>
                        ) : pengukurans.map((baris) => (
                            <TableRow key={`${baris.jadwal_id}:${baris.indikator_id}:${baris.periode.id}`}>
                                <TableCell className="max-w-sm break-words">
                                    <div className="font-semibold">{baris.indikator.kode}</div>
                                    <div className="mt-0.5 text-muted">{baris.indikator.nama}</div>
                                    <p className="mt-1 text-xs text-muted">{baris.unit.nama} · PJ: {baris.pic?.nama ?? '—'}</p>
                                    <p className="mt-1 text-xs text-muted lg:hidden">{baris.periode.nama_periode} {baris.tahun}</p>
                                    <p className="mt-1 text-xs text-muted lg:hidden">Target {target(baris)} · Realisasi {realisasi(baris)}</p>
                                    {/* Layar sempit: target, realisasi, dan status pindah ke sel indikator agar kolom Aksi tidak terpotong. */}
                                    <div className="mt-2 lg:hidden"><StatusBaris baris={baris} /></div>
                                </TableCell>
                                <TableCell className="hidden lg:table-cell">{baris.periode.nama_periode} {baris.tahun}</TableCell>
                                <TableCell className="hidden text-right lg:table-cell">{target(baris)}</TableCell>
                                <TableCell className="hidden text-right lg:table-cell">{realisasi(baris)}</TableCell>
                                <TableCell className="hidden lg:table-cell"><StatusBaris baris={baris} /></TableCell>
                                <TableCell className="text-left"><AksiBaris baris={baris} /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Pagination {...pagination} ariaLabel="Halaman pengukuran" className="border-t border-border px-5 py-3.5" />
            </Card>
        </AuthenticatedLayout>
    );
}
