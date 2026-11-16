import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Button } from '@/Components/Button';

export default function PindahUnit({ id, versi, tujuan = 'unit indikator' }: { id: string; versi: number; tujuan?: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ expected_versi: versi, alasan: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const error = Object.values(errors).find(Boolean);

    return <>
        <Button type="button" variant="outline" onClick={() => setOpen(true)}>Pindahkan unit Rencana Aksi</Button>
        <AuditReasonModal
            open={open}
            title="Pindahkan unit Rencana Aksi"
            description={`Unit tujuan: ${tujuan}. Kegiatan terkait yang memenuhi syarat ikut berpindah.`}
            notice={<p className="mb-3 text-sm text-warning-dark">Setelah pemindahan, lakukan koreksi snapshot. Penyimpanan dan pengajuan tidak tersedia sampai konteks unit selaras.</p>}
            reason={form.data.alasan}
            error={error}
            busy={form.processing}
            confirmLabel="Pindahkan unit"
            onReasonChange={(value) => form.setData('alasan', value)}
            onClose={() => setOpen(false)}
            onConfirm={() => form.post(`/rencana-aksi/${id}/pindah-unit`, { onSuccess: () => setOpen(false) })}
        />
    </>;
}
