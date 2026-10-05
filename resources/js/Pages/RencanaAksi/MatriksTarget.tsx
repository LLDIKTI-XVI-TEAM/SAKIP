import { Badge } from '@/Components/Badge';
import { Input } from '@/Components/Input';
import type { RencanaAksiKomponen, RencanaAksiPeriode } from './types';
import { rencanaAksiStatusPerhitungan } from './types';

interface MatriksTargetProps {
    tipePerhitungan: string;
    komponen: RencanaAksiKomponen[];
    periode: RencanaAksiPeriode[];
    desimalTampilan: number;
    satuan: string;
    /** True bila seluruh matriks hanya-baca (can.update false atau sedang menyimpan). */
    disabled: boolean;
    /** F2: penentu per-periode boleh disunting (koreksi parsial). Default semua efektif boleh. */
    dapatDisunting?: (periodeId: string) => boolean;
    /** Nilai input mentah per sel (`""` = belum diisi/null, `"0"` = nol eksplisit). */
    values: Record<string, string>;
    onValueChange: (periodeId: string, komponenId: string | null, value: string) => void;
    /** Pesan galat validasi per sel bila ada. */
    galatSel: (periodeId: string, komponenId: string | null) => string | undefined;
    formatNilai: (nilai: string | number, desimal: number) => string;
}

function kunci(periodeId: string, komponenId: string | null): string {
    return `${periodeId}::${komponenId ?? 'manual'}`;
}

function labelSkor(nilai: string | null, status: string, formatNilai: (n: string | number, d: number) => string, desimal: number, satuan: string): string {
    if (nilai !== null) {
        return `${formatNilai(nilai, desimal)} ${satuan}`;
    }
    return rencanaAksiStatusPerhitungan[status] ?? status;
}

/**
 * Matriks periode × komponen untuk penyusunan target rencana aksi.
 *
 * Skor turunan selalu berasal dari payload server (`periode.skor`)
 * dan tidak pernah dihitung ulang di React. Perubahan input hanya
 * mengubah `values` formulir; kolom skor tetap menampilkan hasil
 * tersimpan sampai server menyimpan ulang. F2: baris di luar lingkup
 * koreksi dinonaktifkan dan tidak dikirim (validasi fail-closed tetap
 * di backend); F5: skor reaktif ditampilkan komponen pratinjau terpisah.
 */
