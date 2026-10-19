import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { ArahIndikator } from '@/types/dashboard';

interface SelisihTargetPkProps {
    selisih: string | null;
    arah: ArahIndikator;
    satuan: string;
    desimal: number;
}

/**
 * Selisih realisasi terhadap target PK dalam satuan indikator (Q47 §47.3). Tanda tidak dibalik; warna mengikuti arah
 * indikator dan teks "di atas/di bawah target" tetap ada agar makna tidak bergantung pada warna.
 */
export function SelisihTargetPk({ selisih, arah, satuan, desimal }: SelisihTargetPkProps) {
    const formatNilai = useFormatNilai();
    if (selisih === null) return <span className="text-muted">—</span>;

    const negatif = selisih.trim().startsWith('-');
    const nol = Number(selisih) === 0;
    const mutlak = negatif ? selisih.trim().slice(1) : selisih.trim();
    const tanda = nol ? '' : negatif ? '−' : '+';
    const baik = !nol && negatif === (arah === 'turun_baik');
    const warna = nol ? 'text-muted' : baik ? 'text-success-dark' : 'text-danger-dark';

    return (
        <span className="inline-flex flex-col items-start md:items-end">
            <span className={`font-semibold tabular-nums ${warna}`}>{`${tanda}${formatNilai(mutlak, desimal)} ${satuan}`}</span>
            <span className="text-xs text-muted">{nol ? 'sama dengan target' : negatif ? 'di bawah target' : 'di atas target'}</span>
        </span>
    );
}
