import React from 'react';
import { Link } from '@inertiajs/react';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import { Tooltip, TooltipProps } from './Tooltip';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type IconButtonVariant = 'outline' | 'ghost' | 'primary' | 'danger';
export type IconButtonSize = 'xs' | 'sm' | 'md';

export interface IconButtonProps
    extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'children'> {
    icon: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>;
    label: string;
    href?: string;
    tooltip?: string;
    tooltipAlign?: TooltipProps['align'];
    tooltipPosition?: TooltipProps['position'];
    variant?: IconButtonVariant;
    size?: IconButtonSize;
}

const variantStyles: Record<IconButtonVariant, string> = {
    outline:
        'border border-border bg-surface text-muted hover:border-primary/40 hover:bg-soft hover:text-primary focus:ring-primary/20 shadow-2xs',
    ghost: 'text-muted hover:bg-soft hover:text-primary focus:ring-primary/20',
    primary: 'bg-primary text-white hover:bg-primary/90 focus:ring-primary/30 shadow-xs',
    danger: 'text-danger border border-transparent hover:border-danger/30 hover:bg-danger/10 focus:ring-danger/20',
};

const sizeStyles: Record<IconButtonSize, { button: string; icon: string }> = {
    xs: { button: 'h-7 w-7 p-1', icon: 'h-3.5 w-3.5' },
    sm: { button: 'h-8 w-8 p-1.5', icon: 'h-4 w-4' },
    md: { button: 'h-9 w-9 p-2', icon: 'h-4.5 w-4.5' },
};

export const IconButton: React.FC<IconButtonProps> = ({
    icon: Icon,
    label,
    href,
    tooltip,
    tooltipAlign = 'center',
    tooltipPosition = 'top',
    variant = 'outline',
    size = 'sm',
    className,
    disabled,
    ...props
}) => {
    const sizeConfig = sizeStyles[size];
    const baseClasses =
        'inline-flex items-center justify-center rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 select-none cursor-pointer';
    const classes = twMerge(
        clsx(baseClasses, variantStyles[variant], sizeConfig.button, className)
    );

    const content = (
        <>
            <Icon className={sizeConfig.icon} aria-hidden="true" />
            <span className="sr-only">{label}</span>
        </>
    );

    const buttonElement = href ? (
        <Link
            href={href}
            className={classes}
            aria-label={label}
            onClick={props.onClick as any}
        >
            {content}
        </Link>
    ) : (
        <button
            type={props.type || 'button'}
            disabled={disabled}
            className={classes}
            aria-label={label}
            {...props}
        >
            {content}
        </button>
    );

    if (tooltip) {
        return (
            <Tooltip content={tooltip} align={tooltipAlign} position={tooltipPosition}>
                {buttonElement}
            </Tooltip>
        );
    }

    return buttonElement;
};

export interface ViewIconButtonProps
    extends Omit<IconButtonProps, 'icon' | 'label'> {
    icon?: IconButtonProps['icon'];
    label?: string;
}

export const ViewIconButton: React.FC<ViewIconButtonProps> = ({
    icon = Eye,
    label = 'Lihat Detail',
    tooltip = 'Lihat Detail',
    variant = 'ghost',
    ...props
}) => (
    <IconButton
        icon={icon}
        label={label}
        tooltip={tooltip}
        variant={variant}
        {...props}
    />
);

export interface EditIconButtonProps
    extends Omit<IconButtonProps, 'icon' | 'label'> {
    icon?: IconButtonProps['icon'];
    label?: string;
}

export const EditIconButton: React.FC<EditIconButtonProps> = ({
    icon = Pencil,
    label = 'Edit Dokumen',
    tooltip = 'Edit Dokumen',
    variant = 'ghost',
    ...props
}) => (
    <IconButton
        icon={icon}
        label={label}
        tooltip={tooltip}
        variant={variant}
        {...props}
    />
);

export interface DeleteIconButtonProps
    extends Omit<IconButtonProps, 'icon' | 'label'> {
    icon?: IconButtonProps['icon'];
    label?: string;
}

export const DeleteIconButton: React.FC<DeleteIconButtonProps> = ({
    icon = Trash2,
    label = 'Hapus',
    tooltip = 'Hapus',
    variant = 'danger',
    ...props
}) => (
    <IconButton
        icon={icon}
        label={label}
        tooltip={tooltip}
        variant={variant}
        {...props}
    />
);

export default IconButton;

