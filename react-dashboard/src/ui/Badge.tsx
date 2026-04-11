import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '../lib/cn';

type Variant = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'violet';
type Size = 'sm' | 'md';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: Variant;
  size?: Size;
  icon?: ReactNode;
  dot?: boolean;
  children: ReactNode;
}

const variantStyles: Record<Variant, string> = {
  neutral: 'bg-surface2/80 text-text-muted border-border ring-1 ring-inset ring-white/5',
  success: 'bg-green-500/12 text-green-300 border-green-500/30 ring-1 ring-inset ring-green-400/10',
  warning: 'bg-amber-500/12 text-amber-300 border-amber-500/30 ring-1 ring-inset ring-amber-400/10',
  danger:  'bg-danger/12 text-red-300 border-danger/30 ring-1 ring-inset ring-red-400/10',
  info:    'bg-blue-500/12 text-blue-300 border-blue-500/30 ring-1 ring-inset ring-blue-400/10',
  violet:  'bg-violet/12 text-violet-light border-violet/30 ring-1 ring-inset ring-violet/10',
};

const dotStyles: Record<Variant, string> = {
  neutral: 'bg-text-muted',
  success: 'bg-green-400',
  warning: 'bg-amber-400',
  danger:  'bg-danger',
  info:    'bg-blue-400',
  violet:  'bg-violet-light',
};

const sizeStyles: Record<Size, string> = {
  sm: 'text-[11px] px-2 py-0.5 gap-1',
  md: 'text-xs px-2.5 py-1 gap-1.5',
};

/**
 * Badge — compact status indicator.
 * - 6 variants (neutral, success, warning, danger, info, violet)
 * - Optional dot or icon prefix
 */
export function Badge({
  variant = 'neutral',
  size = 'md',
  icon,
  dot = false,
  className,
  children,
  ...rest
}: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center font-medium rounded-full border',
        variantStyles[variant],
        sizeStyles[size],
        className,
      )}
      {...rest}
    >
      {dot && (
        <span
          aria-hidden="true"
          className={cn('inline-block h-1.5 w-1.5 rounded-full', dotStyles[variant])}
        />
      )}
      {icon && <span aria-hidden="true" className="shrink-0">{icon}</span>}
      {children}
    </span>
  );
}
