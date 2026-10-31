import { useId, useRef, useState } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { statusKegiatanConfig, type KegiatanFormProps, type StatusTujuan } from '@/types/kegiatan';
import { StatusKegiatanModal, aksiStatusKegiatan } from './StatusKegiatanModal';

interface Props {
    kegiatan: KegiatanFormProps['kegiatan'];
    /** Pilihan status dari server; kosong berarti tanpa aksi status. */
    ubahStatus: StatusTujuan[];
    opsiPeriodeTujuan: KegiatanFormProps['opsiPeriodeTujuan'];
}

/** Kartu status pelaksanaan yang dipakai halaman catat rencana dan catat realisasi kegiatan. */
export function StatusPelaksanaanCard({ kegiatan, ubahStatus, opsiPeriodeTujuan }: Props) {
    const [aksiStatus, setAksiStatus] = useState<StatusTujuan | null>(null);
    const judulStatus = useRef<HTMLHeadingElement>(null);
    const judulStatusId = useId();
    const statusConfig = statusKegiatanConfig[kegiatan.status] ?? { label: kegiatan.status, variant: 'muted' as const };

    return (
        <>
            <Card role="region" aria-labelledby={judulStatusId}>
                <CardContent className="space-y-4 p-4 sm:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <h2 id={judulStatusId} ref={judulStatus} tabIndex={-1} className="text-base font-semibold text-ink focus:outline-none">Status pelaksanaan</h2>
                        <Badge variant={statusConfig.variant}>{statusConfig.label}</Badge>
                    </div>
                    {kegiatan.justifikasi && (
                        <dl className="space-y-2 rounded-lg border border-border bg-soft/50 p-3 text-sm">
                            {kegiatan.periode_tujuan && <div><dt className="text-xs text-muted">Periode tujuan</dt><dd className="font-medium text-ink">{kegiatan.periode_tujuan} {kegiatan.tahun}</dd></div>}
                            <div><dt className="text-xs text-muted">Justifikasi</dt><dd className="whitespace-pre-line break-words font-medium text-ink">{kegiatan.justifikasi}</dd></div>
                        </dl>
                    )}
                    {ubahStatus.length > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            {ubahStatus.map((status) => {
                                const { label, icon: Icon, danger } = aksiStatusKegiatan[status];
                                // key per status: tombol lama dilepas setelah transisi sehingga fokus jatuh ke judul kartu.
                                return <Button key={status} type="button" variant="outline" className={danger ? 'border-danger/40 text-danger hover:bg-danger/10' : undefined} onClick={() => setAksiStatus(status)}>
                                    <Icon className="h-4 w-4" aria-hidden="true" />
                                    {label}
                                </Button>;
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
            {aksiStatus && <StatusKegiatanModal
                key={`${kegiatan.id}:${aksiStatus}`}
                kegiatan={{ ...kegiatan, periode: kegiatan.periode_nama }}
                status={aksiStatus}
                opsiPeriodeTujuan={opsiPeriodeTujuan}
                fokusCadangan={judulStatus}
                onClose={() => setAksiStatus(null)}
            />}
        </>
    );
}
