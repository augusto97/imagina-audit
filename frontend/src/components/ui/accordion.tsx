import * as React from 'react'
import { cn } from '@/lib/utils'
import { ChevronDown } from 'lucide-react'

interface AccordionItemProps {
  title: React.ReactNode
  children: React.ReactNode
  defaultOpen?: boolean
  className?: string
}

function AccordionItem({ title, children, defaultOpen = false, className }: AccordionItemProps) {
  const [open, setOpen] = React.useState(defaultOpen)

  return (
    <div className={cn('border-b border-[var(--border-default)]', className)}>
      <button
        type="button"
        className="flex w-full items-center justify-between py-4 text-left text-sm font-medium text-[var(--text-primary)] hover:text-[var(--accent-primary)] transition-colors cursor-pointer"
        onClick={() => setOpen(!open)}
      >
        {title}
        <ChevronDown
          className={cn(
            'h-4 w-4 shrink-0 text-[var(--text-tertiary)] transition-transform duration-200',
            open && 'rotate-180'
          )}
        />
      </button>
      <div
        className={cn(
          'overflow-hidden transition-all duration-300',
          open ? 'max-h-[1000px] opacity-100 pb-4' : 'max-h-0 opacity-0'
        )}
      >
        {children}
      </div>
    </div>
  )
}

export { AccordionItem }
