import { AccordionItem } from '@/components/ui/accordion'
import SemaphoreIcon from './SemaphoreIcon'
import type { MetricResult } from '@/types/audit'

interface MetricRowProps {
  metric: MetricResult
}

export default function MetricRow({ metric }: MetricRowProps) {
  return (
    <AccordionItem
      title={
        <div className="flex items-center gap-3 pr-2">
          <SemaphoreIcon level={metric.level} />
          <span className="flex-1 text-left">{metric.name}</span>
          <span className="text-xs text-[var(--text-tertiary)] shrink-0">
            {metric.displayValue}
          </span>
        </div>
      }
    >
      <div className="space-y-3 pl-6 text-sm">
        <p className="text-[var(--text-secondary)]">{metric.description}</p>

        {metric.recommendation && (
          <div className="rounded-xl bg-[var(--bg-tertiary)] p-3">
            <p className="text-xs font-semibold text-[var(--text-tertiary)] mb-1">Recomendación</p>
            <p className="text-[var(--text-secondary)]">{metric.recommendation}</p>
          </div>
        )}

        {metric.imaginaSolution && (
          <div className="rounded-xl border border-[var(--accent-primary)]/20 bg-[var(--accent-primary)]/5 p-3">
            <p className="text-xs font-semibold text-[var(--accent-primary)] mb-1">
              Cómo lo resuelve Imagina WP
            </p>
            <p className="text-[var(--text-secondary)]">{metric.imaginaSolution}</p>
          </div>
        )}
      </div>
    </AccordionItem>
  )
}
