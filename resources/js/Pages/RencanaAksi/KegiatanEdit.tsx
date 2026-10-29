import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { formatRentangPeriode } from '@/lib/rentangPeriode';
import { bantuanAnggaran } from '@/lib/rupiah';
import { BerkasPerencanaanPanel } from '@/Pages/Kegiatan/Partials/BerkasPerencanaanPanel';
import { KlaimPanel } from '@/Pages/Kegiatan/Partials/KlaimPanel';
import { StatusPelaksanaanCard } from '@/Pages/Kegiatan/Partials/StatusPelaksanaanCard';
import type { KegiatanFormProps, RencanaKegiatan } from '@/types/kegiatan';
import type { RencanaAksiIdentitas } from './types';

/**
 * Props halaman ubah kegiatan dalam konteks Rencana Aksi (Q74 §74.1):
 * `FormKegiatan` + panel klaim + berkas perencanaan dan pelaksanaan, ditambah
 * identitas RA pengklaim untuk tautan kembali. Bentuk props mengikuti
 * `FormKegiatanRencanaAksi` dan dipakai bersama halaman catat realisasi.
 */
export interface KegiatanEditProps extends KegiatanFormProps {
    /** Identitas RA pengklaim; hanya dipakai untuk navigasi kembali ke konteks RA. */
    rencanaAksi: RencanaAksiIdentitas;
}

const nilaiAwal = (kegiatan: RencanaKegiatan) => ({
    unit_id: kegiatan.unit_id,
    tahun: String(kegiatan.tahun),
    periode_id: kegiatan.periode_id,
    nama: kegiatan.nama,
    tujuan: kegiatan.tujuan ?? '',
    sasaran_peserta: kegiatan.sasaran_peserta == null ? '' : String(kegiatan.sasaran_peserta),
    keterangan_peserta: kegiatan.keterangan_peserta ?? '',
    lokasi: kegiatan.lokasi ?? '',
    tanggal_rencana: kegiatan.tanggal_rencana ?? '',
    anggaran: kegiatan.anggaran ?? '',
    expected_state: kegiatan.version_token,
});

/**
 * Form catat rencana kegiatan yang dibuka dari menu aksi halaman kegiatan
 * Rencana Aksi; realisasi dicatat di halaman terpisah (`KegiatanRealisasi`).
 * URL mutasi lama dipertahankan; hanya konteks navigasi yang berpindah ke
 * Rencana Aksi, sehingga klaim, berkas, dan status tetap di gerbang server
 * yang sama.
 */
