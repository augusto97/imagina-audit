import { memo } from 'react'
import { useTranslation } from 'react-i18next'
import { Blocks, Shield, Gauge, Search, Smartphone, Server, BarChart3, Activity, Database, HelpCircle, ChevronRight, type LucideIcon } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { getLevelColor, getLevelLabel } from '@/lib/utils'
import type { ModuleResult } from '@/types/audit'

/**
 * Grid de mini-gauges circulares — uno por módulo de la auditoría.
 * Reemplaza la lista vertical plana que había en ExecutiveSummary.
 *
 * Click en un gauge dispara `onModuleClick(moduleId)` — el padre
 * puede switchear al tab "Detalles" y scrollear al módulo.
 */
export const ModuleScoreGrid = memo(function ModuleScoreGrid({
  modules,
  onModuleClick,
}: {
  modules: ModuleResult[]
  onModuleClick?: (moduleId: string) => void
}) {
  const { t } = useTranslation()
  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <CardTitle className="text-base">{t('report.modules_grid_title')}</CardTitle>
          <span className="text-xs text-[var(--text-tertiary)]">
            {t('report.modules_grid_click_hint')}
          </span>
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
          {modules.map(m => (
            <ModuleGaugeTile key={m.id} module={m} onClick={onModuleClick} />
          ))}
        </div>
      </CardContent>
    </Card>
  )
})

const MODULE_ICONS: Record<string, LucideIcon> = {
  wordpress: Blocks,
  security: Shield,
  performance: Gauge,
  seo: Search,
  mobile: Smartphone,
  infrastructure: Server,
  conversion: BarChart3,
  page_health: Activity,
  wp_internal: Database,
}

function ModuleGaugeTile({
  module: m,
  onClick,
}: {
  module: ModuleResult
  onClick?: (moduleId: string) => void
}) {
  const Icon = MODULE_ICONS[m.id] ?? HelpCircle
  const color = getLevelColor(m.level)
  const score = m.score ?? 0
  const isClickable = Boolean(onClick)

  return (
    <button
      type="button"
      onClick={() => onClick?.(m.id)}
      disabled={!isClickable}
      className={`group flex flex-col items-center gap-2 rounded-xl border border-[var(--border-default)] bg-white p-3 transition-all ${
        isClickable ? 'cursor-pointer hover:border-[var(--text-tertiary)] hover:shadow-sm' : 'cursor-default'
      }`}
    >
      <CircularGauge score={score} color={color} label={m.score === null ? '—' : undefined} />

      <div className="flex w-full items-center gap-1.5 min-w-0">
        <Icon className="h-3.5 w-3.5 shrink-0 text-[var(--text-tertiary)]" strokeWidth={1.5} />
        <span className="truncate text-[11px] font-semibold text-[var(--text-primary)]" title={m.name}>
          {m.name}
        </span>
        {isClickable && (
          <ChevronRight className="ml-auto h-3 w-3 shrink-0 text-[var(--text-tertiary)] opacity-0 transition-opacity group-hover:opacity-100" />
        )}
      </div>

      <div className="text-[10px] font-medium uppercase tracking-wider" style={{ color }}>
        {getLevelLabel(m.level)}
      </div>
    </button>
  )
}

/**
 * Gauge circular de ~64px con progress ring coloreado por nivel.
 * Dibuja un círculo base gris + un arco progresivo (strokeDasharray)
 * que representa el porcentaje del score. El número va centrado.
 */
function CircularGauge({ score, color, label }: { score: number; color: string; label?: string }) {
  const size = 64
  const strokeWidth = 5
  const radius = (size - strokeWidth) / 2
  const circumference = 2 * Math.PI * radius
  const pct = Math.max(0, Math.min(100, score)) / 100
  const dash = circumference * pct

  return (
    <div className="relative" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke="var(--border-default)"
          strokeWidth={strokeWidth}
          fill="none"
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke={color}
          strokeWidth={strokeWidth}
          strokeLinecap="round"
          strokeDasharray={`${dash} ${circumference}`}
          fill="none"
          className="transition-all duration-500 ease-out"
        />
      </svg>
      <div className="absolute inset-0 flex items-center justify-center">
        <span className="text-base font-bold tabular-nums" style={{ color: label ? 'var(--text-tertiary)' : color }}>
          {label ?? score}
        </span>
      </div>
    </div>
  )
}
