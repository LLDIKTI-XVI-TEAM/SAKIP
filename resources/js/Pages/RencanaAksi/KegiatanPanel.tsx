import { useState, type FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { bantuanAnggaran, formatRupiah } from '@/lib/rupiah';
import { ARAH_DAMPAK_OPTIONS, statusKegiatanConfig, type ArahDampak } from '@/types/kegiatan';
import type { RencanaAksiKegiatan, RencanaAksiKomponen, RencanaAksiPeriode, TotalAnggaran } from './types';

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

/** Total anggaran beserta penanda kegiatan yang anggarannya belum diisi (Q42 §42.2 butir 2). */
function teksTotal({ total, belum_diisi }: TotalAnggaran): string {
    return belum_diisi > 0 ? `${formatRupiah(total)} (${belum_diisi} kegiatan belum diisi)` : formatRupiah(total);
}

/** Penanda roll-over; menjadi tautan ke baris pasangannya bila baris itu tampil di panel. */
function TautanRollOverText({ label, target }: { label: string; target: string | null }) {
    return target
        ? <a href={`#kegiatan-${target}`} className="block rounded text-xs font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/25">{label}</a>
        : <p className="text-xs text-muted">{label}</p>;
}

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
    // Anchor hanya pada baris pertama per kegiatan; tautan roll-over hanya bila barisnya ada di panel ini.
    const anchor = new Set(kegiatan.daftar.filter((row, index, all) => all.findIndex((other) => other.kegiatan_id === row.kegiatan_id) === index).map((row) => row.id));
    const adaDiPanel = (id: string): boolean => kegiatan.daftar.some((row) => row.kegiatan_id === id);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kegiatan per Periode</CardTitle>
                <p className="text-sm text-muted">Kegiatan yang ditambahkan di sini langsung diklaim ke rencana aksi ini. Klaim menjelaskan dukungan kegiatan dan tidak mengubah nilai target atau pengukuran.</p>
                <p className="text-sm text-ink">Total anggaran rencana aksi: <span className="font-semibold">{teksTotal(kegiatan.total)}</span></p>
            </CardHeader>
            <CardContent className="space-y-4">
                {kotor && (kegiatan.can.tambah || kegiatan.can.kelola) && <p className="text-sm text-muted">Simpan atau buang perubahan target sebelum menambah atau mengelola kegiatan.</p>}
                {periode.length === 0 && <p className="text-sm text-muted">Tidak ada periode efektif untuk rencana aksi ini.</p>}
                {periode.map((item) => {
                    const daftar = kegiatan.daftar.filter((row) => row.periode_id === item.id);
                    const kunci = terkunci.includes(item.id);
                    // Periode tanpa kegiatan tidak punya entri per_periode; totalnya Rp0.
                    const totalPeriode = kegiatan.total.per_periode[item.id] ?? { total: '0', belum_diisi: 0 };

                    return (
                        <section key={item.id} aria-labelledby={`kegiatan-periode-${item.id}`} className="rounded-lg border border-border p-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 id={`kegiatan-periode-${item.id}`} className="flex items-center gap-2 text-sm font-semibold text-ink">
                                    {item.nama ?? 'Periode'}
                                    {kunci && <Badge variant="muted">Terkunci</Badge>}
                                </h3>
                                <p className="text-xs text-muted">Total {teksTotal(totalPeriode)}</p>
                                {kegiatan.can.tambah && !kunci && (
                                    <Button type="button" size="sm" variant="outline" disabled={kotor} onClick={() => buka(item.id)} aria-label={`Tambah kegiatan ${item.nama ?? ''}`}>
                                        <Plus className="h-4 w-4" aria-hidden="true" />
                                        Tambah kegiatan
                                    </Button>
                                )}
                            </div>
                            {daftar.length === 0 ? (
                                <p className="mt-2 text-xs text-muted">Belum ada kegiatan.</p>
                            ) : (
                                <ul className="mt-2 divide-y divide-border">
                                    {daftar.map((row) => {
                                        const status = statusKegiatanConfig[row.status] ?? { label: row.status, variant: 'muted' as const };
                                        const komponenKlaim = komponen.find((item) => item.komponen_id === row.komponen_id);

                                        return (
                                            <li key={row.id} id={anchor.has(row.id) ? `kegiatan-${row.kegiatan_id}` : undefined} className="flex scroll-mt-4 flex-wrap items-start justify-between gap-2 py-2">
                                                <div className="min-w-0 space-y-1">
                                                    <p className="break-words text-sm text-ink">{row.nama}</p>
                                                    <p className="text-xs text-muted">Anggaran: {formatRupiah(row.anggaran)}</p>
                                                    {row.lanjutan && <TautanRollOverText label={`Ditunda → ${namaPeriode(row.lanjutan.periode_id)}`} target={adaDiPanel(row.lanjutan.id) ? row.lanjutan.id : null} />}
                                                    {row.asal && <TautanRollOverText label={`Lanjutan dari ${namaPeriode(row.asal.periode_id)}`} target={adaDiPanel(row.asal.id) ? row.asal.id : null} />}
                                                    {(row.kegiatan_bersama || row.sumber_klaim === 'pengukuran') && (
                                                        <span className="flex flex-wrap gap-1">
                                                            {row.kegiatan_bersama && <Badge variant="info">Kegiatan bersama</Badge>}
                                                            {row.sumber_klaim === 'pengukuran' && <Badge variant="muted">Klaim pengukuran</Badge>}
                                                        </span>
                                                    )}
                                                    <p className="text-xs text-muted">{komponenKlaim ? `${komponenKlaim.kode} — ${komponenKlaim.label}` : row.komponen_id === null ? 'Tanpa komponen tertentu' : 'Komponen tidak tersedia pada snapshot'}</p>
                                                    <Badge variant={row.arah_dampak === 'menambah' ? 'success' : 'warning'}>{row.arah_dampak === 'menambah' ? 'Menambah' : 'Mengurangi'}</Badge>
                                                    {row.catatan && <p className="break-words text-xs text-muted">{row.catatan}</p>}
                                                </div>
                                                <span className="flex items-center gap-2">
                                                    <Badge variant={status.variant}>{status.label}</Badge>
                                                    {/* Klaim pengukuran dikelola lewat siklus pengukuran, bukan halaman Kegiatan. */}
                                                    {kegiatan.can.kelola && row.sumber_klaim === 'rencana_aksi' && (
                                                        <Link href={`/kegiatan/${row.kegiatan_id}/edit`} aria-disabled={kotor} onBefore={() => !kotor} className="rounded text-xs font-medium text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/25">
                                                            Kelola klaim
                                                        </Link>
                                                    )}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
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
