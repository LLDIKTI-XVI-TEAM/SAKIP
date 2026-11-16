import { ListChecks } from 'lucide-react';
import { Card } from '@/Components/Card';
import { DaftarHitungan } from '@/Components/Dashboard/DaftarHitungan';
import { STATUS_ALUR } from '@/Components/Dashboard/statusAlur';
import type { DashboardProps } from '@/types/dashboard';

/** Kesiapan Rencana Aksi per indikator; indikator tanpa RA dihitung "Belum disusun" agar tidak tertutup jumlah yang disahkan. */
export function PanelRencanaAksi({ progres, tahun }: { progres: DashboardProps['progresRencanaAksi']; tahun: number }) {
    // "Belum disusun" memakai token yang sama dengan "Belum mengisi" pada grafik progres pengukuran.
    const items = [{ key: 'belum_disusun' as const, label: 'Belum disusun', token: 'secondary' }, ...STATUS_ALUR].map(({ key, label, token }) => ({
        key,
        label,
        token,
        nilai: progres[key],
    }));

    return (
        <section aria-labelledby="progres-rencana-aksi" className="h-full">
            <Card className="h-full">
                <div className="flex items-start gap-3 px-5 pt-5 pb-3">
                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                        <ListChecks className="h-4 w-4" aria-hidden="true" />
                    </span>
                    <div className="min-w-0">
                        <h2 id="progres-rencana-aksi" className="text-base font-semibold tracking-tight text-ink">
                            Progres Rencana Aksi {tahun}
                        </h2>
                        <p className="mt-0.5 text-xs text-muted">Jumlah indikator per status Rencana Aksi.</p>
                    </div>
                </div>
                <div className="px-5 pb-5">
                    <DaftarHitungan items={items} satuan="indikator" />
                </div>
            </Card>
        </section>
    );
}
