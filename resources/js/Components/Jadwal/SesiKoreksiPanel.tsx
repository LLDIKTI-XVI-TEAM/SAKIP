import { useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { LingkupKoreksi, SesiKoreksi } from '@/types/periode-jadwal';

interface Props {
    jadwalId: string;
    revisi: number;
    canReopen: boolean;
    koreksi: SesiKoreksi | null;
    indikator: { id: string; nama: string }[];
    periode: { periode_id: string; nama: string }[];
}

const waktu = (value: string) => new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Makassar',
}).format(new Date(value));

export function SesiKoreksiPanel({ jadwalId, revisi, canReopen, koreksi, indikator, periode }: Props) {
    const [open, setOpen] = useState(false);
    const [unknown, setUnknown] = useState(false);
    const recovery = useAuthRecovery();
    const submitting = useRef(false);
    const form = useForm<{ alasan: string; koreksi_mulai: string; koreksi_sampai: string; lingkup_koreksi: LingkupKoreksi }>({
        alasan: '', koreksi_mulai: '', koreksi_sampai: '', lingkup_koreksi: { jenis_objek: [], indikator_ids: [], periode_ids: [] },
    });
    const errors = form.errors as Record<string, string | undefined>;
    const disabled = form.processing || unknown || Boolean(recovery.recovery);
    const lengkap = form.data.koreksi_mulai !== '' && form.data.koreksi_sampai > form.data.koreksi_mulai
        && Object.values(form.data.lingkup_koreksi).every((ids) => ids.length > 0);
    const namaIndikator = koreksi?.lingkup.indikator_ids.map((id) => indikator.find((item) => item.id === id)?.nama) ?? [];
    const path = `/jadwal/${jadwalId}/buka-kembali`;
    const markUnknown = () => { setOpen(false); setUnknown(true); };
    const confirm = () => {
        if (disabled || submitting.current || !lengkap || form.data.alasan.trim() === '') return;
        submitting.current = true;
        form.transform((data) => ({ ...data, expected_revisi: revisi }));
        form.post(path, {
            preserveScroll: true,
            onSuccess: (page) => { if (page.flash.success) setOpen(false); else markUnknown(); },
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) markUnknown();
                else setOpen(false);
                return false;
            },
            onNetworkError: () => { markUnknown(); return false; },
            onFinish: () => { submitting.current = false; },
        });
    };

    if (!canReopen && !koreksi) return null;
    return <Card>
        <CardHeader><h2 className="font-semibold">Sesi koreksi perencanaan</h2></CardHeader>
        <CardContent className="space-y-4 p-4 sm:p-5">
            <p className="text-sm text-muted">Koreksi dibatasi pada waktu, jenis data, indikator, dan periode yang dipilih. PIC tidak otomatis mendapat akses. Kegiatan, klaim, dan lampiran kegiatan tetap terkunci.</p>
            {koreksi && <div className="space-y-1 rounded-lg border border-border bg-soft p-3 text-sm">
                <p className="font-semibold">Sesi koreksi {koreksi.status}</p>
                <p>{waktu(koreksi.mulai)} – {waktu(koreksi.sampai)} WITA</p>
                <p>Jenis data: {koreksi.lingkup.jenis_objek.map((jenis) => jenis === 'rencana_aksi' ? 'Rencana Aksi' : 'Pengukuran').join(', ')}.</p>
                <p>Indikator: {namaIndikator.every(Boolean) ? namaIndikator.join('; ') : `${namaIndikator.length} indikator`}.</p>
                <p>Periode: {koreksi.lingkup.periode_ids.map((id) => periode.find((item) => item.periode_id === id)?.nama ?? id).join(', ')}.</p>
                {koreksi.status === 'berakhir' && <p>Waktu koreksi selesai. Tutup jadwal sebelum membuka sesi berikutnya.</p>}
            </div>}
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {unknown && <div role="alert" className="space-y-1 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                <p>Hasil pembukaan belum terkonfirmasi. Periksa jadwal terbaru sebelum mencoba lagi.</p>
                <Link href={`/jadwal/${jadwalId}`} className="rounded text-primary underline focus:ring-2 focus:ring-primary/30">Periksa jadwal terbaru</Link>
            </div>}
            {canReopen && <>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Input label="Awal sesi (WITA)" type="datetime-local" required value={form.data.koreksi_mulai} error={errors.koreksi_mulai} disabled={disabled} onChange={(e) => form.setData('koreksi_mulai', e.target.value)} />
                    <Input label="Akhir sesi (WITA)" type="datetime-local" required value={form.data.koreksi_sampai} error={errors.koreksi_sampai} disabled={disabled} onChange={(e) => form.setData('koreksi_sampai', e.target.value)} />
                </div>
                {([
                    ['jenis_objek', 'Jenis data', [{ id: 'rencana_aksi', nama: 'Rencana Aksi' }, { id: 'pengukuran', nama: 'Pengukuran' }]],
                    ['indikator_ids', 'Indikator', indikator],
                    ['periode_ids', 'Periode', periode.map((item) => ({ id: item.periode_id, nama: item.nama }))],
                ] as const).map(([key, label, options]) => <fieldset key={key} disabled={disabled} className="rounded-lg border border-border p-3">
                    <legend className="px-1 text-sm font-semibold">{label} <span className="text-danger" aria-hidden="true">*</span></legend>
                    <div className="grid max-h-56 gap-2 overflow-y-auto sm:grid-cols-2">
                        {options.map((option) => <label key={option.id} className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="h-4 w-4 shrink-0 accent-primary" checked={form.data.lingkup_koreksi[key].includes(option.id)} onChange={() => form.setData('lingkup_koreksi', {
                                ...form.data.lingkup_koreksi,
                                [key]: options.filter((item) => item.id === option.id ? !form.data.lingkup_koreksi[key].includes(item.id) : form.data.lingkup_koreksi[key].includes(item.id)).map((item) => item.id),
                            })} />
                            {option.nama}
                        </label>)}
                    </div>
                    {options.length === 0 && <p className="text-sm text-muted">{key === 'indikator_ids'
                        ? 'Tidak ada indikator yang dapat Anda baca. Periksa izin baca atau ketersediaan indikator pada jadwal.'
                        : `Belum ada pilihan ${label.toLowerCase()} pada jadwal ini.`}</p>}
                </fieldset>)}
                <div className="flex justify-end"><Button type="button" disabled={disabled || !lengkap} onClick={() => setOpen(true)}>Buka kembali untuk koreksi</Button></div>
            </>}
        </CardContent>
        <AuditReasonModal open={open} title="Buka sesi koreksi" description="Hanya data yang dipilih dapat dikoreksi oleh Perencanaan selama sesi berlangsung. Tanggal penutupan asli dan data acuan tetap."
            reason={form.data.alasan} error={errors.alasan} busy={form.processing} submitDisabled={!lengkap || form.data.alasan.trim() === ''}
            notice={Object.entries(errors).filter(([key]) => key !== 'alasan').map(([key, message]) => <p key={key} role="alert" className="mb-2 text-sm text-danger">{message}</p>)}
            confirmLabel="Buka sesi koreksi" onReasonChange={(value) => form.setData('alasan', value)} onClose={() => !form.processing && setOpen(false)} onConfirm={confirm} />
    </Card>;
}
