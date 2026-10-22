import { useState, type ReactNode } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { TargetModal } from '@/Pages/TargetTahunan/TargetModal';
import { ANJURAN_TARGET_RENSTRA, labelDeviasi } from '@/Pages/TargetTahunan/deviasi';
import type { TargetPkIndikator, TargetPkSasaran } from '@/types/perjanjian-kinerja';

/** Baseline (data pendukung) dan target PK per sasaran. Nilai, izin, dan status baca-saja berasal dari server. */
export function TargetPkTable({ pkId, tahun, sasaran, canUpdate, jadwalStatus }: { pkId: string; tahun: number; sasaran: TargetPkSasaran[]; canUpdate: boolean; jadwalStatus?: string | null }) {
    const formatNilai = useFormatNilai();
    const [editing, setEditing] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const nilai = (value: string | null, indikator: TargetPkIndikator): ReactNode => value === null
        ? <span className="text-muted">Belum diisi</span>
        : formatNilai(value, indikator.desimal_tampilan);

    return <section aria-labelledby="target-pk-heading" className="space-y-4">
        <div>
            <h2 id="target-pk-heading" className="text-base font-semibold text-ink">Baseline dan target PK {tahun}</h2>
            <p className="mt-0.5 text-sm text-muted">Baseline adalah data pendukung, bukan isi dokumen PK. Target Renstra tahun yang sama tampil sebagai pembanding.</p>
            <p className="mt-0.5 text-xs text-muted">{ANJURAN_TARGET_RENSTRA}</p>
        </div>
        {(jadwalStatus === 'aktif' || jadwalStatus === 'ditutup') && <p className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-ink">
            Perubahan target PK tidak mengubah target yang dipakai Rencana Aksi dan pengukuran tahun {tahun}.
        </p>}
        {message && <p role="status" className="rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-ink">{message}</p>}
        {sasaran.length === 0
            ? <p className="text-sm text-muted">Belum ada indikator aktif yang berlaku pada tahun {tahun}.</p>
            : sasaran.map((item) => <Card key={item.id}>
                <div className="border-b border-border p-4 md:p-5">
                    <Badge variant="primary" className="font-mono">{item.kode}</Badge>
                    <h3 className="mt-1 text-base font-semibold leading-relaxed text-ink">{item.deskripsi}</h3>
                </div>
                {/* Di HP setiap indikator menjadi kartu dengan label per sel, tanpa scroll horizontal. */}
                <Table wrapperClassName="overflow-x-visible" className="block md:table">
                    <caption className="sr-only">Target PK {tahun} sasaran {item.kode}</caption>
                    <TableHeader className="hidden md:table-header-group">
                        <TableRow>
                            <TableHead className="w-28">Kode</TableHead>
                            <TableHead>Indikator</TableHead>
                            <TableHead className="text-right">Baseline (data pendukung)</TableHead>
                            <TableHead className="text-right">Target PK {tahun}</TableHead>
                            <TableHead className="text-right">Target Renstra {tahun}</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="block md:table-row-group">
                        {item.indikator.map((indikator) => <TableRow key={indikator.id} className="grid grid-cols-2 gap-3 p-4 md:table-row md:align-top">
                            <TableHead scope="row" className="h-auto p-0 font-mono text-sm normal-case tracking-normal md:px-4 md:py-3.5">{indikator.kode}</TableHead>
                            <TableCell className="p-0 md:px-4 md:py-3.5">
                                <span className="text-ink">{indikator.nama}</span>
                                <span className="block text-xs text-muted">{indikator.satuan}</span>
                            </TableCell>
                            <TableCell className="min-w-0 p-0 tabular-nums md:px-4 md:py-3.5 md:text-right">
                                <span className="mb-1 block text-xs text-muted md:hidden">Baseline (data pendukung)</span>
                                {nilai(indikator.baseline, indikator)}
                            </TableCell>
                            <TableCell className="min-w-0 p-0 tabular-nums md:px-4 md:py-3.5 md:text-right">
                                <span className="mb-1 block text-xs text-muted md:hidden">Target PK {tahun}</span>
                                {nilai(indikator.target_tahunan, indikator)}
                            </TableCell>
                            <TableCell className="min-w-0 p-0 tabular-nums md:px-4 md:py-3.5 md:text-right">
                                <span className="mb-1 block text-xs text-muted md:hidden">Target Renstra {tahun}</span>
                                {indikator.target_renstra === null
                                    ? <span className="text-xs text-muted">Target Renstra belum ada</span>
                                    : formatNilai(indikator.target_renstra, indikator.desimal_tampilan)}
                                {indikator.status_deviasi === 'menyimpang' && <span className="mt-1 block"><Badge variant="warning">{labelDeviasi(indikator.arah)}</Badge></span>}
                            </TableCell>
                            <TableCell className="col-span-2 p-0 md:px-4 md:py-3.5 md:text-right">
                                {canUpdate && !indikator.read_only_reason
                                    ? <Button type="button" size="sm" variant="outline" aria-label={`Ubah target PK ${indikator.kode}`} onClick={() => { setMessage(''); setEditing(indikator.id); }}>Ubah</Button>
                                    : indikator.read_only_reason && <span className="text-xs text-muted">{indikator.read_only_reason}</span>}
                            </TableCell>
                        </TableRow>)}
                    </TableBody>
                </Table>
            </Card>)}
        {editing && <TargetModal pkId={pkId} indikatorId={editing} tahun={tahun} onClose={() => setEditing(null)} onSaved={setMessage} />}
    </section>;
}