export default function KegiatanEdit({ rencanaAksi, kegiatan, unitOptions, periodeOptions, opsiPeriodeTujuan, can, klaim, berkasPerencanaan }: KegiatanEditProps) {
    const form = useForm(nilaiAwal(kegiatan));
    const { isDirty, setData, setDefaults, data: { expected_state } } = form;
    // Status dapat berubah dari kartu di halaman yang sama: hanya form bersih yang menyerap token dan data baru.
    useEffect(() => {
        if (!isDirty && expected_state !== kegiatan.version_token) {
            const terbaru = nilaiAwal(kegiatan);
            setDefaults(terbaru);
            setData(terbaru);
        }
    }, [kegiatan, isDirty, expected_state, setData, setDefaults]);
    // preserveState mempertahankan form.data setelah status berubah; data terkunci merender nilai server, bukan draf (Q69 §69.3).
    const terkunci = !can.ubah_rencana;
    const data = terkunci ? nilaiAwal(kegiatan) : form.data;
    const errors: typeof form.errors = terkunci ? {} : form.errors;
    const path = `/kegiatan/${kegiatan.id}`;
    const halamanKegiatan = `/rencana-aksi/${rencanaAksi.id}/kegiatan`;
    const halamanUbah = `${halamanKegiatan}/${kegiatan.id}`;
    const submitting = useRef(false);
    const summary = useRef<HTMLDivElement>(null);
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const blocked = Boolean(failure || recovery.recovery);
    const title = 'Ubah rencana kegiatan';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (submitting.current || blocked) return;
        submitting.current = true;
        form.transform((data) => ({ ...data, tahun: Number(data.tahun), sasaran_peserta: data.sasaran_peserta === '' ? null : Number(data.sasaran_peserta), anggaran: data.anggaran === '' ? null : data.anggaran }));
        form.post(path, {
            // Halaman tidak di-remount: sukses menyegarkan baseline form dari props tersimpan.
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const tersimpan = nilaiAwal(page.props.kegiatan as RencanaKegiatan);
                form.setDefaults(tersimpan);
                form.setData(tersimpan);
            },
            onError: () => requestAnimationFrame(() => summary.current?.focus()),
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) {
                    setFailure(response.status === 403 ? 'Hasil penyimpanan belum terkonfirmasi karena akses ditolak. Periksa izin dan data terbaru sebelum mencoba kembali.' : 'Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
            onNetworkError: () => { setFailure('Koneksi terputus. Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'); return false; },
            onCancel: () => setFailure('Permintaan dibatalkan di browser. Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'),
            onFinish: () => { submitting.current = false; },
        });
    };

    const periodeDipilih = periodeOptions.find((option) => option.id === data.periode_id);
    const rentangPeriode = formatRentangPeriode(periodeDipilih?.tanggal ?? null, /^\d{4}$/.test(data.tahun) ? Number(data.tahun) : undefined) ?? undefined;

    return (
        <AuthenticatedLayout
            title={title}
            breadcrumbs={[
                { label: 'Rencana Aksi', href: '/rencana-aksi' },
                { label: rencanaAksi.kode ? `${rencanaAksi.kode} · ${rencanaAksi.tahun}` : `Tahun ${rencanaAksi.tahun}`, href: `/rencana-aksi/${rencanaAksi.id}` },
                { label: 'Kegiatan', href: halamanKegiatan },
                { label: title },
            ]}
        >
            <Head title={title} />
            <div className="mx-auto max-w-5xl space-y-5">
                <Link href={halamanKegiatan} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke kegiatan rencana aksi
                </Link>
                {/* Di luar <form> utama: modal tanpa portal akan menjadi form bersarang dan ikut nonaktif oleh fieldset terkunci. */}
                <StatusPelaksanaanCard kegiatan={kegiatan} ubahStatus={can.ubah_status} opsiPeriodeTujuan={opsiPeriodeTujuan} />
                <Card>
                    <CardHeader><CardTitle>Catat rencana kegiatan</CardTitle></CardHeader>
                    <CardContent className="p-4 sm:p-6">
                        <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                        {terkunci
                            ? <p className="mb-5 flex items-center gap-2 text-sm text-muted"><Lock className="h-4 w-4" aria-hidden="true" />Data rencana terkunci karena status sudah berubah.</p>
                            : <p className="mb-6 text-sm text-muted">Catat rencana kegiatan untuk unit dan periode aktif.</p>}
                        <form onSubmit={submit} aria-label="Catat rencana kegiatan" className="space-y-5" aria-busy={form.processing}>
                            {!terkunci && (form.hasErrors || failure) && <div ref={summary} tabIndex={-1} role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark focus:outline-none focus:ring-2 focus:ring-danger/30">
                                {failure || form.errors.expected_state || 'Periksa kembali isian yang ditandai sebelum menyimpan.'}
                            </div>}
                            <fieldset disabled={terkunci || form.processing || blocked} className="grid gap-5 sm:grid-cols-2">
                                <Select id="unit_id" label="Unit" required hoverScroll value={data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} error={errors.unit_id} options={[{ value: '', label: 'Pilih unit' }, ...unitOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                                <Input label="Tahun" type="number" required min={1} max={9999} value={data.tahun} onChange={(e) => form.setData('tahun', e.target.value)} error={errors.tahun} />
                                <Select id="periode_id" label="Periode" required value={data.periode_id} onChange={(e) => form.setData('periode_id', e.target.value)} error={errors.periode_id} helperText={rentangPeriode} options={[{ value: '', label: 'Pilih periode' }, ...periodeOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                                <Input label="Tanggal rencana" type="date" value={data.tanggal_rencana} onChange={(e) => form.setData('tanggal_rencana', e.target.value)} error={errors.tanggal_rencana} />
                                <div className="sm:col-span-2"><Input label="Nama kegiatan" required maxLength={500} placeholder="Contoh: Rapat Koordinasi Penyusunan Laporan Kinerja" value={data.nama} onChange={(e) => form.setData('nama', e.target.value)} error={errors.nama} /></div>
                                <div className="sm:col-span-2"><Textarea label="Tujuan" maxLength={10000} placeholder="Jelaskan tujuan pelaksanaan kegiatan secara ringkas..." value={data.tujuan} onChange={(e) => form.setData('tujuan', e.target.value)} error={errors.tujuan} /></div>
                                <Input id="anggaran" label="Anggaran (Rp)" type="number" inputMode="numeric" min={0} step={1} placeholder="Contoh: 5000000" value={data.anggaran} onChange={(e) => form.setData('anggaran', e.target.value)} error={errors.anggaran} helperText={kegiatan.anggaran === null && data.anggaran === '' ? 'Belum diisi. ' + bantuanAnggaran('') : bantuanAnggaran(data.anggaran)} />
                                <Input label="Sasaran peserta" type="number" min={0} max={2147483647} placeholder="Contoh: 30" value={data.sasaran_peserta} onChange={(e) => form.setData('sasaran_peserta', e.target.value)} error={errors.sasaran_peserta} />
                                <Input label="Keterangan peserta" maxLength={255} placeholder="Contoh: Tim Pokja dan perwakilan pimpinan perguruan tinggi" value={data.keterangan_peserta} onChange={(e) => form.setData('keterangan_peserta', e.target.value)} error={errors.keterangan_peserta} />
                                <div className="sm:col-span-2"><Input label="Lokasi" maxLength={255} placeholder="Contoh: Ruang Rapat LLDIKTI XVI / Daring via Zoom" value={data.lokasi} onChange={(e) => form.setData('lokasi', e.target.value)} error={errors.lokasi} /></div>
                            </fieldset>
                            {!terkunci && <div className="flex flex-wrap items-center gap-3 border-t border-border pt-5">
                                <Button type="submit" disabled={blocked} isLoading={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan kegiatan'}</Button>
                                {failure && <Link href={halamanUbah} className="text-sm text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/25">Muat ulang form</Link>}
                            </div>}
                        </form>
                    </CardContent>
                </Card>
                {klaim && <KlaimPanel kegiatanId={kegiatan.id} klaim={klaim} rencanaAksiKonteks={rencanaAksi.id} />}
                {berkasPerencanaan && <BerkasPerencanaanPanel kegiatanId={kegiatan.id} berkas={berkasPerencanaan} />}
            </div>
        </AuthenticatedLayout>
    );
}
