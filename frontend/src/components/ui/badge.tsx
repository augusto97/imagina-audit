import * as React from 'react'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const badgeVariants = cva(
  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors',
  {
    variants: {
      variant: {
        default: 'border-transparent bg-[var(--accent-primary)] text-white',
        secondary: 'border-transparent bg-[var(--bg-tertiary)] text-[var(--text-secondary)]',
        destructive: 'border-transparent bg-red-500/20 text-red-400',
        warning: 'border-transparent bg-amber-500/20 text-amber-400',
        success: 'border-transparent bg-emerald-500/20 text-emerald-400',
        outline: 'text-[var(--text-secondary)] border-[var(--border-default)]',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return <div className={cn(badgeVariants({ variant }), className)} {...props} />
}

export { Badge, badgeVariants }
