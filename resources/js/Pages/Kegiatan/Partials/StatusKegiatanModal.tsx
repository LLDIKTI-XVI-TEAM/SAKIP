import { useEffect, useId, useRef, useState, type FormEvent, type RefObject } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Ban, CalendarClock, CircleX, type LucideIcon } from 'lucide-react';
import { clsx } from 'clsx';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { KegiatanOption, StatusTujuan } from '@/types/kegiatan';

/** Label dan ikon aksi status, dipakai menu baris daftar dan kartu status form. */
export const aksiStatusKegiatan: Record<StatusTujuan, { label: string; icon: LucideIcon; danger?: boolean }> = {
    ditunda: { label: 'Tunda', icon: CalendarClock },
    tidak_terlaksana: { label: 'Tidak terlaksana', icon: CircleX },
    batal: { label: 'Batalkan', icon: Ban, danger: true },
};

// Status final (Q69 §69.1) memerlukan langkah konfirmasi (Q69 §69.7 butir 3); `tidak_terlaksana` masih dapat diubah sehingga dikirim langsung.
const TEKS: Record<StatusTujuan, { judul: string; konfirmasi?: { judul: string; final: string; kirim: string } }> = {
    ditunda: { judul: 'Tunda kegiatan', konfirmasi: { judul: 'Tunda kegiatan?', final: 'Status Ditunda final dan tidak dapat diubah.', kirim: 'Ya, tunda' } },
    tidak_terlaksana: { judul: 'Tandai tidak terlaksana' },
    batal: { judul: 'Batalkan kegiatan', konfirmasi: { judul: 'Batalkan kegiatan?', final: 'Status Batal final dan tidak dapat diubah.', kirim: 'Ya, batalkan' } },
};

interface StatusKegiatanModalProps {
    kegiatan: { id: string; nama: string; tahun: number; periode: string; version_token: string };
    status: StatusTujuan;
    opsiPeriodeTujuan: KegiatanOption[];
    /** Tujuan fokus setelah sukses bila pemicu sudah hilang dari DOM atau tidak pernah difokus. */
    fokusCadangan: RefObject<HTMLElement | null>;
    onClose: () => void;
}

/**
 * Satu modal untuk tiga transisi status pelaksanaan. Caller memasangnya hanya saat terbuka, dengan `key` per kegiatan
 * dan status, sehingga isian awal selalu memakai status dan token terbaru tanpa `reset()`: setelah sukses, defaults
 * `useForm` berganti ke data terakhir yang dikirim.
 */