export default function MatriksTarget({
    tipePerhitungan,
    komponen,
    periode,
    desimalTampilan,
    satuan,
    disabled,
    dapatDisunting,
    values,
    onValueChange,
    galatSel,
    formatNilai,
}: MatriksTargetProps) {
    const manual = tipePerhitungan === 'manual';
    const kodeKomponen = (id: string | null): string => {
        if (id === null) {
            return 'Target';
        }
        return komponen.find((item) => item.komponen_id === id)?.kode ?? 'Komponen';
    };

    return (
        <div className="overflow-x-auto rounded-xl border border-border bg-surface">
            <table className="w-full min-w-[680px] text-left text-sm">
                <caption className="sr-only">Matriks target per periode per komponen; skor turunan dari server</caption>
                <thead>
                    <tr className="border-b border-border bg-soft text-xs font-semibold uppercase tracking-wide text-muted">
                        <th scope="col" className="min-w-[140px] px-4 py-3">
                            Periode
                        </th>
                        {manual ? (
                            <th scope="col" className="min-w-[180px] px-4 py-3">
                                Target ({satuan})
                            </th>
                        ) : (
                            komponen.map((item) => (
                                <th key={item.komponen_id} scope="col" className="min-w-[160px] px-4 py-3">
                                    <span className="block text-ink normal-case">{item.kode} · {item.label}</span>
                                    <span className="mt-0.5 block text-[11px] font-normal normal-case text-muted">
                                        {item.peran}{item.bobot === '' ? '' : ` · Bobot ${item.bobot}`}
                                    </span>
                                </th>
                            ))
                        )}
                        <th scope="col" className="min-w-[170px] px-4 py-3">
                            Skor tersimpan (server)
                        </th>
                        <th scope="col" className="min-w-[170px] px-4 py-3">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {periode.map((baris) => {
                        if (!baris.efektif) {
                            return (
                                <tr key={baris.id} className="bg-soft/40">
                                    <th scope="row" className="px-4 py-3 font-medium text-ink">
                                        {baris.nama ?? 'Periode'}
                                        <span className="mt-1 block text-xs font-normal text-muted">Urutan {baris.urutan}</span>
                                    </th>
                                    <td colSpan={manual ? 1 : Math.max(komponen.length, 1)} className="px-4 py-3 text-sm text-muted">
                                        <Badge variant="muted" size="sm">Tidak berlaku</Badge>
                                        <p className="mt-1 text-xs">Periode di luar cakupan indikator ini; tidak dapat diisi.</p>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted">—</td>
                                    <td className="px-4 py-3 text-sm text-muted">Tidak berlaku</td>
                                </tr>
                            );
                        }

                        const selList = manual
                            ? [{ komponen_id: null as string | null, kode: null as string | null, label: null as string | null }]
                            : komponen.map((item) => ({ komponen_id: item.komponen_id as string | null, kode: item.kode, label: item.label }));
                        // F2: periode efektif di luar lingkup koreksi dikunci di UI
                        // (tak dikirim saat simpan); backend tetap menolak fail-closed.
                        const terkunciKoreksi = dapatDisunting ? !dapatDisunting(baris.id) : false;
                        const selDisabled = disabled || terkunciKoreksi;

                        return (
                            <tr key={baris.id} className="align-top">
                                <th scope="row" className="px-4 py-3 font-medium text-ink">
                                    {baris.nama ?? 'Periode'}
                                    <span className="mt-1 block text-xs font-normal text-muted">Urutan {baris.urutan}</span>
                                </th>
                                {selList.map((sel) => {
                                    const key = kunci(baris.id, sel.komponen_id);
                                    const galat = galatSel(baris.id, sel.komponen_id);
                                    const inputId = `target-${baris.id}-${sel.komponen_id ?? 'manual'}`;
                                    const label = manual ? `Target ${baris.nama ?? ''} (${satuan})` : `${sel.kode} · ${sel.label} · ${baris.nama ?? ''}`;
                                    return (
                                        <td key={key} className="px-4 py-3">
                                            <Input
                                                id={inputId}
                                                name={inputId}
                                                label={label}
                                                labelClassName="sr-only"
                                                type="number"
                                                step="any"
                                                value={values[key] ?? ''}
                                                placeholder="Belum diisi"
                                                disabled={selDisabled}
                                                onChange={(event) => onValueChange(baris.id, sel.komponen_id, event.target.value)}
                                                error={galat}
                                                aria-invalid={galat ? true : undefined}
                                                helperText={values[key] === '0' || values[key] === '0.00' ? 'Nol eksplisit (berbeda dari kosong).' : undefined}
                                            />
                                        </td>
                                    );
                                })}
                                <td className="px-4 py-3">
                                    <p className="font-mono text-sm font-semibold text-ink" data-testid={`skor-${baris.id}`}>
                                        {labelSkor(baris.skor.nilai, baris.skor.status_perhitungan, formatNilai, desimalTampilan, satuan)}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted">Hasil server; bukan hitungan klien.</p>
                                </td>
                                <td className="px-4 py-3">
                                    {baris.skor.nilai === null && baris.skor.status_perhitungan === 'tidak_dapat_dihitung' ? (
                                        <Badge variant="muted" size="sm">Tidak dapat dihitung</Badge>
                                    ) : baris.skor.nilai === null ? (
                                        <Badge variant="muted" size="sm">Belum diisi</Badge>
                                    ) : (
                                        <Badge variant="success" size="sm">Terhitung</Badge>
                                    )}
                                    {terkunciKoreksi && (
                                        <p className="mt-2 text-xs font-medium text-muted" data-testid={`koreksi-terkunci-${baris.id}`}>
                                            Di luar lingkup koreksi; tidak disunting dan tidak dikirim.
                                        </p>
                                    )}
                                    {baris.peringatan_turun && (
                                        <p role="note" className="mt-2 text-xs font-medium text-warning-dark" data-testid={`peringatan-${baris.id}`}>
                                            Nilai turun dari periode sebelumnya
                                            {baris.komponen_turun.length > 0
                                                ? `: ${baris.komponen_turun.map((id) => (id === null ? 'target' : kodeKomponen(id))).join(', ')}`
                                                : ''}
                                            . Peringatan tidak memblokir penyimpanan.
                                        </p>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
