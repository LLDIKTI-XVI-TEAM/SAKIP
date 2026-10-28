import { useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { bantuanAnggaran } from '@/lib/rupiah';
import type { KegiatanFormProps, RencanaKegiatan } from '@/types/kegiatan';

interface Props {
    kegiatan: RencanaKegiatan;
    can: KegiatanFormProps['can'];
    pelaksanaan: KegiatanFormProps['pelaksanaan'];
    halamanUbah: string;
}

const nilaiAwal = (kegiatan: RencanaKegiatan) => ({
    tanggal_realisasi: kegiatan.tanggal_realisasi ?? '',
    biaya_terpakai: kegiatan.biaya_terpakai ?? '',
    realisasi_peserta: kegiatan.realisasi_peserta == null ? '' : String(kegiatan.realisasi_peserta),
    uraian_pelaksanaan: kegiatan.uraian_pelaksanaan ?? '',
    kendala: kegiatan.kendala ?? '',
    strategi_tindaklanjut: kegiatan.strategi_tindaklanjut ?? '',
    expected_state: kegiatan.version_token,
});

/** Form pelaksanaan terpisah: capability dan gerbang tetap ditentukan server, draf tidak ditimpa unggahan berkas. */
export function RealisasiKegiatanPanel({ kegiatan, can, pelaksanaan, halamanUbah }: Props) {
    const form = useForm(nilaiAwal(kegiatan));
    const { isDirty, setData, setDefaults, data: { expected_state } } = form;
    // Form bersih menyerap keadaan terbaru; draf yang sudah diubah tetap memakai token asal untuk mendeteksi konflik.
    useEffect(() => {
        if (!isDirty && expected_state !== kegiatan.version_token) {
            const terbaru = nilaiAwal(kegiatan);
            setDefaults(terbaru);
            setData(terbaru);
        }
    }, [kegiatan, isDirty, expected_state, setData, setDefaults]);
    const errors: Record<string, string | undefined> = form.errors;
    const id = useId();
    const summary = useRef<HTMLDivElement>(null);
    const submitting = useRef(false);
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const blocked = Boolean(failure || recovery.recovery);
    const terkunci = !can.catat_realisasi;
    const data = terkunci ? nilaiAwal(kegiatan) : form.data;
    const path = `/kegiatan/${kegiatan.id}/realisasi`;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (submitting.current || blocked || terkunci) return;
        const submitter = (event.nativeEvent as SubmitEvent).submitter;
        const tandai = submitter instanceof HTMLButtonElement && submitter.value === 'terlaksana';
        if (tandai && !can.tandai_terlaksana) return;
        submitting.current = true;
        form.transform((values) => ({ ...values,
            tanggal_realisasi: values.tanggal_realisasi || null,
            biaya_terpakai: values.biaya_terpakai === '' ? null : values.biaya_terpakai,
            realisasi_peserta: values.realisasi_peserta === '' ? null : Number(values.realisasi_peserta),
            tandai_terlaksana: tandai,
        }));
        form.post(path, {
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
                    setFailure('Hasil penyimpanan realisasi belum terkonfirmasi. Periksa izin dan data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
            onNetworkError: () => { setFailure('Koneksi terputus. Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'); return false; },
            onCancel: () => setFailure('Permintaan dibatalkan di browser. Periksa data terbaru sebelum mencoba kembali.'),
            onFinish: () => { submitting.current = false; },
        });
    };

    return (
        <Card role="region" aria-labelledby={`${id}-judul`}>
            <CardHeader><CardTitle id={`${id}-judul`}>Catat realisasi</CardTitle></CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted">Simpan realisasi secara bertahap. Saat menandai terlaksana, tanggal, biaya terpakai, peserta riil, dan bukti wajib harus lengkap. Narasi opsional.</p>
                {terkunci && <p className="text-sm text-muted">Realisasi hanya dapat dibaca pada status atau tahun kegiatan ini.</p>}
                {pelaksanaan.perlu_koreksi && <div role="status" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-ink">
                    <p className="font-semibold">Perlu koreksi bukti pelaksanaan</p>
                    <p>Lengkapi bukti tambahan tanpa menghapus atau mengganti bukti yang sudah menjadi dasar kegiatan terlaksana.</p>
                    <ul className="mt-2 list-inside list-disc">{pelaksanaan.persyaratan_kurang.map((nama) => <li key={nama}>{nama}</li>)}</ul>
                </div>}
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                <form onSubmit={submit} aria-label="Catat realisasi" aria-busy={form.processing} className="space-y-5">
                    {(form.hasErrors || failure) && <div ref={summary} tabIndex={-1} role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark focus:outline-none focus:ring-2 focus:ring-danger/30">
                        {failure || errors.expected_state || errors.status || errors.bukti || 'Periksa kembali isian realisasi yang ditandai.'}
                    </div>}
                    <fieldset disabled={terkunci || form.processing || blocked} className="grid gap-5 sm:grid-cols-2">
                        <Input id={`${id}-tanggal`} label="Tanggal realisasi" type="date" min={pelaksanaan.rentang?.mulai} max={pelaksanaan.rentang?.selesai}
                            value={data.tanggal_realisasi} onChange={(e) => form.setData('tanggal_realisasi', e.target.value)} error={form.errors.tanggal_realisasi}
                            helperText={pelaksanaan.rentang ? `Rentang periode: ${pelaksanaan.rentang.mulai} sampai ${pelaksanaan.rentang.selesai}.` : 'Tanggal periode belum diatur di Master Periode.'} />
                        <Input id={`${id}-biaya`} label="Biaya terpakai (Rp)" type="number" inputMode="numeric" min={0} step={1} max="999999999999999"
                            value={data.biaya_terpakai} onChange={(e) => form.setData('biaya_terpakai', e.target.value)} error={form.errors.biaya_terpakai}
                            helperText={`${bantuanAnggaran(data.biaya_terpakai)} Boleh melebihi anggaran rencana; total anggaran tetap memakai rencana.`} />
                        <Input id={`${id}-peserta`} label="Peserta riil" type="number" min={0} max={2147483647} step={1}
                            value={data.realisasi_peserta} onChange={(e) => form.setData('realisasi_peserta', e.target.value)} error={form.errors.realisasi_peserta} />
                        <div className="sm:col-span-2"><Textarea id={`${id}-uraian`} label="Uraian pelaksanaan" maxLength={10000}
                            value={data.uraian_pelaksanaan} onChange={(e) => form.setData('uraian_pelaksanaan', e.target.value)} error={form.errors.uraian_pelaksanaan} /></div>
                        <Textarea id={`${id}-kendala`} label="Kendala" maxLength={10000} value={data.kendala} onChange={(e) => form.setData('kendala', e.target.value)} error={form.errors.kendala} />
                        <Textarea id={`${id}-strategi`} label="Strategi tindak lanjut" maxLength={10000} value={data.strategi_tindaklanjut} onChange={(e) => form.setData('strategi_tindaklanjut', e.target.value)} error={form.errors.strategi_tindaklanjut} />
                    </fieldset>
                    {!terkunci && <div className="flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:flex-wrap">
                        <Button type="submit" value="simpan" disabled={blocked || form.processing} isLoading={form.processing}>Simpan realisasi</Button>
                        {can.tandai_terlaksana && <Button type="submit" value="terlaksana" variant="outline" disabled={blocked || form.processing}>Tandai terlaksana</Button>}
                        {failure && <Link href={halamanUbah} className="text-sm text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/25">Muat ulang form realisasi</Link>}
                    </div>}
                </form>
            </CardContent>
        </Card>
    );
}
