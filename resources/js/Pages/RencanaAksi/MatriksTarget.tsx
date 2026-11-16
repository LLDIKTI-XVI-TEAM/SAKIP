import { Badge } from '@/Components/Badge';
import { Input } from '@/Components/Input';
import { RentangPeriode } from '@/Components/Periode/RentangPeriode';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { peranKomponenLabel } from '@/lib/indikator';
import type { RencanaAksiKomponen, RencanaAksiPeriode } from './types';
import { kunciSel, rencanaAksiStatusPerhitungan, tanpaNolBelakang } from './types';

interface MatriksTargetProps {
    tipePerhitungan: string;
    komponen: RencanaAksiKomponen[];
    periode: RencanaAksiPeriode[];
    /** Tahun Rencana Aksi untuk rentang periode. */
    tahun: number;
    desimalTampilan: number;
    satuan: string;
    /** True bila seluruh matriks hanya-baca (can.update false atau sedang menyimpan). */
    disabled: boolean;
    /** Penentu per-periode boleh disunting (koreksi parsial). Default semua efektif boleh. */
    dapatDisunting?: (periodeId: string) => boolean;
    /** Id periode yang terkunci bagi aktor (selalu kosong bagi Perencanaan). */
    periodeTerkunci: ReadonlySet<string>;
    formatTanggal: (tanggal: string) => string;
    /** Nilai input mentah per sel (`""` = belum diisi/null, `"0"` = nol eksplisit). */
    values: Record<string, string>;
    onValueChange: (periodeId: string, komponenId: string | null, value: string) => void;
    /** Pesan galat validasi per sel bila ada. */
    galatSel: (periodeId: string, komponenId: string | null) => string | undefined;
    formatNilai: (nilai: string | number, desimal: number) => string;
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
 * tersimpan sampai server menyimpan ulang. Baris yang dikunci
 * (di luar lingkup koreksi atau periode terkunci) dinonaktifkan dan
 * tidak dikirim (validasi fail-closed tetap di backend); skor reaktif
 * ditampilkan komponen pratinjau terpisah.
 */
export default function MatriksTarget({
    tipePerhitungan,
    komponen,
    periode,
    tahun,
    desimalTampilan,
    satuan,
    disabled,
    dapatDisunting,
    periodeTerkunci,
    formatTanggal,
    values,
    onValueChange,
    galatSel,
    formatNilai,
}: MatriksTargetProps) {
    const manual = tipePerhitungan === 'manual';
    const kolom = manual
        ? [{ komponen_id: null as string | null, kode: null as string | null, label: null as string | null }]
        : komponen.map((item) => ({ komponen_id: item.komponen_id as string | null, kode: item.kode, label: item.label }));
    // Bobot disimpan numeric(30,12): buang nol di belakang koma lalu format sesuai setelan angka (1, 0,5).
    const formatBobot = (bobot: string): string => {
        const teks = tanpaNolBelakang(bobot);
        return formatNilai(teks, teks.split('.')[1]?.length ?? 0);
    };
    const kodeKomponen = (id: string | null): string => {
        if (id === null) {
            return 'Target';
        }
        return komponen.find((item) => item.komponen_id === id)?.kode ?? 'Komponen';
    };

    return (
        // Grid mobile: setiap periode menjadi kartu dengan label isian terlihat, tanpa scroll horizontal.
        <Table wrapperClassName="overflow-x-visible" className="block md:table">
            <caption className="sr-only">Matriks target per periode per komponen; skor dari hasil tersimpan</caption>
            <TableHeader className="hidden md:table-header-group">
                <TableRow>
                    <TableHead className="whitespace-normal">Periode</TableHead>
                    {manual ? (
                        <TableHead className="whitespace-normal">Target ({satuan})</TableHead>
                    ) : (
                        komponen.map((item) => (
                            <TableHead key={item.komponen_id} className="whitespace-normal normal-case tracking-normal">
                                <span className="block">{item.kode} · {item.label}</span>
                                <span className="mt-0.5 block text-xs font-normal text-muted">
                                    {peranKomponenLabel[item.peran] ?? item.peran}{item.bobot === '' ? '' : ` · Bobot ${formatBobot(item.bobot)}`}
                                </span>
                            </TableHead>
                        ))
                    )}
                    <TableHead className="whitespace-normal">Skor tersimpan</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody className="block md:table-row-group">
                {periode.map((baris) => {
                    if (!baris.efektif) {
                        return (
                            <TableRow key={baris.id} className="grid gap-2 bg-soft/40 p-4 md:table-row">
                                <TableHead scope="row" className="h-auto p-0 text-sm normal-case tracking-normal md:px-4 md:py-3.5">
                                    {baris.nama ?? 'Periode'}
                                    <RentangPeriode tanggal={baris.tanggal} tahun={tahun} />
                                </TableHead>
                                <TableCell colSpan={kolom.length} className="p-0 text-muted md:px-4 md:py-3.5">
                                    <Badge variant="muted" size="sm">Tidak berlaku</Badge>
                                    <p className="mt-1">Periode di luar cakupan indikator ini; tidak dapat diisi.</p>
                                </TableCell>
                                <TableCell className="hidden text-muted md:table-cell">—</TableCell>
                            </TableRow>
                        );
                    }

                    // Periode efektif yang dikunci (di luar lingkup koreksi
                    // atau periode terkunci) hanya-baca di UI (tak dikirim
                    // saat simpan); backend tetap menolak fail-closed.
                    const terkunciKoreksi = dapatDisunting ? !dapatDisunting(baris.id) : false;
                    const selDisabled = disabled || terkunciKoreksi;
                    const batas = baris.batas_terkunci_pic;
                    const terkunciAktor = periodeTerkunci.has(baris.id);
                    const kunciId = batas === null ? undefined : `kunci-${baris.id}`;

                    return (
                        <TableRow key={baris.id} className="grid grid-cols-2 gap-3 p-4 md:table-row md:align-top">
                            <TableHead scope="row" className="col-span-2 h-auto p-0 text-sm normal-case tracking-normal md:px-4 md:py-3.5">
                                <span className="flex flex-wrap items-center gap-2">
                                    {baris.nama ?? 'Periode'}
                                    {/* Perencanaan tetap dapat menyunting setelah batas PIC, jadi badgenya menyebut PIC agar tidak terbaca sebagai kunci bagi dirinya. */}
                                    {batas !== null && <Badge variant="muted" size="sm">{terkunciAktor ? 'Terkunci' : 'Terkunci bagi PIC'}</Badge>}
                                </span>
                                <RentangPeriode tanggal={baris.tanggal} tahun={tahun} />
                            </TableHead>
                            {kolom.map((sel) => {
                                const key = kunciSel(baris.id, sel.komponen_id);
                                const galat = galatSel(baris.id, sel.komponen_id);
                                const inputId = `target-${baris.id}-${sel.komponen_id ?? 'manual'}`;
                                const label = manual ? `Target ${baris.nama ?? ''} (${satuan})` : `${sel.kode} · ${sel.label} · ${baris.nama ?? ''}`;
                                return (
                                    // Di HP isian diratakan ke bawah agar sejajar walau panjang label komponen berbeda.
                                    <TableCell key={key} className={`flex min-w-0 flex-col justify-end p-0 md:table-cell md:px-4 md:py-3.5 ${manual ? 'col-span-2' : ''}`}>
                                        {/* Label visual untuk HP; label aksesibel tetap dari <label> sr-only milik Input. */}
                                        <span aria-hidden="true" data-label-hp className="mb-1 block text-xs text-muted md:hidden">
                                            {manual ? `Target (${satuan})` : `${sel.kode} · ${sel.label}`}
                                        </span>
                                        <Input
                                            id={inputId}
                                            name={inputId}
                                            label={label}
                                            labelClassName="sr-only"
                                            type="number"
                                            step="any"
                                            value={values[key] ?? ''}
                                            placeholder={baris.boleh_tanpa_target ? (selDisabled ? '—' : 'Tidak ditargetkan') : 'Belum diisi'}
                                            disabled={selDisabled}
                                            onChange={(event) => onValueChange(baris.id, sel.komponen_id, event.target.value)}
                                            error={galat}
                                            aria-invalid={galat ? true : undefined}
                                            aria-describedby={kunciId}
                                            helperText={values[key] === '0' || values[key] === '0.00' ? 'Nol eksplisit (berbeda dari kosong).' : undefined}
                                        />
                                    </TableCell>
                                );
                            })}
                            <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5">
                                <p className="text-xs text-muted md:hidden">Skor tersimpan</p>
                                <p className="font-mono text-sm font-semibold text-ink" data-testid={`skor-${baris.id}`}>
                                    {labelSkor(baris.skor.nilai, baris.skor.status_perhitungan, formatNilai, desimalTampilan, satuan)}
                                </p>
                                {batas !== null && (
                                    <p id={kunciId} className="mt-2 text-xs text-muted">
                                        {/* Periode yang boleh tanpa target tidak wajib diisi siapa pun, jadi tidak menyebut Tim Perencanaan. */}
                                        {terkunciAktor
                                            ? `Batas pengisian ${formatTanggal(batas)} sudah lewat.${baris.boleh_tanpa_target ? '' : ' Target diisi Tim Perencanaan.'}`
                                            : `Batas pengisian PIC ${formatTanggal(batas)} sudah lewat.`}
                                    </p>
                                )}
                                {/* Catatan per baris hanya membedakan baris saat sebagian matriks dapat disunting.
                                    Baris yang terkunci bagi aktor sudah dijelaskan alasan batas di atas; catatan tetap
                                    tampil bila baris nonaktif karena lingkup koreksi, termasuk baris "Terkunci bagi PIC". */}
                                {terkunciKoreksi && !disabled && !terkunciAktor && (
                                    <p className="mt-2 text-xs font-medium text-muted" data-testid={`koreksi-terkunci-${baris.id}`}>
                                        Periode dikunci; tidak disunting dan tidak dikirim.
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
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}
