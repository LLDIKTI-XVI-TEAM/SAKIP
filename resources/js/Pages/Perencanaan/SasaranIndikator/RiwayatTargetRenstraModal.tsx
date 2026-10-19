import { useEffect, useState } from 'react';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { classifyRecovery, type RecoveryState } from '@/lib/authRecovery';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';

type Entri = { waktu: string; aktor: string | null; tahun: number; nilai_lama: string | null; nilai_baru: string | null };

const GAGAL = 'Riwayat belum dapat dimuat. Coba lagi.';
const sel = 'p-0 md:px-4 md:py-3';
const labelHp = 'mr-1 text-xs text-muted md:hidden';

/** Riwayat revisi target Renstra: bagian data target (cukup izin baca indikator), bukan Jejak Audit. Dimuat saat dibuka. */
export function RiwayatTargetRenstraModal({ indikator, onClose }: {
    indikator: { id: string; kode: string; desimal_tampilan: number };
    onClose: () => void;
}) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const [data, setData] = useState<{ items: Entri[]; terpotong: boolean } | null>(null);
    const [error, setError] = useState('');
    const [recovery, setRecovery] = useState<RecoveryState | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        const path = `/perencanaan/indikator/${indikator.id}/target-renstra/riwayat`;
        const muat = async () => {
            try {
                const response = await fetch(path, {
                    credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal,
                });
                if (controller.signal.aborted) return;
                if (!response.ok) {
                    setRecovery(classifyRecovery({ status: response.status, data: {}, headers: {} }, { effectiveMethod: 'get', path, mutation: false }));
                    setError(response.status === 403 ? 'Anda tidak memiliki akses membaca indikator ini.'
                        : response.status === 401 ? 'Sesi berakhir. Masuk ulang untuk melihat data.' : GAGAL);
                    return;
                }
                const value: unknown = await response.json();
                if (controller.signal.aborted) return;
                // Bentuk entri dijamin Action; yang dijaga hanya wadahnya agar payload rusak tidak memecah render.
                if (typeof value !== 'object' || value === null || !('items' in value) || !Array.isArray(value.items)
                    || !('terpotong' in value) || typeof value.terpotong !== 'boolean') {
                    setError(GAGAL);
                    return;
                }
                setData({ items: value.items, terpotong: value.terpotong });
            } catch {
                if (!controller.signal.aborted) setError(GAGAL);
            }
        };
        void muat();
        return () => controller.abort();
    }, [indikator.id]);

    const nilai = (value: string | null) => value === null ? '—' : formatNilai(value, indikator.desimal_tampilan);

    return <Modal isOpen onClose={onClose} title="Riwayat target Renstra" description={indikator.kode} size="xl" bodyClassName="p-4 sm:p-6">
        {error ? <div className="space-y-3">
            <AuthRecoveryNotice recovery={recovery} />
            {/* Pemulihan sesi sudah diumumkan sebagai alert; pesan ini cukup terlihat agar tidak diumumkan dua kali. */}
            <p role={recovery ? undefined : 'alert'} className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger">{error}</p>
        </div>
            : !data ? <p role="status" className="rounded-lg bg-soft p-3 text-sm text-muted">Memuat data…</p>
                : data.items.length === 0 ? <p className="text-sm text-muted">Belum ada riwayat.</p>
                    : <div className="space-y-3">
                        {data.terpotong && <p className="text-sm text-muted">Menampilkan 100 perubahan terbaru.</p>}
                        <Table wrapperClassName="overflow-x-visible" className="block md:table">
                            <caption className="sr-only">Riwayat perubahan target Renstra {indikator.kode}, terbaru di atas</caption>
                            <TableHeader className="hidden md:table-header-group">
                                <TableRow>
                                    <TableHead>Waktu</TableHead>
                                    <TableHead>Aktor</TableHead>
                                    <TableHead>Tahun</TableHead>
                                    <TableHead className="text-right">Nilai lama</TableHead>
                                    <TableHead className="text-right">Nilai baru</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {data.items.map((item, index) => <TableRow key={`${item.waktu}-${item.tahun}-${index}`} className="grid grid-cols-2 gap-2 py-3 md:table-row">
                                    <TableCell className={`col-span-2 ${sel}`}>{formatTanggal(item.waktu, { withTime: true })}</TableCell>
                                    <TableCell className={`col-span-2 ${sel}`}>{item.aktor ?? '—'}</TableCell>
                                    <TableCell className={`col-span-2 ${sel}`}><span className={labelHp}>Tahun</span>{item.tahun}</TableCell>
                                    <TableCell className={`${sel} tabular-nums md:text-right`}><span className={labelHp}>Lama</span><span>{nilai(item.nilai_lama)}</span></TableCell>
                                    <TableCell className={`${sel} tabular-nums md:text-right`}><span className={labelHp}>Baru</span><span>{nilai(item.nilai_baru)}</span></TableCell>
                                </TableRow>)}
                            </TableBody>
                        </Table>
                    </div>}
    </Modal>;
}
