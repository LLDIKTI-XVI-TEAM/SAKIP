import { ChevronDown } from 'lucide-react';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { RenstraOption } from '@/types/sasaran-indikator';
import { useMuatDaftar } from './useMuatDaftar';

type Revisi = {
    nomor: number;
    waktu: string;
    aktor: string | null;
    justifikasi: string;
    perubahan: { indikator: { kode: string; nama: string; desimal_tampilan: number }; tahun: number; nilai_lama: string | null; nilai_baru: string | null }[];
};

const sel = 'p-0 md:px-4 md:py-3';
const labelHp = 'mr-1 text-xs text-muted md:hidden';

/** Histori revisi bernomor per Renstra (Q71 §71.3); seperti riwayat per indikator, cukup izin baca indikator. */
export function HistoriRevisiTargetRenstraModal({ renstra, onClose }: { renstra: RenstraOption; onClose: () => void }) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const { data, error, recovery } = useMuatDaftar<Revisi>(`/perencanaan/renstra/${renstra.id}/revisi-target/histori`, 'Histori revisi belum dapat dimuat. Coba lagi.');

    return <Modal isOpen onClose={onClose} title="Histori revisi target Renstra" description={renstra.nama} size="xl" bodyClassName="p-4 sm:p-6">
        {error ? <div className="space-y-3">
            <AuthRecoveryNotice recovery={recovery} />
            <p role={recovery ? undefined : 'alert'} className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger">{error}</p>
        </div>
            : !data ? <p role="status" className="rounded-lg bg-soft p-3 text-sm text-muted">Memuat data…</p>
                : data.items.length === 0 ? <p className="text-sm text-muted">Belum ada revisi.</p>
                    : <div className="space-y-4">
                        {data.terpotong && <p className="text-sm text-muted">Menampilkan 100 revisi terbaru.</p>}
                        <ol className="space-y-4">
                            {/* Tiap revisi terlipat agar histori yang panjang tetap ringkas; justifikasi dan tabel muncul saat dibuka. */}
                            {data.items.map((revisi) => <li key={revisi.nomor} className="rounded-lg border border-border">
                                <details className="group">
                                    <summary className="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 rounded-lg p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25 [&::-webkit-details-marker]:hidden">
                                        <h3 className="text-sm font-semibold text-ink">Revisi ke-{revisi.nomor}</h3>
                                        <span className="flex items-center gap-2 text-xs text-muted">
                                            {formatTanggal(revisi.waktu, { withTime: true })} · {revisi.aktor ?? '—'} · {revisi.perubahan.length} perubahan
                                            <ChevronDown aria-hidden className="size-4 shrink-0 transition-transform group-open:rotate-180" />
                                        </span>
                                    </summary>
                                    <div className="border-t border-border p-4">
                                        <p className="whitespace-pre-line break-words text-sm text-ink">{revisi.justifikasi}</p>
                                        <Table wrapperClassName="mt-3 overflow-x-visible" className="block md:table">
                                            <caption className="sr-only">Perubahan pada revisi ke-{revisi.nomor}</caption>
                                            <TableHeader className="hidden md:table-header-group">
                                                <TableRow>
                                                    <TableHead>Indikator</TableHead>
                                                    <TableHead>Tahun</TableHead>
                                                    <TableHead className="text-right">Nilai lama</TableHead>
                                                    <TableHead className="text-right">Nilai baru</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody className="block md:table-row-group">
                                                {revisi.perubahan.map((p) => {
                                                    const nilai = (value: string | null) => value === null ? '—' : formatNilai(value, p.indikator.desimal_tampilan);
                                                    return <TableRow key={`${p.indikator.kode}-${p.tahun}`} className="grid grid-cols-2 gap-2 py-3 md:table-row">
                                                        <TableCell className={`col-span-2 ${sel}`}><span className="font-mono text-xs">{p.indikator.kode}</span> <span className="text-ink">{p.indikator.nama}</span></TableCell>
                                                        <TableCell className={`col-span-2 ${sel}`}><span className={labelHp}>Tahun</span>{p.tahun}</TableCell>
                                                        <TableCell className={`${sel} tabular-nums md:text-right`}><span className={labelHp}>Lama</span><span>{nilai(p.nilai_lama)}</span></TableCell>
                                                        <TableCell className={`${sel} tabular-nums md:text-right`}><span className={labelHp}>Baru</span><span>{nilai(p.nilai_baru)}</span></TableCell>
                                                    </TableRow>;
                                                })}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </details>
                            </li>)}
                        </ol>
                    </div>}
    </Modal>;
}
