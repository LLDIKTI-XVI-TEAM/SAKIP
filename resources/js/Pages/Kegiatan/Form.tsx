import { useId, useRef, useState, type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { bantuanAnggaran } from '@/lib/rupiah';
import { statusKegiatanConfig, type KegiatanFormProps, type RencanaKegiatan, type StatusTujuan } from '@/types/kegiatan';
import { BerkasPerencanaanPanel } from './Partials/BerkasPerencanaanPanel';
import { KlaimPanel } from './Partials/KlaimPanel';
import { StatusKegiatanModal, aksiStatusKegiatan } from './Partials/StatusKegiatanModal';

const nilaiAwal = (kegiatan: RencanaKegiatan) => ({
    unit_id: kegiatan.unit_id,
    tahun: String(kegiatan.tahun),
    periode_id: kegiatan.periode_id,
    nama: kegiatan.nama, tujuan: kegiatan.tujuan ?? '',
    sasaran_peserta: kegiatan.sasaran_peserta == null ? '' : String(kegiatan.sasaran_peserta),
    keterangan_peserta: kegiatan.keterangan_peserta ?? '', lokasi: kegiatan.lokasi ?? '',
    tanggal_rencana: kegiatan.tanggal_rencana ?? '', anggaran: kegiatan.anggaran ?? '', expected_state: kegiatan.version_token,
});

export default function Form({ kegiatan, unitOptions, periodeOptions, opsiPeriodeTujuan, can, klaim, berkasPerencanaan }: KegiatanFormProps) {
    const form = useForm(nilaiAwal(kegiatan));
    // preserveState mempertahankan form.data setelah status berubah; data terkunci merender nilai server, bukan draf (Q69 §69.3).
    const terkunci = !can.ubah_rencana;
    const data = terkunci ? nilaiAwal(kegiatan) : form.data;
    const errors: typeof form.errors = terkunci ? {} : form.errors;
    const [aksiStatus, setAksiStatus] = useState<StatusTujuan | null>(null);
    const judulStatus = useRef<HTMLHeadingElement>(null);
    const judulStatusId = useId();
    const statusConfig = statusKegiatanConfig[kegiatan.status] ?? { label: kegiatan.status, variant: 'muted' as const };
    const path = `/kegiatan/${kegiatan.id}`;
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
            preserveScroll: true,
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
    return <AuthenticatedLayout title={title} breadcrumbs={[{ label: 'Kegiatan', href: '/kegiatan' }, { label: title }]}>
        <Head title={title} />
        <div className="mx-auto max-w-5xl space-y-5">
            <Link href="/kegiatan" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Kembali ke daftar
            </Link>
            {/* Di luar <form> utama: modal tanpa portal akan menjadi form bersarang dan ikut nonaktif oleh fieldset terkunci. */}
            <Card role="region" aria-labelledby={judulStatusId}>
                <CardContent className="space-y-4 p-4 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 id={judulStatusId} ref={judulStatus} tabIndex={-1} className="text-base font-semibold text-ink focus:outline-none">Status pelaksanaan</h2>
                        <Badge variant={statusConfig.variant}>{statusConfig.label}</Badge>
                    </div>
                    {kegiatan.justifikasi && (
                        <dl className="space-y-2 rounded-lg border border-border bg-soft/50 p-3 text-sm">
                            {kegiatan.periode_tujuan && <div><dt className="text-xs text-muted">Periode tujuan</dt><dd className="font-medium text-ink">{kegiatan.periode_tujuan} {kegiatan.tahun}</dd></div>}
                            <div><dt className="text-xs text-muted">Justifikasi</dt><dd className="whitespace-pre-line break-words font-medium text-ink">{kegiatan.justifikasi}</dd></div>
                        </dl>
                    )}
                    {can.ubah_status.length > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            {can.ubah_status.map((status) => {
                                const { label, icon: Icon, danger } = aksiStatusKegiatan[status];
                                // key per status: tombol lama dilepas setelah transisi sehingga fokus jatuh ke judul kartu.
                                return <Button key={status} type="button" variant="outline" className={danger ? 'border-danger/40 text-danger hover:bg-danger/10' : undefined} onClick={() => setAksiStatus(status)}>
                                    <Icon className="h-4 w-4" aria-hidden="true" />
                                    {label}
                                </Button>;
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
            {aksiStatus && <StatusKegiatanModal
                key={`${kegiatan.id}:${aksiStatus}`}
                kegiatan={{ ...kegiatan, periode: kegiatan.periode_nama }}
                status={aksiStatus}
                opsiPeriodeTujuan={opsiPeriodeTujuan}
                fokusCadangan={judulStatus}
                onClose={() => setAksiStatus(null)}
            />}
            <Card>
                <CardContent className="p-4 sm:p-6">
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                    {terkunci
                        ? <p className="mb-5 flex items-center gap-2 text-sm text-muted"><Lock className="h-4 w-4" aria-hidden="true" />Data rencana terkunci karena status sudah berubah.</p>
                        : <p className="mb-6 text-sm text-muted">Catat rencana kegiatan untuk unit dan periode aktif.</p>}
                    <form onSubmit={submit} className="space-y-5">
                        {!terkunci && (form.hasErrors || failure) && <div ref={summary} tabIndex={-1} role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark focus:outline-none focus:ring-2 focus:ring-danger/30">
                            {failure || form.errors.expected_state || 'Periksa kembali isian yang ditandai sebelum menyimpan.'}
                        </div>}
                        <fieldset disabled={terkunci || form.processing || blocked} className="grid gap-5 sm:grid-cols-2">
                            <Select id="unit_id" label="Unit" required hoverScroll value={data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} error={errors.unit_id} options={[{ value: '', label: 'Pilih unit' }, ...unitOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                            <Input label="Tahun" type="number" required min={1} max={9999} value={data.tahun} onChange={(e) => form.setData('tahun', e.target.value)} error={errors.tahun} />
                            <Select id="periode_id" label="Periode" required value={data.periode_id} onChange={(e) => form.setData('periode_id', e.target.value)} error={errors.periode_id} options={[{ value: '', label: 'Pilih periode' }, ...periodeOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
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
                            {failure && <Link href={`${path}/edit`} className="text-sm text-primary underline">Muat ulang form</Link>}
                        </div>}
                    </form>
                </CardContent>
            </Card>
            {klaim && <KlaimPanel kegiatanId={kegiatan.id} klaim={klaim} />}
            {berkasPerencanaan && <BerkasPerencanaanPanel kegiatanId={kegiatan.id} berkas={berkasPerencanaan} />}
        </div>
    </AuthenticatedLayout>;
}
