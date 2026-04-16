import * as React from "react"
import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "file:text-foreground placeholder:text-[var(--text-tertiary)] selection:bg-[var(--accent-primary)]/20 flex h-9 w-full min-w-0 rounded-lg border border-[var(--border-default)] bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-colors file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:opacity-50",
        "focus-visible:border-[var(--accent-primary)] focus-visible:ring-[3px] focus-visible:ring-[var(--accent-primary)]/15",
        "aria-invalid:border-red-500 aria-invalid:ring-red-500/20",
        className
      )}
      {...props}
    />
  )
}

export { Input }
