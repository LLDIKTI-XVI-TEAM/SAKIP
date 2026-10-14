import { useRef, useState, type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { KegiatanFormProps } from '@/types/kegiatan';

export default function Form({ kegiatan, tahunDefault, unitOptions, periodeOptions }: KegiatanFormProps) {
    const form = useForm({
        unit_id: kegiatan?.unit_id ?? unitOptions[0]?.id ?? '',
        tahun: String(kegiatan?.tahun ?? tahunDefault),
        periode_id: kegiatan?.periode_id ?? periodeOptions[0]?.id ?? '',
        nama: kegiatan?.nama ?? '', tujuan: kegiatan?.tujuan ?? '',
        sasaran_peserta: kegiatan?.sasaran_peserta == null ? '' : String(kegiatan.sasaran_peserta),
        keterangan_peserta: kegiatan?.keterangan_peserta ?? '', lokasi: kegiatan?.lokasi ?? '',
        tanggal_rencana: kegiatan?.tanggal_rencana ?? '', expected_state: kegiatan?.version_token ?? '',
    });
    const submitting = useRef(false);
    const summary = useRef<HTMLDivElement>(null);
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const blocked = Boolean(failure || recovery.recovery);
    const title = kegiatan ? 'Ubah rencana kegiatan' : 'Buat rencana kegiatan';
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (submitting.current || blocked) return;
        submitting.current = true;
        form.transform((data) => ({ ...data, tahun: Number(data.tahun), sasaran_peserta: data.sasaran_peserta === '' ? null : Number(data.sasaran_peserta) }));
        form.post(kegiatan ? `/kegiatan/${kegiatan.id}` : '/kegiatan', {
            preserveScroll: true,
            onError: () => requestAnimationFrame(() => summary.current?.focus()),
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path: kegiatan ? `/kegiatan/${kegiatan.id}` : '/kegiatan', mutation: true })) {
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
        <Card className="max-w-4xl">
            <CardContent className="p-4 sm:p-6">
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                <p className="mb-6 text-sm text-muted">Catat rencana kegiatan untuk unit dan periode aktif. Kegiatan baru berstatus Rencana.</p>
                <form onSubmit={submit} className="space-y-5">
                    {(form.hasErrors || failure) && <div ref={summary} tabIndex={-1} role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark focus:outline-none focus:ring-2 focus:ring-danger/30">
                        {failure || form.errors.expected_state || 'Periksa kembali isian yang ditandai sebelum menyimpan.'}
                    </div>}
                    <fieldset disabled={form.processing || blocked} className="grid gap-5 sm:grid-cols-2">
                        <Select id="unit_id" label="Unit" required hoverScroll value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} error={form.errors.unit_id} options={[{ value: '', label: 'Pilih unit' }, ...unitOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                        <Input label="Tahun" type="number" required min={1} max={9999} value={form.data.tahun} onChange={(e) => form.setData('tahun', e.target.value)} error={form.errors.tahun} />
                        <Select id="periode_id" label="Periode" required value={form.data.periode_id} onChange={(e) => form.setData('periode_id', e.target.value)} error={form.errors.periode_id} options={[{ value: '', label: 'Pilih periode' }, ...periodeOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                        <Input label="Tanggal rencana" type="date" value={form.data.tanggal_rencana} onChange={(e) => form.setData('tanggal_rencana', e.target.value)} error={form.errors.tanggal_rencana} />
                        <div className="sm:col-span-2"><Input label="Nama kegiatan" required maxLength={500} placeholder="Contoh: Rapat Koordinasi Penyusunan Laporan Kinerja" value={form.data.nama} onChange={(e) => form.setData('nama', e.target.value)} error={form.errors.nama} /></div>
                        <div className="sm:col-span-2"><Textarea label="Tujuan" maxLength={10000} placeholder="Jelaskan tujuan pelaksanaan kegiatan secara ringkas..." value={form.data.tujuan} onChange={(e) => form.setData('tujuan', e.target.value)} error={form.errors.tujuan} /></div>
                        <Input label="Sasaran peserta" type="number" min={0} max={2147483647} placeholder="Contoh: 30" value={form.data.sasaran_peserta} onChange={(e) => form.setData('sasaran_peserta', e.target.value)} error={form.errors.sasaran_peserta} />
                        <Input label="Keterangan peserta" maxLength={255} placeholder="Contoh: Tim Pokja dan perwakilan pimpinan perguruan tinggi" value={form.data.keterangan_peserta} onChange={(e) => form.setData('keterangan_peserta', e.target.value)} error={form.errors.keterangan_peserta} />
                        <div className="sm:col-span-2"><Input label="Lokasi" maxLength={255} placeholder="Contoh: Ruang Rapat LLDIKTI XVI / Daring via Zoom" value={form.data.lokasi} onChange={(e) => form.setData('lokasi', e.target.value)} error={form.errors.lokasi} /></div>
                    </fieldset>
                    <div className="flex flex-wrap items-center gap-3 border-t border-border pt-5">
                        <Button type="submit" disabled={blocked} isLoading={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan kegiatan'}</Button>
                        <Link href="/kegiatan" className="text-sm font-medium text-primary hover:underline">Kembali ke daftar</Link>
                        {failure && <Link href={kegiatan ? `/kegiatan/${kegiatan.id}/edit` : '/kegiatan/buat'} className="text-sm text-primary underline">Muat ulang form</Link>}
                    </div>
                </form>
            </CardContent>
        </Card>
    </AuthenticatedLayout>;
}
