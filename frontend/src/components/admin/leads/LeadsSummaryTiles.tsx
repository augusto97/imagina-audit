import { useTranslation } from 'react-i18next'
import { FileSearch, UserCheck, AlertCircle, Blocks, Pin, Database, CalendarDays, type LucideIcon } from 'lucide-react'
import type { LeadsSummary } from '@/types/lead'

/**
 * Tiles compactos con contadores globales, clickeables para filtrar.
 *
 * Layout por tile:
 *   ┌──────────────────────┐
 *   │ [icon·bg]    LABEL   │
 *   │             123      │
 *   └──────────────────────┘
 *
 * Decisiones visuales:
 *  - Icono dentro de un badge tintado (rounded-lg) a la izquierda,
 *    no un icono suelto en la esquina.
 *  - Tono del value es dinámico: 'Score crítico' solo se pinta rojo si
 *    el contador es > 0. Antes el 0 en rojo era ruido visual.
 *  - Grid 4 columnas en laptop, 7 en desktop grande — antes 7 forzados
 *    hacían tiles muy anchos con label truncado.
 *  - Active state con ring + bg tintado, no solo border.
 *  - Misma altura siempre (h-[82px]) para evitar que el baseline baile
 *    con números de distinto número de dígitos.
 */

type Tone = 'neutral' | 'accent' | 'good' | 'critical' | 'amber'

interface TileDef {
  key: string
  filterKey: string
  label: string
  value: number
  icon: LucideIcon
  staticTone: Tone           // tono base del icono
  valueToneWhenPositive?: Tone // si value > 0, el número toma este tono (ej. críticos)
}

export function LeadsSummaryTiles({
  summary,
  activeFilter,
  onFilter,
}: {
  summary: LeadsSummary
  activeFilter: string
  onFilter: (filter: string) => void
}) {
  const { t } = useTranslation()
  const tiles: TileDef[] = [
    { key: 'total',       filterKey: 'all',          label: t('leads.tile_total'),           value: summary.total,        icon: FileSearch,   staticTone: 'accent' },
    { key: 'withContact', filterKey: 'with_contact', label: t('leads.tile_with_contact'),    value: summary.withContact,  icon: UserCheck,    staticTone: 'good' },
    { key: 'critical',    filterKey: 'critical',     label: t('leads.tile_critical_score'),  value: summary.critical,     icon: AlertCircle,  staticTone: 'neutral', valueToneWhenPositive: 'critical' },
    { key: 'wordpress',   filterKey: 'wp_yes',       label: t('leads.tile_wordpress'),       value: summary.wordpress,    icon: Blocks,       staticTone: 'accent' },
    { key: 'snapshot',    filterKey: 'snap_yes',     label: t('leads.tile_with_snapshot'),   value: summary.withSnapshot, icon: Database,     staticTone: 'good' },
    { key: 'pinned',      filterKey: 'pin_yes',      label: t('leads.tile_protected'),       value: summary.pinned,       icon: Pin,          staticTone: 'amber' },
    { key: 'thisWeek',    filterKey: 'this_week',    label: t('leads.tile_last_7_days'),     value: summary.thisWeek,     icon: CalendarDays, staticTone: 'neutral' },
  ]

  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-7">
      {tiles.map((t) => (
        <Tile
          key={t.key}
          def={t}
          active={t.filterKey === activeFilter}
          onClick={() => onFilter(t.filterKey)}
        />
      ))}
    </div>
  )
}

function Tile({ def, active, onClick }: { def: TileDef; active: boolean; onClick: () => void }) {
  const Icon = def.icon
  const iconTone = toneStyles[def.staticTone]
  const valueTone = def.value > 0 && def.valueToneWhenPositive
    ? toneStyles[def.valueToneWhenPositive].text
    : 'text-[var(--text-primary)]'

  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`group relative flex h-[82px] items-center gap-3 rounded-xl border bg-white px-3 text-left transition-all ${
        active
          ? 'border-[var(--accent-primary)] bg-[var(--accent-primary)]/5 shadow-[0_0_0_3px_var(--accent-primary)]/10 ring-1 ring-[var(--accent-primary)]'
          : 'border-[var(--border-default)] hover:border-[var(--text-tertiary)] hover:shadow-sm'
      }`}
    >
      <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${iconTone.bg} ${iconTone.icon}`}>
        <Icon className="h-5 w-5" strokeWidth={1.75} />
      </div>
      <div className="min-w-0 flex-1">
        <div className="text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
          {def.label}
        </div>
        <div className={`mt-0.5 text-2xl font-bold tabular-nums leading-none ${valueTone}`}>
          {def.value}
        </div>
      </div>
    </button>
  )
}

const toneStyles: Record<Tone, { bg: string; icon: string; text: string }> = {
  neutral:  { bg: 'bg-slate-100',   icon: 'text-slate-600',   text: 'text-[var(--text-primary)]' },
  accent:   { bg: 'bg-sky-100',     icon: 'text-sky-700',     text: 'text-sky-700' },
  good:     { bg: 'bg-emerald-100', icon: 'text-emerald-700', text: 'text-emerald-700' },
  critical: { bg: 'bg-red-100',     icon: 'text-red-600',     text: 'text-red-600' },
  amber:    { bg: 'bg-amber-100',   icon: 'text-amber-600',   text: 'text-amber-600' },
}
