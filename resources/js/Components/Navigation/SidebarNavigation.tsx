import { useEffect, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import type { SharedPageProps } from '@/types/auth';
import {
    findActiveNavigation,
    getSidebarNavEntries,
} from '@/lib/navigation/sidebarNavigation';
import { SidebarGroup } from '@/Components/Navigation/SidebarGroup';

export interface SidebarNavigationProps {
    can?: SharedPageProps['auth']['can'];
    url: string;
    onNavigate?: () => void;
    defaultExpandedGroupIds?: string[];
}

export function SidebarNavigation({
    can,
    url,
    onNavigate,
    defaultExpandedGroupIds,
}: SidebarNavigationProps) {
    const entries = getSidebarNavEntries(can);
    const { activeItemId, activeGroupId } = findActiveNavigation(entries, url);

    const [expandedGroups, setExpandedGroups] = useState<Set<string>>(() => {
        const initial = new Set<string>(defaultExpandedGroupIds);
        if (activeGroupId) {
            initial.add(activeGroupId);
        }
        return initial;
    });

    // Otomatis membuka parent group bila pengguna berpindah ke rute anak di dalamnya
    useEffect(() => {
        if (activeGroupId) {
            setExpandedGroups((prev) => {
                if (prev.has(activeGroupId)) return prev;
                const next = new Set(prev);
                next.add(activeGroupId);
                return next;
            });
        }
    }, [activeGroupId]);

    const toggleGroup = (groupId: string) => {
        setExpandedGroups((prev) => {
            const next = new Set(prev);
            if (next.has(groupId)) {
                next.delete(groupId);
            } else {
                next.add(groupId);
            }
            return next;
        });
    };

    return (
        <nav aria-label="Navigasi utama" className="space-y-1.5">
            {entries.map((entry) => {
                if (entry.type === 'item') {
                    if (!entry.item.visible) return null;
                    const isActive = activeItemId === entry.item.id;
                    const Icon = entry.item.icon;

                    return (
                        <Link
                            key={entry.item.id}
                            href={entry.item.href}
                            title={entry.item.label}
                            aria-current={isActive ? 'page' : undefined}
                            onClick={onNavigate}
                            className={`group flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all ${
                                isActive
                                    ? 'bg-surface/15 text-white font-semibold shadow-xs'
                                    : 'text-white/80 hover:bg-surface/10 hover:text-white'
                            }`}
                        >
                            <Icon
                                aria-hidden="true"
                                className={`h-[18px] w-[18px] shrink-0 transition-colors ${
                                    isActive ? 'text-white' : 'text-white/75 group-hover:text-white'
                                }`}
                            />
                            <span className="truncate">{entry.item.label}</span>
                        </Link>
                    );
                }

                // Jangan render grup jika semua anaknya tidak visible
                if (entry.group.children.length === 0) {
                    return null;
                }

                return (
                    <SidebarGroup
                        key={entry.group.id}
                        group={entry.group}
                        isExpanded={expandedGroups.has(entry.group.id)}
                        onToggle={() => toggleGroup(entry.group.id)}
                        activeItemId={activeItemId}
                        onNavigate={onNavigate}
                    />
                );
            })}
        </nav>
    );
}
