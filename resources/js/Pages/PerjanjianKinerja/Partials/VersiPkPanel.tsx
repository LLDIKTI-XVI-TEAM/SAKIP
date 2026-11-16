import { useEffect, useRef } from 'react';
import { FilePlus } from 'lucide-react';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { PerubahanTargetVersi, RenstraPkSummary, RevisiPk, StorageSettings, VersiPk } from '@/types/perjanjian-kinerja';
import { RevisiPkForm } from './RevisiPkForm';

export const ID_KARTU_VERSI = 'versi-pk';
const ID_TOMBOL_REVISI = 'revisi-pk-tombol';

interface VersiPkPanelProps {
    pk: RenstraPkSummary;
    versi: VersiPk[];
    revisi: RevisiPk | null;
    canRevisi: boolean;
    canReadBerkas: boolean;
    storageSettings?: StorageSettings;
    terbuka: boolean;
    onBuka: () => void;
    onTutup: () => void;
}

/** Kartu versi PK: tombol revisi, panel revisi, lalu linimasa versi terbaru di atas. Izin dan selisih target dari server. */
export function VersiPkPanel({ pk, versi, revisi, canRevisi, canReadBerkas, storageSettings, terbuka, onBuka, onTutup }: VersiPkPanelProps) {
    const formatTanggal = useFormatTanggal();
    const formatNilai = useFormatNilai();
    const terbukaSebelumnya = useRef(terbuka);
    // Fokus kembali ke tombol setelah panel tertutup, yaitu setelah tombol aktif lagi.
    useEffect(() => {
        if (terbukaSebelumnya.current && !terbuka) document.getElementById(ID_TOMBOL_REVISI)?.focus();
        terbukaSebelumnya.current = terbuka;
    }, [terbuka]);

    if (versi.length === 0 && !canRevisi) return null;

    const chip = (item: PerubahanTargetVersi) => {
        const nilai = (value: string) => formatNilai(value, item.desimal_tampilan);
        if (item.lama === null) return `${item.kode} masuk PK, target ${nilai(item.baru ?? '')}`;
        if (item.baru === null) return `${item.kode} tidak lagi aktif`;
        return `${item.kode} ${nilai(item.lama)} → ${nilai(item.baru)}`;
    };
    const dokumenAwal = [pk.nomor_pk, pk.tanggal_pk && formatTanggal(pk.tanggal_pk)].filter(Boolean).join(' · ') || `Dokumen PK ${pk.tahun}`;
    // Revisi pertama membentuk versi 1 lebih dulu, sehingga revisinya menjadi versi 2.
    const nomorVersiBaru = revisi ? Math.max(revisi.expected_nomor_versi, 1) + 1 : 0;

    return (
        <Card id={ID_KARTU_VERSI} className="scroll-mt-4">
            <CardHeader className="items-start gap-4">
                <div>
                    <CardTitle>Versi PK</CardTitle>
                    <p className="mt-0.5 text-xs text-muted">{versi.length} versi</p>
                </div>
                {canRevisi && (
                    <Button id={ID_TOMBOL_REVISI} size="sm" className="h-11 gap-1.5 sm:h-9" disabled={terbuka} onClick={onBuka}>
                        <FilePlus className="h-4 w-4" aria-hidden="true" />
                        Revisi PK
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-5">
                {canRevisi && terbuka && revisi && (
                    <RevisiPkForm pkId={pk.id} nomorVersi={nomorVersiBaru} revisi={revisi} storageSettings={storageSettings} onClose={onTutup} />
                )}
                {versi.length === 0 ? <p className="text-sm text-muted">Belum ada revisi.</p> : (
                    <ol className="divide-y divide-border">
                        {[...versi].reverse().map((item, index) => {
                            const awal = item.nomor_versi === 1;
                            // Versi terbaru di urutan pertama; label PK akhir hanya bila sudah ada revisi.
                            const terakhir = index === 0 && versi.length > 1;
                            return (
                                <li key={item.id} className="space-y-1.5 py-4 text-sm first:pt-0 last:pb-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span aria-hidden="true" className={`h-2 w-2 shrink-0 rounded-full ${index === 0 ? 'bg-primary' : 'bg-muted'}`} />
                                        <span className="font-semibold text-ink">Versi {item.nomor_versi}</span>
                                        {awal && <Badge variant="muted">PK awal</Badge>}
                                        {terakhir && <Badge variant="success">PK akhir</Badge>}
                                        {item.periode_mulai && <span className="text-xs text-muted">Berlaku mulai {item.periode_mulai.nama}</span>}
                                    </div>
                                    <div className="space-y-1.5 pl-4">
                                        <p className="break-words text-ink">{awal ? dokumenAwal : `Surat ${item.nomor_surat} · ${formatTanggal(item.tanggal_surat)}`}</p>
                                        {!awal && <>
                                            <p className="text-muted">{['Disetujui', item.disetujui_oleh].filter(Boolean).join(' ')} · {formatTanggal(item.disetujui_pada)}</p>
                                            {item.perubahan_target.length > 0 ? (
                                                <ul className="flex flex-wrap gap-1.5" aria-label={`Perubahan target versi ${item.nomor_versi}`}>
                                                    {item.perubahan_target.map((perubahan) => (
                                                        <li key={perubahan.indikator_id}><Badge variant="primary" className="whitespace-normal">{chip(perubahan)}</Badge></li>
                                                    ))}
                                                </ul>
                                            ) : <p className="text-muted">Target sama dengan versi PK sebelumnya</p>}
                                            {item.alasan && <p className="break-words text-ink">Alasan: {item.alasan}</p>}
                                        </>}
                                        {item.berkas.length > 0 && (
                                            <ul className="space-y-2 pt-1">
                                                {item.berkas.map((berkas) => (
                                                    <BerkasLampiranItem
                                                        key={berkas.id}
                                                        berkas={{
                                                            ...berkas,
                                                            tautan: canReadBerkas ? berkas.tautan : null,
                                                            download_url: canReadBerkas ? `/perjanjian-kinerja/${pk.id}/berkas/${berkas.id}/unduh` : null,
                                                        }}
                                                        meta={berkas.keterangan && <span className="block break-words text-ink">Keterangan: {berkas.keterangan}</span>}
                                                    />
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
