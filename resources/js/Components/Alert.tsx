import React from 'react';
import { Info, AlertTriangle, AlertCircle, CheckCircle2 } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type AlertVariant = 'info' | 'warning' | 'danger' | 'success';

export interface AlertProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'title'> {
    variant?: AlertVariant;
    title?: React.ReactNode;
    icon?: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>;
    hideIcon?: boolean;
    children?: React.ReactNode;
}

const variantStyles: Record<AlertVariant, { container: string; iconColor: string; defaultIcon: typeof Info }> = {
    info: {
        container: 'border-info/30 bg-info/10 text-info-dark',
        iconColor: 'text-info-dark',
        defaultIcon: Info,
    },
    warning: {
        container: 'border-warning/30 bg-warning/10 text-warning-dark',
        iconColor: 'text-warning-dark',
        defaultIcon: AlertTriangle,
    },
    danger: {
        container: 'border-danger/30 bg-danger/10 text-danger',
        iconColor: 'text-danger',
        defaultIcon: AlertCircle,
    },
    success: {
        container: 'border-success/30 bg-success/10 text-success-dark',
        iconColor: 'text-success-dark',
        defaultIcon: CheckCircle2,
    },
};

export const Alert = React.forwardRef<HTMLDivElement, AlertProps>(
    ({ variant = 'info', title, icon, hideIcon = false, className, children, ...props }, ref) => {
        const config = variantStyles[variant];
        const IconComponent = icon ?? config.defaultIcon;

        return (
            <div
                ref={ref}
                role={variant === 'danger' || variant === 'warning' ? 'alert' : 'status'}
                className={twMerge(
                    clsx(
                        'flex gap-3 rounded-lg border p-4 text-sm transition-colors',
                        config.container,
                        className
                    )
                )}
                {...props}
            >
                {!hideIcon && (
                    <div className="shrink-0 pt-0.5">
                        <IconComponent
                            className={twMerge(clsx('h-4 w-4', config.iconColor))}
                            aria-hidden="true"
                        />
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    {title && (
                        <h4 className="font-semibold leading-tight mb-1">
                            {title}
                        </h4>
                    )}
                    {children && <div className="leading-relaxed">{children}</div>}
                </div>
            </div>
        );
    }
);

Alert.displayName = 'Alert';

export default Alert;
