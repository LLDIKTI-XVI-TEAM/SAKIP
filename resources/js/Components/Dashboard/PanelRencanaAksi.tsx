import { ListChecks } from 'lucide-react';
import { Card } from '@/Components/Card';
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
                <div className="grid grid-cols-2 gap-3 px-5 pb-5 sm:grid-cols-3">
                    {items.map(({ key, label }) => (
                        <div key={key} data-status={key} className="rounded-lg border border-border bg-soft/40 px-3 py-2.5">
                            <p className="text-xs text-muted">{label}</p>
                            <p className="mt-1 text-xl font-bold tabular-nums text-ink">{progres[key]}</p>
                        </div>
                    ))}
                </div>
            </Card>
        </section>
    );
}
