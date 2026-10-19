import { useEffect, useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { History } from 'lucide-react';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { RiwayatTargetRenstraModal } from './RiwayatTargetRenstraModal';
import type { IndikatorKinerjaItem, RenstraOption, SasaranStrategisItem } from '@/types/sasaran-indikator';

const BELUM_PASTI = 'Hasil simpan belum dapat dipastikan. Muat data terbaru sebelum mencoba lagi.';

type FormTarget = { nilai: Record<string, string | null>; expected_state: string };

/** Tabel indikator × tahun Renstra. Nilai tetap string; normalisasi, perbandingan, dan otorisasi dimiliki server. */
export function TargetRenstraTable({ renstra, sasarans, canEdit, onSaved, onSaving }: {
    renstra: RenstraOption;
    sasarans: SasaranStrategisItem[];
    canEdit: boolean;
    onSaved: (message: string) => void;
    onSaving?: (saving: boolean) => void;
}) {
    const formatNilai = useFormatNilai();
    const recovery = useAuthRecovery();
    const form = useForm<FormTarget>({ nilai: {}, expected_state: '' });
    const errors: Record<string, string | undefined> = form.errors;
    const [editing, setEditing] = useState<string | null>(null);
    const [failure, setFailure] = useState('');
    const [riwayat, setRiwayat] = useState<IndikatorKinerjaItem | null>(null);
    const root = useRef<HTMLDivElement>(null);
    const kembaliFokus = useRef<string | null>(null);
    const years = Array.from({ length: renstra.tahun_selesai - renstra.tahun_mulai + 1 }, (_, i) => renstra.tahun_mulai + i);
    // Status absen berarti fail-closed; server tetap menolak Renstra nonaktif/diarsipkan.
    const renstraEditable = renstra.status === 'draft' || renstra.status === 'aktif';
    // Hasil ambigu atau token basi: simpan ulang ditahan sampai data terbaru dimuat, seperti editor target PK.
    const blocked = Boolean(failure || recovery.recovery || errors.expected_state);

    const nilaiTersimpan = (ind: IndikatorKinerjaItem, tahun: number) => ind.target_renstra?.find((t) => t.tahun === tahun)?.nilai ?? null;

    const mulai = (ind: IndikatorKinerjaItem) => {
        // Isi ulang dari props terkini: preserveState menahan isian lama sehingga token basi memicu 409.
        const nilai = Object.fromEntries(years.filter((tahun) => tahun >= ind.tahun_mulai_berlaku).map((tahun) => [tahun, nilaiTersimpan(ind, tahun)]));
        form.setData({ nilai, expected_state: ind.target_renstra_state ?? '' });
        form.clearErrors();
        // Kegagalan ambigu tidak dihapus di sini: props bisa basi, jadi blokir simpan baru lepas setelah data terbaru dimuat (Muat ulang atau ganti Renstra).
        setEditing(ind.id);
    };

    // Tombol yang sedang fokus hilang saat keluar edit; fokus dikembalikan ke tombol Ubah baris itu,
    // atau ke Riwayat bila Ubah disembunyikan karena sesi perlu masuk ulang.
    const selesai = (id: string) => { kembaliFokus.current = id; setEditing(null); };
    useEffect(() => {
        if (editing !== null || kembaliFokus.current === null) return;
        const tombol = (atribut: string) => root.current?.querySelector<HTMLButtonElement>(`[${atribut}="${kembaliFokus.current}"]`);
        (tombol('data-ubah') ?? tombol('data-riwayat'))?.focus();
        kembaliFokus.current = null;
    }, [editing]);

    // Seperti editor target PK: selama simpan berjalan navigasi Inertia lain ditahan. Membatalkan PUT di klien
    // tidak membatalkan transaksi server, dan tabel bisa dilepas sebelum peringatan hasil ambigu tampil.
    useEffect(() => {
        if (!form.processing) return;
        return router.on('before', (event) => { if (!event.detail.visit.prefetch) event.preventDefault(); });
    }, [form.processing]);

    const simpan = (ind: IndikatorKinerjaItem) => {
        const path = `/perencanaan/indikator/${ind.id}/target-renstra`;
        setFailure('');
        form.transform((data) => ({ ...data, nilai: Object.fromEntries(Object.entries(data.nilai).map(([tahun, nilai]) => [tahun, nilai === '' ? null : nilai])) }));
        form.put(path, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => onSaving?.(true),
            onFinish: () => onSaving?.(false),
            onSuccess: (page) => {
                const hasil: unknown = page.flash.target_renstra;
                // Flash milik indikator lain bukan bukti baris ini tersimpan.
                if (typeof hasil !== 'object' || hasil === null || !('indikator_id' in hasil) || hasil.indikator_id !== ind.id
                    || !('tahun' in hasil) || !Array.isArray(hasil.tahun)) {
                    setFailure(BELUM_PASTI);
                    return;
                }
                selesai(ind.id);
                onSaved(hasil.tahun.length > 0 ? `Target Renstra ${ind.kode} tersimpan.` : 'Tidak ada perubahan.');
            },
            onHttpException: (response) => {
                if (response.status === 403) setFailure('Akses ditolak. Hasil simpan belum dapat dipastikan. Periksa data setelah akses pulih.');
                else if (!recovery.handleHttpException(response, { effectiveMethod: 'put', path, mutation: true })) setFailure(BELUM_PASTI);
                return false;
            },
            onNetworkError: () => { setFailure(BELUM_PASTI); return false; },
            onCancel: () => setFailure(BELUM_PASTI),
        });
    };

    const muatUlang = (id: string) => router.reload({ onSuccess: () => { selesai(id); setFailure(''); } });

    return <div ref={root} className="space-y-6">
        <AuthRecoveryNotice recovery={recovery.recovery} />
        {sasarans.map((sasaran) => <Card key={sasaran.id}>
            <div className="border-b border-border p-4 md:p-5">
                <Badge variant="primary" size="sm" className="font-mono">{sasaran.kode}</Badge>
                <h2 className="mt-1 text-base font-semibold leading-relaxed text-ink">{sasaran.deskripsi}</h2>
            </div>
            {/* Di HP setiap indikator menjadi kartu dengan label tahun per sel, tanpa scroll horizontal. */}
            <Table wrapperClassName="overflow-x-visible" className="block md:table">
                <caption className="sr-only">Target Renstra per tahun untuk sasaran {sasaran.kode}</caption>
                <TableHeader className="hidden md:table-header-group">
                    <TableRow>
                        <TableHead className="w-28">Kode</TableHead>
                        <TableHead>Indikator</TableHead>
                        {years.map((tahun) => <TableHead key={tahun} className="text-right">{tahun}</TableHead>)}
                        <TableHead className="text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody className="block md:table-row-group">
                    {sasaran.indikator_kinerjas.map((ind) => {
                        const aktif = editing === ind.id;
                        const bisaUbah = canEdit && renstraEditable && ind.status === 'aktif' && editing === null && !recovery.recovery;
                        const pesanBaris = errors.nilai ?? errors.expected_state ?? failure;
                        return <TableRow key={ind.id} className="grid grid-cols-2 gap-3 p-4 md:table-row md:align-top">
                            <TableHead scope="row" className="h-auto p-0 font-mono text-sm normal-case tracking-normal md:px-4 md:py-3.5">{ind.kode}</TableHead>
                            <TableCell className="p-0 md:px-4 md:py-3.5">
                                <span className="text-ink">{ind.nama}</span>
                                {aktif && pesanBaris && <p role="alert" className="mt-1 text-xs font-medium text-danger">{pesanBaris}</p>}
                            </TableCell>
                            {years.map((tahun) => {
                                const nilai = nilaiTersimpan(ind, tahun);
                                return <TableCell key={tahun} className="min-w-0 p-0 tabular-nums md:px-4 md:py-3.5 md:text-right">
                                    <span className="mb-1 block text-xs text-muted md:hidden">{tahun}</span>
                                    {tahun < ind.tahun_mulai_berlaku ? <Badge variant="muted" size="sm">Belum berlaku</Badge>
                                        : aktif ? <Input
                                            label={`Target ${tahun} ${ind.kode}`}
                                            labelClassName="sr-only"
                                            inputMode="decimal"
                                            autoComplete="off"
                                            // Tombol Ubah hilang saat edit; fokus pindah ke isian pertama agar pengguna keyboard tidak kehilangan posisi.
                                            autoFocus={tahun === Math.max(renstra.tahun_mulai, ind.tahun_mulai_berlaku)}
                                            className="md:min-w-24 md:text-right"
                                            value={form.data.nilai[tahun] ?? ''}
                                            readOnly={form.processing}
                                            onChange={(event) => form.setData('nilai', { ...form.data.nilai, [tahun]: event.target.value })}
                                            error={errors[`nilai.${tahun}`]}
                                        />
                                            : nilai === null ? <><span aria-hidden="true" className="text-muted">—</span><span className="sr-only">Target Renstra belum ada</span></>
                                                : formatNilai(nilai, ind.desimal_tampilan)}
                                </TableCell>;
                            })}
                            <TableCell className="col-span-2 p-0 md:px-4 md:py-3.5 md:text-right">
                                <div className="flex flex-wrap gap-2 md:justify-end">
                                    <Button type="button" size="sm" variant="outline" className="w-9 px-0" aria-label={`Riwayat target Renstra ${ind.kode}`} title="Riwayat" data-riwayat={ind.id} onClick={() => setRiwayat(ind)}>
                                        <History className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                    {aktif ? <>
                                        {blocked && !recovery.recovery && <Button type="button" size="sm" variant="outline" onClick={() => muatUlang(ind.id)}>Muat ulang</Button>}
                                        <Button type="button" size="sm" onClick={() => simpan(ind)} disabled={blocked} isLoading={form.processing}>Simpan</Button>
                                        <Button type="button" size="sm" variant="outline" onClick={() => selesai(ind.id)} disabled={form.processing}>Batal</Button>
                                    </> : bisaUbah && <Button type="button" size="sm" variant="outline" aria-label={`Ubah target Renstra ${ind.kode}`} data-ubah={ind.id} onClick={() => mulai(ind)}>Ubah</Button>}
                                </div>
                            </TableCell>
                        </TableRow>;
                    })}
                </TableBody>
            </Table>
        </Card>)}
        {riwayat && <RiwayatTargetRenstraModal indikator={riwayat} onClose={() => setRiwayat(null)} />}
    </div>;
}
