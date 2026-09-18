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

export const RoleSwitcher: React.FC = () => {
    const { is_dev, demo_users, auth } = usePage<any>().props;

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
        <div className="bg-amber-50 border-b border-amber-200 px-4 py-2 text-xs flex flex-wrap items-center justify-between gap-2 shadow-inner">
            <div className="flex items-center gap-2 text-amber-900 font-medium">
                <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0" />
                <span className="font-bold uppercase tracking-wider text-[11px] bg-amber-200/80 px-2 py-0.5 rounded text-amber-900">
                    DEV Quick Role Switcher
                </span>
                <span className="hidden sm:inline text-amber-800">
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
                            className={`px-2.5 py-1 rounded-md text-xs font-medium transition-all flex items-center gap-1 cursor-pointer ${
                                isActive
                                    ? 'bg-[#122E92] text-white shadow-xs font-semibold cursor-default'
                                    : 'bg-white hover:bg-amber-100/80 text-slate-700 border border-amber-200 hover:border-amber-300'
                            }`}
                            title={`${u.name} - ${u.email}`}
                        >
                            {isActive && <UserCheck className="w-3 h-3 text-[#D6AC48]" />}
                            <span className="capitalize">{u.role}</span>
                            <span className="text-[10px] opacity-75">({u.unit})</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
