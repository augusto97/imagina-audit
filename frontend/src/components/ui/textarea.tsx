import * as React from "react"
import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "placeholder:text-[var(--text-tertiary)] flex min-h-[80px] w-full rounded-lg border border-[var(--border-default)] bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-colors",
        "focus-visible:border-[var(--accent-primary)] focus-visible:ring-[3px] focus-visible:ring-[var(--accent-primary)]/15",
        "disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
