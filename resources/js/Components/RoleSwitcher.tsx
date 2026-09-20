import React from 'react';
import { router, usePage } from '@inertiajs/react';
import { ShieldAlert, UserCheck } from 'lucide-react';

interface DemoUser {
    id: number;
    name: string;
    email: string;
    role: string;
    unit: string;
}

interface RoleSwitcherPageProps {
    [key: string]: unknown;
    is_dev?: boolean;
    demo_users?: DemoUser[];
    auth?: {
        user?: {
            id?: number;
        } | null;
    };
}

export const RoleSwitcher: React.FC = () => {
    const { is_dev, demo_users, auth } = usePage<RoleSwitcherPageProps>().props;

    if (!is_dev || !demo_users || demo_users.length === 0) {
        return null;
    }

    const currentUserId = auth?.user?.id;

    const handleSwitch = (userId: number) => {
        router.post(`/dev/switch-role/${userId}`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-warning/30 bg-warning/10 px-4 py-2 text-xs shadow-inner">
            <div className="flex items-center gap-2 font-medium text-warning-dark">
                <ShieldAlert className="h-4 w-4 shrink-0 text-warning-dark" />
                <span className="rounded bg-warning/20 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-warning-dark">
                    DEV Quick Role Switcher
                </span>
                <span className="hidden text-warning-dark sm:inline">
                    Pilih peran untuk menguji alur otorisasi tanpa logout:
                </span>
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
                {demo_users.map((u: DemoUser) => {
                    const isActive = u.id === currentUserId;
                    return (
                        <button
                            key={u.id}
                            onClick={() => !isActive && handleSwitch(u.id)}
                            disabled={isActive}
                            className={`flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition-all ${
                                isActive
                                    ? 'bg-primary text-white shadow-xs font-semibold cursor-default'
                                    : 'border border-border bg-surface text-ink hover:bg-soft hover:border-primary/25'
                            }`}
                            title={`${u.name} - ${u.email}`}
                        >
                            {isActive && <UserCheck className="w-3 h-3 text-secondary" />}
                            <span className="capitalize">{u.role}</span>
                            <span className="text-[10px] opacity-75">({u.unit})</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
