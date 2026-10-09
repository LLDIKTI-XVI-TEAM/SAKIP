import { ChevronRight } from 'lucide-react';
import { Link } from '@inertiajs/react';
import type { SidebarNavGroup } from '@/lib/navigation/sidebarNavigation';

export interface SidebarGroupProps {
    group: SidebarNavGroup;
    isExpanded: boolean;
    onToggle: () => void;
    activeItemId: string | null;
    onNavigate?: () => void;
}

export function SidebarGroup({
    group,
    isExpanded,
    onToggle,
    activeItemId,
    onNavigate,
}: SidebarGroupProps) {
    const hasActiveChild = group.children.some((child) => child.id === activeItemId);
    const Icon = group.icon;
    const buttonId = `nav-group-btn-${group.id}`;
    const panelId = `nav-group-panel-${group.id}`;

    return (
        <div className="space-y-1">
            <button
                type="button"
                id={buttonId}
                aria-expanded={isExpanded}
                aria-controls={panelId}
                onClick={onToggle}
                className={`group/btn flex w-full items-center justify-between gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all ${
                    hasActiveChild
                        ? 'bg-surface/15 text-white font-semibold shadow-xs'
                        : 'text-white/80 hover:bg-surface/10 hover:text-white'
                }`}
            >
                <div className="flex min-w-0 items-center gap-3">
                    <Icon
                        aria-hidden="true"
                        className={`h-[18px] w-[18px] shrink-0 transition-colors ${
                            hasActiveChild ? 'text-white' : 'text-white/75 group-hover/btn:text-white'
                        }`}
                    />
                    <span className="truncate">{group.label}</span>
                </div>
                <ChevronRight
                    aria-hidden="true"
                    className={`h-4 w-4 shrink-0 text-white/60 transition-transform duration-200 ease-in-out motion-reduce:transition-none group-hover/btn:text-white/80 ${
                        isExpanded ? 'rotate-90' : ''
                    }`}
                />
            </button>

            <div
                id={panelId}
                role="group"
                aria-labelledby={buttonId}
                aria-label={group.label}
                hidden={!isExpanded}
                className={isExpanded ? 'space-y-1 pl-4 pt-0.5 pb-1' : 'hidden'}
            >
                {group.children.map((child) => {
                    const isActive = activeItemId === child.id;
                    const ChildIcon = child.icon;

                    return (
                        <Link
                            key={child.id}
                            href={child.href}
                            title={child.label}
                            aria-current={isActive ? 'page' : undefined}
                            onClick={onNavigate}
                            className={`group/child flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium transition-all ${
                                isActive
                                    ? 'bg-surface/20 text-white font-semibold shadow-2xs'
                                    : 'text-white/70 hover:bg-surface/10 hover:text-white'
                            }`}
                        >
                            <ChildIcon
                                aria-hidden="true"
                                className={`h-4 w-4 shrink-0 transition-colors ${
                                    isActive ? 'text-white' : 'text-white/60 group-hover/child:text-white'
                                }`}
                            />
                            <span className="truncate">{child.label}</span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
