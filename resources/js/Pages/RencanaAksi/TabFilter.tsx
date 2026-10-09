import { Link } from '@inertiajs/react';

export type TabRencanaAksi = 'draf' | 'antrean' | 'disahkan';

const TABS: { id: TabRencanaAksi; label: string; href: string }[] = [
    { id: 'draf', label: 'Penyusunan', href: '/rencana-aksi' },
    { id: 'antrean', label: 'Antrean', href: '/rencana-aksi?status=antrean' },
    { id: 'disahkan', label: 'Disahkan', href: '/rencana-aksi?status=disahkan' },
];

/** Tab daftar Rencana Aksi: titik masuk penyusunan, antrean pengesahan, dan daftar disahkan. */
export default function TabFilter({ active }: { active: TabRencanaAksi }) {
    return (
        <nav aria-label="Filter daftar rencana aksi" className="mb-6 flex w-fit gap-1 rounded-lg border border-border bg-soft p-1">
            {TABS.map((tab) => (
                <Link
                    key={tab.id}
                    href={tab.href}
                    aria-current={active === tab.id ? 'page' : undefined}
                    className={active === tab.id
                        ? 'rounded-md bg-surface px-3 py-1.5 text-xs font-semibold text-primary shadow-xs'
                        : 'rounded-md px-3 py-1.5 text-xs font-medium text-muted hover:text-ink'}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
