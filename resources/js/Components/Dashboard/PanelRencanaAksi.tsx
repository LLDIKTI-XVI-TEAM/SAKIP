import { ListChecks } from 'lucide-react';
import { Card } from '@/Components/Card';
import { KotakHitungan } from '@/Components/Dashboard/KotakHitungan';
import { STATUS_ALUR } from '@/Components/Dashboard/statusAlur';
import type { DashboardProps } from '@/types/dashboard';

/** Kesiapan Rencana Aksi per indikator; indikator tanpa RA dihitung "Belum disusun" agar tidak tertutup jumlah yang disahkan. */
export function PanelRencanaAksi({ progres, tahun }: { progres: DashboardProps['progresRencanaAksi']; tahun: number }) {
    const items = [{ key: 'belum_disusun' as const, label: 'Belum disusun' }, ...STATUS_ALUR];

    return (
        <section aria-labelledby="progres-rencana-aksi">
            <Card>
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
                {/* Di kolom sepertiga (xl) tiga kotak sebaris membuat label "Dikembalikan" meluber. */}
                <div className="grid grid-cols-2 gap-3 px-5 pb-5 sm:grid-cols-3 xl:grid-cols-2">
                    {items.map(({ key, label }) => (
                        <KotakHitungan key={key} status={key} label={label} nilai={progres[key]} />
                    ))}
                </div>
            </Card>
        </section>
    );
}
