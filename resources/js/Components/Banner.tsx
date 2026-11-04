import { Link } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, Info, X } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Tooltip } from '@/Components/Tooltip';

export interface BannerItem {
    id: string;
    signature: string;
    tingkat: 'info' | 'warning' | 'danger';
    judul: string;
    keterangan: string;
    href?: string | null;
    label?: string | null;
}

const tampilan = {
    info: { icon: Info, className: 'border-info/20 bg-info/10 text-info-dark' },
    warning: { icon: AlertTriangle, className: 'border-warning/30 bg-warning/10 text-warning-dark' },
    danger: { icon: AlertCircle, className: 'border-danger/20 bg-danger/10 text-danger-dark' },
};

export function Banner({ item, heading, onDismiss }: { item: BannerItem; heading: string; onDismiss: () => void }) {
    const { icon: Icon, className } = tampilan[item.tingkat];

    return (
        <div role={item.tingkat === 'danger' ? 'alert' : 'status'} aria-label={item.judul}
            className={`flex min-w-0 items-start gap-3 rounded-xl border p-4 text-sm ${className}`}>
            <Icon className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1 break-words">
                <h2 className="font-semibold">{heading}</h2>
                <p className="mt-1 font-medium">{item.judul}</p>
                <p className="mt-1 leading-relaxed">{item.keterangan}</p>
                {item.href && item.label && (
                    <Link href={item.href} className="mt-2 inline-flex min-h-11 items-center rounded-md font-semibold underline underline-offset-4 hover:no-underline focus:outline-none focus:ring-2 focus:ring-current focus:ring-offset-2">
                        {item.label}
                    </Link>
                )}
            </div>
            <Tooltip content="Tutup pengingat" align="right">
                <Button type="button" variant="ghost" size="sm" onClick={onDismiss}
                    aria-label={`Tutup pengingat: ${item.judul}`}
                    className="-mr-2 -mt-2 h-11 w-11 shrink-0 p-0 text-current hover:bg-surface/60 hover:text-current focus:ring-current">
                    <X className="h-4 w-4" aria-hidden="true" />
                </Button>
            </Tooltip>
        </div>
    );
}
