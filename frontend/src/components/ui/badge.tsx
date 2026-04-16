import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 transition-colors",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-[var(--accent-primary)] text-white",
        secondary:
          "border-transparent bg-[var(--bg-tertiary)] text-[var(--text-secondary)]",
        destructive:
          "border-transparent bg-red-100 text-red-700",
        warning:
          "border-transparent bg-amber-100 text-amber-700",
        success:
          "border-transparent bg-emerald-100 text-emerald-700",
        outline:
          "text-[var(--text-secondary)] border-[var(--border-default)]",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  ...props
}: React.ComponentProps<"span"> & VariantProps<typeof badgeVariants>) {
  return (
    <span
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
