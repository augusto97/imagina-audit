import { FileSearch, UserCheck, AlertCircle, Blocks, Pin, Database, CalendarDays, type LucideIcon } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import type { LeadsSummary } from '@/types/lead'

/**
 * 7 tiles compactos con los contadores globales de leads. Cada tile es
 * clickeable y dispara un filtro predefinido en la tabla (p.ej. 'con
 * contacto' → filter=with_contact).
 *
 * Los tiles "filtrables" se resaltan cuando su filtro asociado está activo.
 */
export function LeadsSummaryTiles({
  summary,
  activeFilter,
  onFilter,
}: {
  summary: LeadsSummary
  activeFilter: string
  onFilter: (filter: string) => void
}) {
  const tiles: Array<{
    key: string
    filterKey: string | null
    label: string
    value: number
    icon: LucideIcon
    tone: 'neutral' | 'good' | 'warning' | 'critical' | 'amber'
  }> = [
    { key: 'total',      filterKey: 'all',           label: 'Total',              value: summary.total,        icon: FileSearch,   tone: 'neutral' },
    { key: 'withContact', filterKey: 'with_contact', label: 'Con contacto',       value: summary.withContact,  icon: UserCheck,    tone: 'good' },
    { key: 'critical',   filterKey: 'critical',      label: 'Score crítico',      value: summary.critical,     icon: AlertCircle,  tone: 'critical' },
    { key: 'wordpress',  filterKey: 'wp_yes',        label: 'WordPress',          value: summary.wordpress,    icon: Blocks,       tone: 'neutral' },
    { key: 'snapshot',   filterKey: 'snap_yes',      label: 'Con snapshot',       value: summary.withSnapshot, icon: Database,     tone: 'good' },
    { key: 'pinned',     filterKey: 'pin_yes',       label: 'Protegidos',         value: summary.pinned,       icon: Pin,          tone: 'amber' },
    { key: 'thisWeek',   filterKey: 'this_week',     label: 'Últimos 7 días',     value: summary.thisWeek,     icon: CalendarDays, tone: 'neutral' },
  ]

  const tones = {
    neutral:  { text: 'text-[var(--text-primary)]', icon: 'text-[var(--text-tertiary)]' },
    good:     { text: 'text-emerald-700',           icon: 'text-emerald-600' },
    warning:  { text: 'text-amber-700',             icon: 'text-amber-600' },
    critical: { text: 'text-red-700',               icon: 'text-red-600' },
    amber:    { text: 'text-amber-700',             icon: 'text-amber-500 fill-amber-500' },
  } as const

  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
      {tiles.map((t) => {
        const isActive = t.filterKey === activeFilter
        const Icon = t.icon
        const tone = tones[t.tone]
        return (
          <Card
            key={t.key}
            onClick={() => t.filterKey && onFilter(t.filterKey)}
            className={`cursor-pointer border shadow-sm transition-all hover:shadow-md ${
              isActive ? 'border-[var(--accent-primary)] ring-1 ring-[var(--accent-primary)]' : 'border-[var(--border-default)]'
            }`}
          >
            <CardContent className="p-3">
              <div className="flex items-center justify-between gap-2">
                <Icon className={`h-4 w-4 shrink-0 ${tone.icon}`} strokeWidth={1.5} />
                <span className="text-[10px] font-medium uppercase tracking-wider text-[var(--text-tertiary)] truncate">
                  {t.label}
                </span>
              </div>
              <p className={`mt-1 text-2xl font-bold tabular-nums ${tone.text}`}>{t.value}</p>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}
