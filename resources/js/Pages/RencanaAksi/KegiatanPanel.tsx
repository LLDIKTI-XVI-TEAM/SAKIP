import { useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { ClipboardCheck, MoreHorizontal, Pencil, Plus } from 'lucide-react';
import { ActionMenu } from '@/Components/ActionMenu';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableCellLabel, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { bantuanAnggaran, formatRupiah, teksTotalAnggaran } from '@/lib/rupiah';
import { ARAH_DAMPAK_OPTIONS, statusKegiatanConfig, type ArahDampak } from '@/types/kegiatan';
import type { RencanaAksiKegiatan, RencanaAksiKomponen, RencanaAksiPeriode } from './types';

interface KegiatanPanelProps {
    rencanaAksiId: string;
    /** Hanya periode efektif RA. */
    periode: RencanaAksiPeriode[];
    komponen: RencanaAksiKomponen[];
    /** Periode terkunci bagi aktor (Q41); kosong untuk Perencanaan. */
    terkunci: string[];
    kegiatan: RencanaAksiKegiatan;
    /** Form target berubah belum disimpan; tambah kegiatan menaikkan versi header dan me-remount form target. */
    kotor: boolean;
}

interface FormKegiatan {
    periode_id: string;
    salin_ke_periode_ids: string[];
    nama: string;
    tujuan: string;
    anggaran: string;
    sasaran_peserta: string;
    keterangan_peserta: string;
    lokasi: string;
    tanggal_rencana: string;
    komponen_id: string;
    arah_dampak: ArahDampak;
    catatan: string;
}

const KOSONG: FormKegiatan = {
    periode_id: '', salin_ke_periode_ids: [], nama: '', tujuan: '', anggaran: '', sasaran_peserta: '', keterangan_peserta: '',
    lokasi: '', tanggal_rencana: '', komponen_id: '', arah_dampak: 'menambah', catatan: '',
};

// Kolom berlebar tetap baru muat di samping sidebar mulai 1280 px; di bawahnya setiap baris tampil sebagai kartu berlabel.
const selResponsif = 'min-w-0 p-0 xl:px-4 xl:py-3.5';
const labelResponsif = 'md:inline xl:hidden';

/** Galat validasi bisnis gerbang klaim (422) yang tidak terikat ke field form. */
function galatBisnis(errs: Record<string, string | undefined>): string | undefined {
    return errs.status_alur ?? errs.jendela ?? errs.unit_id ?? errs.indikator_id;
}

/**
 * Daftar kegiatan per periode pada Rencana Aksi (Q42 §42.1): tambah kegiatan
 * sekaligus klaimnya dan salin ke periode lain (Q49 §49.2). Periode terkunci
 * tampil baca-saja bagi PIC; server tetap menolak di bawah lock.
 */
export default function KegiatanPanel({ rencanaAksiId, periode, komponen, terkunci, kegiatan, kotor }: KegiatanPanelProps) {
    const [terbuka, setTerbuka] = useState(false);
    const [galatKirim, setGalatKirim] = useState('');
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<FormKegiatan>(KOSONG);
    const fieldErrors = errors as Record<string, string | undefined>;
    const terbukaBagiAktor = periode.filter((item) => !terkunci.includes(item.id));
    // Satu kegiatan dapat muncul sebagai beberapa baris klaim komponen; anchor `#kegiatan-{id}` hanya dipasang di baris pertama agar tidak duplikat.
    const barisAnchor = new Set(kegiatan.daftar
        .filter((row, index, semua) => semua.findIndex((lain) => lain.kegiatan_id === row.kegiatan_id) === index)
        .map((row) => row.id));

    const buka = (periodeId: string): void => {
        clearErrors();
        setGalatKirim('');
        setData({ ...KOSONG, periode_id: periodeId });
        setTerbuka(true);
    };

    const tutup = (): void => {
        if (processing) return;
        setTerbuka(false);
        reset();
        clearErrors();
    };

    const toggleSalin = (periodeId: string): void => {
        setData('salin_ke_periode_ids', data.salin_ke_periode_ids.includes(periodeId)
            ? data.salin_ke_periode_ids.filter((id) => id !== periodeId)
            : [...data.salin_ke_periode_ids, periodeId]);
    };

    const kirim = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (processing) return;
        setGalatKirim('');
        transform((form) => ({
            ...form,
            sasaran_peserta: form.sasaran_peserta === '' ? null : Number(form.sasaran_peserta),
            anggaran: form.anggaran === '' ? null : form.anggaran,
        }));
        post(`/rencana-aksi/${rencanaAksiId}/kegiatan`, {
            preserveScroll: true,
            onSuccess: () => {
                setTerbuka(false);
                reset();
            },
            onHttpException: (response) => {
                setGalatKirim(response.status === 403
                    ? 'Izin menambah kegiatan ditolak. Muat ulang halaman untuk melihat akses terbaru.'
                    : 'Hasil penyimpanan belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                return false;
            },
            onNetworkError: () => {
                setGalatKirim('Koneksi terputus. Muat ulang halaman untuk memastikan kegiatan tersimpan.');
                return false;
            },
        });
    };

    const galatUmum = galatKirim || galatBisnis(fieldErrors);
    // Galat per elemen (`salin_ke_periode_ids.0`) ditampilkan sebagai satu pesan grup.
    const galatSalin = fieldErrors.salin_ke_periode_ids ?? Object.entries(fieldErrors).find(([key]) => key.startsWith('salin_ke_periode_ids.'))?.[1];
    const namaPeriode = (id: string): string => periode.find((item) => item.id === id)?.nama ?? 'Periode';
    // Kolom komponen disembunyikan bila tidak ada komponen maupun klaim berkomponen (indikator manual); klaim yang merujuk
    // komponen di luar snapshot tetap menampilkan kolom agar penjelasannya tidak hilang. Kolom aksi hanya bila ada capability kelola.
    const adaKomponen = komponen.length > 0 || kegiatan.daftar.some((row) => row.komponen_id !== null);
    const jumlahKolom = 4 + (adaKomponen ? 1 : 0) + (kegiatan.can.kelola ? 1 : 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kegiatan per Periode</CardTitle>
            </CardHeader>
            <CardContent className="divide-y divide-border p-0">
                {kotor && (kegiatan.can.tambah || kegiatan.can.kelola) && <p className="px-6 py-4 text-sm text-muted">Simpan atau buang perubahan target sebelum menambah atau mengelola kegiatan.</p>}
                {periode.length === 0 && <p className="px-6 py-4 text-sm text-muted">Tidak ada periode efektif untuk rencana aksi ini.</p>}
                {periode.map((item) => {
                    const daftar = kegiatan.daftar.filter((row) => row.periode_id === item.id);
                    const kunci = terkunci.includes(item.id);
                    // Periode tanpa kegiatan tidak punya entri per_periode; totalnya Rp0.
                    const totalPeriode = kegiatan.total.per_periode[item.id] ?? { total: '0', belum_diisi: 0 };

                    return (
                        <section key={item.id} aria-labelledby={`kegiatan-periode-${item.id}`}>
                            <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                                <h3 id={`kegiatan-periode-${item.id}`} className="flex items-center gap-2 text-sm font-semibold text-ink">
                                    {item.nama ?? 'Periode'}
                                    {kunci && <Badge variant="muted">Terkunci</Badge>}
                                </h3>
                                <div className="flex flex-wrap items-center gap-3">
                                    <p className="text-xs text-muted">Total {teksTotalAnggaran(totalPeriode)}</p>
                                    {kegiatan.can.tambah && !kunci && (
                                        <Button type="button" size="sm" variant="outline" disabled={kotor} onClick={() => buka(item.id)} aria-label={`Tambah kegiatan ${item.nama ?? ''}`}>
                                            <Plus className="h-4 w-4" aria-hidden="true" />
                                            Tambah kegiatan
                                        </Button>
                                    )}
                                </div>
                            </div>
                            {/* Lebar kolom tetap agar kolom sejajar antartabel periode (mulai xl, lihat selResponsif). */}
                            <Table wrapperClassName="overflow-x-visible" className="block xl:table xl:table-fixed">
                                <TableHeader className="max-xl:sr-only">
                                    <TableRow>
                                        <TableHead className="whitespace-normal">Kegiatan</TableHead>
                                        {adaKomponen && <TableHead className="w-56">Komponen</TableHead>}
                                        <TableHead className="w-36">Arah dampak</TableHead>
                                        <TableHead className="w-40 text-right">Anggaran</TableHead>
                                        <TableHead className="w-32">Status</TableHead>
                                        {kegiatan.can.kelola && <TableHead className="w-20 text-right">Aksi</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="block xl:table-row-group">
                                    {daftar.length === 0 ? (
                                        <TableRow className="block xl:table-row">
                                            <TableCell colSpan={jumlahKolom} className="block px-6 py-4 text-muted xl:table-cell">Belum ada kegiatan.</TableCell>
                                        </TableRow>
                                    ) : daftar.map((row) => {
                                        const status = statusKegiatanConfig[row.status] ?? { label: row.status, variant: 'muted' as const };
                                        const komponenKlaim = komponen.find((item) => item.komponen_id === row.komponen_id);

                                        return (
                                            <TableRow key={row.id} id={barisAnchor.has(row.id) ? `kegiatan-${row.kegiatan_id}` : undefined} className="grid min-w-0 scroll-mt-4 grid-cols-2 gap-3 px-6 py-4 xl:table-row">
                                                <TableCell className={`col-span-2 ${selResponsif}`}>
                                                    <p className="break-words text-sm font-medium text-ink">{row.nama}</p>
                                                    {row.catatan && <p className="mt-0.5 break-words text-xs text-muted">{row.catatan}</p>}
                                                    {(row.kegiatan_bersama || row.sumber_klaim === 'pengukuran') && (
                                                        <span className="mt-1.5 flex flex-wrap gap-1">
                                                            {row.kegiatan_bersama && <Badge variant="info">Kegiatan bersama</Badge>}
                                                            {row.sumber_klaim === 'pengukuran' && <Badge variant="muted">Klaim pengukuran</Badge>}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                {adaKomponen && (
                                                    <TableCell className={`col-span-2 ${selResponsif}`}>
                                                        <TableCellLabel className={labelResponsif}>Komponen</TableCellLabel>
                                                        <span>{komponenKlaim ? `${komponenKlaim.kode} — ${komponenKlaim.label}` : row.komponen_id === null ? 'Tanpa komponen tertentu' : 'Komponen tidak tersedia pada snapshot'}</span>
                                                    </TableCell>
                                                )}
                                                <TableCell className={selResponsif}>
                                                    <TableCellLabel className={labelResponsif}>Arah</TableCellLabel>
                                                    <Badge variant={row.arah_dampak === 'menambah' ? 'success' : 'warning'}>{row.arah_dampak === 'menambah' ? 'Menambah' : 'Mengurangi'}</Badge>
                                                </TableCell>
                                                <TableCell className={`${selResponsif} xl:whitespace-nowrap xl:text-right`}>
                                                    <TableCellLabel className={labelResponsif}>Anggaran</TableCellLabel>
                                                    <span className={row.anggaran === null ? 'text-muted' : 'font-medium'}>{formatRupiah(row.anggaran)}</span>
                                                </TableCell>
                                                <TableCell className={selResponsif}>
                                                    <TableCellLabel className={labelResponsif}>Status</TableCellLabel>
                                                    <Badge variant={status.variant}>{status.label}</Badge>
                                                </TableCell>
                                                {kegiatan.can.kelola && (
                                                    <TableCell className={`${selResponsif} xl:text-right`}>
                                                        {/* Klaim pengukuran dikelola lewat siklus pengukuran, bukan halaman kegiatan Rencana Aksi. */}
                                                        {row.sumber_klaim === 'rencana_aksi' && (
                                                            <ActionMenu
                                                                items={[
                                                                    { key: 'rencana', label: 'Catat rencana', icon: Pencil, href: `/rencana-aksi/${rencanaAksiId}/kegiatan/${row.kegiatan_id}` },
                                                                    { key: 'realisasi', label: 'Catat realisasi', icon: ClipboardCheck, href: `/rencana-aksi/${rencanaAksiId}/kegiatan/${row.kegiatan_id}/realisasi` },
                                                                ]}
                                                                trigger={<MoreHorizontal className="h-4 w-4" aria-hidden="true" />}
                                                                triggerLabel={`Aksi kegiatan ${row.nama}`}
                                                                triggerClassName="h-8 w-8 px-0"
                                                                disabled={kotor}
                                                            />
                                                        )}
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </section>
                    );
                })}
            </CardContent>

            <Modal isOpen={terbuka} onClose={tutup} title={`Tambah kegiatan — ${namaPeriode(data.periode_id)}`} size="lg">
                <form onSubmit={kirim} className="space-y-4" aria-busy={processing}>
                    {galatUmum && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark">{galatUmum}</p>}
                    {fieldErrors.periode_id && <p role="alert" className="text-sm text-danger">{fieldErrors.periode_id}</p>}
                    <Input id="kegiatan-ra-nama" label="Nama kegiatan" required maxLength={500} value={data.nama} onChange={(e) => setData('nama', e.target.value)} error={fieldErrors.nama} />
                    <Textarea id="kegiatan-ra-tujuan" label="Tujuan (opsional)" maxLength={10000} value={data.tujuan} onChange={(e) => setData('tujuan', e.target.value)} error={fieldErrors.tujuan} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input id="kegiatan-ra-anggaran" label="Anggaran (Rp)" type="number" inputMode="numeric" min={0} step={1} value={data.anggaran}
                            onChange={(e) => setData('anggaran', e.target.value)} error={fieldErrors.anggaran} helperText={bantuanAnggaran(data.anggaran)} />
                        <Input id="kegiatan-ra-sasaran" label="Sasaran peserta" type="number" min={0} max={2147483647} value={data.sasaran_peserta} onChange={(e) => setData('sasaran_peserta', e.target.value)} error={fieldErrors.sasaran_peserta} />
                        <Input id="kegiatan-ra-tanggal" label="Tanggal rencana" type="date" value={data.tanggal_rencana} onChange={(e) => setData('tanggal_rencana', e.target.value)} error={fieldErrors.tanggal_rencana} />
                        <Input id="kegiatan-ra-keterangan" label="Keterangan peserta" maxLength={255} value={data.keterangan_peserta} onChange={(e) => setData('keterangan_peserta', e.target.value)} error={fieldErrors.keterangan_peserta} />
                        <Input id="kegiatan-ra-lokasi" label="Lokasi" maxLength={255} value={data.lokasi} onChange={(e) => setData('lokasi', e.target.value)} error={fieldErrors.lokasi} />
                        <Select id="kegiatan-ra-komponen" label="Komponen (opsional)" value={data.komponen_id} onChange={(e) => setData('komponen_id', e.target.value)} error={fieldErrors.komponen_id}
                            options={[{ value: '', label: 'Tanpa komponen tertentu' }, ...komponen.map((k) => ({ value: k.komponen_id, label: `${k.kode} — ${k.label}` }))]} />
                        <Select id="kegiatan-ra-arah" label="Arah dampak" required value={data.arah_dampak} onChange={(e) => setData('arah_dampak', e.target.value as ArahDampak)} error={fieldErrors.arah_dampak} options={ARAH_DAMPAK_OPTIONS} />
                    </div>
                    <Input id="kegiatan-ra-catatan" label="Catatan klaim (opsional)" maxLength={255} value={data.catatan} onChange={(e) => setData('catatan', e.target.value)} error={fieldErrors.catatan} />
                    {terbukaBagiAktor.length > 1 && (
                        <fieldset className="space-y-2">
                            <legend className="text-sm font-medium text-ink">Salin ke periode lain (opsional)</legend>
                            <p className="text-xs text-muted">Setiap salinan menjadi kegiatan tersendiri dengan anggaran yang sama dan dapat diubah. Tanggal rencana dan berkas tidak ikut disalin.</p>
                            <div className="flex flex-wrap gap-3">
                                {terbukaBagiAktor.filter((item) => item.id !== data.periode_id).map((item) => (
                                    <label key={item.id} className="inline-flex items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/25"
                                            checked={data.salin_ke_periode_ids.includes(item.id)} onChange={() => toggleSalin(item.id)} />
                                        {item.nama ?? 'Periode'}
                                    </label>
                                ))}
                            </div>
                            {galatSalin && <p role="alert" className="text-sm text-danger">{galatSalin}</p>}
                        </fieldset>
                    )}
                    <div className="flex justify-end gap-2 border-t border-border pt-4">
                        <Button type="button" variant="outline" disabled={processing} onClick={tutup}>Batal</Button>
                        <Button type="submit" isLoading={processing} disabled={processing}>Simpan kegiatan</Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}