export function StatusKegiatanModal({ kegiatan, status, opsiPeriodeTujuan, fokusCadangan, onClose }: StatusKegiatanModalProps) {
    const teks = TEKS[status];
    const form = useForm({ status, justifikasi: '', periode_tujuan_id: '', expected_state: kegiatan.version_token });
    const [konfirmasi, setKonfirmasi] = useState(false);
    const [fokusKe, setFokusKe] = useState<'peringatan' | 'justifikasi' | 'periode' | null>(null);
    // Dicatat saat pemasangan; di Safari klik tidak memfokuskan tombol sehingga pemicu dapat berupa body.
    const [pemicu] = useState(() => document.activeElement);
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const blocked = Boolean(failure || recovery.recovery);
    const { url } = usePage();
    const formId = useId();
    const peringatan = useRef<HTMLParagraphElement>(null);
    const path = `/kegiatan/${kegiatan.id}/status`;
    const periodeTujuan = opsiPeriodeTujuan.find((opsi) => opsi.id === form.data.periode_tujuan_id)?.nama;
    const galatUmum = form.errors.status || form.errors.expected_state;

    // Fokus antarlangkah dipasang setelah commit, saat elemen langkah tujuan sudah ada; nilai awal null tidak
    // mengganggu autofocus Modal saat pemasangan.
    useEffect(() => {
        if (!fokusKe) return;
        (fokusKe === 'peringatan' ? peringatan.current : document.getElementById(`${formId}-${fokusKe}`))?.focus();
        setFokusKe(null);
    }, [fokusKe, formId]);

    // Penutupan ditahan selama request berjalan atau hasilnya belum terkonfirmasi, agar draf tidak hilang.
    const tutup = () => {
        if (form.processing || blocked) return;
        onClose();
    };
    const kembali = () => {
        setKonfirmasi(false);
        setFokusKe('justifikasi');
    };
    const kirim = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || blocked) return;
        if (teks.konfirmasi && !konfirmasi) {
            setKonfirmasi(true);
            setFokusKe('peringatan');
            return;
        }
        form.post(path, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                // Pemulihan fokus bawaan Modal tidak berguna bila pemicu sudah dilepas (tombol status lama, baris keluar filter).
                requestAnimationFrame(() => {
                    if (!pemicu || pemicu === document.body || !pemicu.isConnected) fokusCadangan.current?.focus();
                });
            },
            onError: (errors) => {
                setKonfirmasi(false);
                setFokusKe(errors.periode_tujuan_id ? 'periode' : 'justifikasi');
            },
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) {
                    setFailure(response.status === 403 ? 'Hasil perubahan status belum terkonfirmasi karena akses ditolak. Periksa izin dan data terbaru sebelum mencoba kembali.' : 'Hasil perubahan status belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
            onNetworkError: () => { setFailure('Koneksi terputus. Hasil perubahan status belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'); return false; },
            onCancel: () => setFailure('Permintaan dibatalkan di browser. Hasil perubahan status belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'),
        });
    };

    return (
        <Modal
            isOpen
            onClose={tutup}
            title={konfirmasi && teks.konfirmasi ? teks.konfirmasi.judul : teks.judul}
            description={`${kegiatan.nama} · ${kegiatan.periode} ${kegiatan.tahun}`}
            size="lg"
            showCloseButton={!form.processing && !blocked}
            footer={<>
                {/* Kedua tombol berganti fungsi antarlangkah pada node yang sama, sehingga klik kedua dari klik ganda diabaikan:
                    "Kembali" tidak berlanjut menjadi "Tutup" yang membuang draf, dan "Lanjutkan" tidak berlanjut mengirim status final sebelum konfirmasi terbaca. */}
                <Button type="button" variant="outline" onClick={konfirmasi ? kembali : (event) => { if (event.detail <= 1) tutup(); }} disabled={form.processing || blocked}>
                    {konfirmasi ? 'Kembali' : 'Tutup'}
                </Button>
                <Button type="submit" form={formId} variant={status === 'batal' ? 'danger' : 'primary'} disabled={blocked} isLoading={form.processing}
                    onClick={(event) => { if (konfirmasi && event.detail > 1) event.preventDefault(); }}>
                    {konfirmasi && teks.konfirmasi ? teks.konfirmasi.kirim : teks.konfirmasi ? 'Lanjutkan' : 'Simpan'}
                </Button>
            </>}
        >
            <form id={formId} onSubmit={kirim} className="space-y-4">
                <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                {(failure || galatUmum) && (
                    <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark">
                        {failure || galatUmum}
                        {failure && <Link href={url} className="mt-2 block font-medium underline">Muat ulang data kegiatan</Link>}
                    </div>
                )}
                {konfirmasi && teks.konfirmasi ? <>
                    <div className={clsx('flex gap-3 rounded-lg border p-3 text-sm', status === 'batal' ? 'border-danger/30 bg-danger/5 text-danger-dark' : 'border-warning/30 bg-warning/10 text-warning-dark')}>
                        <AlertTriangle className="h-5 w-5 shrink-0" aria-hidden="true" />
                        <div>
                            <p ref={peringatan} tabIndex={-1} className="font-semibold focus:outline-none">{teks.konfirmasi.final}</p>
                            {status === 'ditunda' && <p className="mt-0.5">Kegiatan lanjutan dibuat di {periodeTujuan}.</p>}
                        </div>
                    </div>
                    <dl className="space-y-2 rounded-lg border border-border bg-soft/50 p-3 text-sm">
                        {status === 'ditunda' && <div><dt className="text-xs text-muted">Periode tujuan</dt><dd className="font-medium text-ink">{periodeTujuan} {kegiatan.tahun}</dd></div>}
                        <div><dt className="text-xs text-muted">Justifikasi</dt><dd className="whitespace-pre-line break-words font-medium text-ink">{form.data.justifikasi}</dd></div>
                    </dl>
                </> : <fieldset disabled={form.processing || blocked} className="space-y-4">
                    {status === 'ditunda' && (
                        <Select id={`${formId}-periode`} label="Periode tujuan" required value={form.data.periode_tujuan_id}
                            onChange={(event) => form.setData('periode_tujuan_id', event.target.value)} error={form.errors.periode_tujuan_id}
                            options={[{ value: '', label: 'Pilih periode tujuan' }, ...opsiPeriodeTujuan.map((opsi) => ({ value: opsi.id, label: opsi.nama }))]} />
                    )}
                    <Textarea id={`${formId}-justifikasi`} label="Justifikasi" required maxLength={10000} rows={4}
                        placeholder="Jelaskan alasan perubahan status kegiatan." value={form.data.justifikasi}
                        onChange={(event) => form.setData('justifikasi', event.target.value)} error={form.errors.justifikasi} />
                </fieldset>}
            </form>
        </Modal>
    );
}
