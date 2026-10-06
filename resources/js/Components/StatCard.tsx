import React, { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/Card';
import type { LucideIcon } from 'lucide-react';

export interface StatCardProps {
    title: string;
    value: ReactNode;
    icon: LucideIcon;
    iconVariant?: 'primary' | 'muted' | 'success' | 'warning' | 'secondary';
    description?: ReactNode;
    testId?: string;
    className?: string;
}

const variantStyles: Record<string, string> = {
    primary: 'bg-primary/10 text-primary border border-primary/20',
    muted: 'bg-soft text-ink border border-border',
    secondary: 'bg-secondary/15 text-ink border border-secondary/25',
    success: 'bg-success/15 text-success border border-success/25',
    warning: 'bg-warning/15 text-warning-dark border border-warning/25',
};

export function StatCard({
    title,
    value,
    icon: Icon,
    iconVariant = 'primary',
    description,
    testId,
    className,
}: StatCardProps) {
    return (
        <Card className={`border-border bg-surface shadow-xs transition-shadow hover:shadow-sm ${className ?? ''}`}>
            <CardContent className="p-4 sm:p-5">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-muted truncate">
                        {title}
                    </span>
                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${variantStyles[iconVariant] ?? variantStyles.primary}`}>
                        <Icon className="h-4.5 w-4.5" aria-hidden="true" />
                    </span>
                </div>
                <p className="mt-2 text-2xl font-bold tracking-tight text-ink" data-testid={testId}>
                    {value}
                </p>
                {description && (
                    <p className="mt-1 text-xs text-muted leading-relaxed">
                        {description}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default StatCard;
