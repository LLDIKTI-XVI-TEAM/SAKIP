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

    // Tanda dan nol dinilai dari nilai yang dibulatkan ke presisi tampilan agar tidak muncul "−0,00".
    const dibulatkan = Number(Number(selisih).toFixed(desimal));
    const negatif = dibulatkan < 0;
    const nol = dibulatkan === 0;
    const mutlak = String(Math.abs(dibulatkan));
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
