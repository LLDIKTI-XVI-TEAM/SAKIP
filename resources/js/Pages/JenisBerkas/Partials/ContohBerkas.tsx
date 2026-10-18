import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Download, Trash2, Upload } from 'lucide-react';

export interface ContohBerkasItem {
    id: string;
    nama_asli: string;
    ukuran_bytes: number | null;
}

interface ContohBerkasProps {
    jenisBerkasId: string;
    nama: string;
    contoh: ContohBerkasItem[];
    canUpdate: boolean;
    onHapus: (contoh: ContohBerkasItem) => void;
}

/** Contoh berkas persyaratan perencanaan kegiatan (Q42 §42.3): unduh, unggah, ganti, hapus. */
export function ContohBerkas({ jenisBerkasId, nama, contoh, canUpdate, onHapus }: ContohBerkasProps) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const unggah = (file: File | undefined, menggantikanId?: string) => {
        if (!file) return;
        setBusy(true);
        setError(null);
        router.post(`/jenis-berkas/${jenisBerkasId}/contoh`, { file, ...(menggantikanId ? { menggantikan_id: menggantikanId } : {}) }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errs) => setError(errs.file ?? errs.menggantikan_id ?? 'Contoh berkas gagal diunggah.'),
            onFinish: () => setBusy(false),
        });
    };

    const pilihFile = (id: string, label: string, keterangan: string, menggantikanId?: string) => (
        <label htmlFor={id} className={`inline-flex cursor-pointer items-center gap-1 font-semibold text-primary hover:underline ${busy ? 'pointer-events-none opacity-60' : ''}`}>
            <Upload className="h-3.5 w-3.5" aria-hidden="true" />
            {label}
            <span className="sr-only"> {keterangan}</span>
            <input id={id} type="file" className="sr-only" disabled={busy}
                onChange={(e) => { unggah(e.target.files?.[0], menggantikanId); e.target.value = ''; }} />
        </label>
    );

    if (contoh.length === 0 && !canUpdate) return null;

    return (
        <div className="mt-2 space-y-1 text-xs">
            <p className="text-muted">Contoh berkas</p>
            {contoh.map((item) => (
                <div key={item.id} className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <a href={`/jenis-berkas/${jenisBerkasId}/contoh/${item.id}`} className="inline-flex min-w-0 items-center gap-1 break-all text-primary hover:underline">
                        <Download className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                        {item.nama_asli}
                    </a>
                    {canUpdate && pilihFile(`contoh-ganti-${item.id}`, 'Ganti', item.nama_asli, item.id)}
                    {canUpdate && (
                        <button type="button" onClick={() => onHapus(item)} disabled={busy}
                            className="inline-flex items-center gap-1 font-semibold text-danger hover:underline disabled:opacity-60"
                            aria-label={`Hapus contoh ${item.nama_asli}`}>
                            <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                            Hapus
                        </button>
                    )}
                </div>
            ))}
            {canUpdate && pilihFile(`contoh-unggah-${jenisBerkasId}`, 'Unggah contoh', nama)}
            {error && <p role="alert" className="font-medium text-danger">{error}</p>}
        </div>
    );
}
