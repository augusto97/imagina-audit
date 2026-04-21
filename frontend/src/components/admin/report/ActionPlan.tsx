import { useMemo, useState } from 'react'
import { Check, Filter, CheckCircle2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { levelBg, type ChecklistState, type MetricWithModule } from './helpers'

type FilterKey = 'all' | 'pending' | 'done' | 'critical' | 'warning'

/**
 * Plan de acción con los problemas críticos + importantes del audit.
 * Cada item es checkable; el estado vive en el orquestador padre.
 *
 * Estructura:
 *   - Header sticky con progress bar + contador.
 *   - Barra de filtros (chips con conteo).
 *   - Lista filtrada, agrupada por prioridad (Alta → Media).
 */
export function ActionPlan({
  critical,
  warning,
  checklist,
  onToggle,
}: {
  critical: MetricWithModule[]
  warning: MetricWithModule[]
  checklist: ChecklistState
  onToggle: (metricId: string) => void
}) {
  const [filter, setFilter] = useState<FilterKey>('all')

  const allItems = [...critical, ...warning]
  const total = allItems.length
  const doneCount = allItems.filter(m => checklist[m.id]?.completed).length

  // Empty state: sin hallazgos
  if (total === 0) {
    return (
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <div className="flex items-start gap-3">
          <CheckCircle2 className="mt-0.5 h-6 w-6 shrink-0 text-emerald-600" strokeWidth={1.75} />
          <div>
            <h2 className="text-lg font-bold text-emerald-700">Sin problemas detectados</h2>
            <p className="mt-1 text-sm text-emerald-600">
              El sitio está en buen estado. No se requieren acciones correctivas urgentes.
            </p>
          </div>
        </div>
      </div>
    )
  }

  const pendingCount = total - doneCount
  const critDone = critical.filter(m => checklist[m.id]?.completed).length
  const critPending = critical.length - critDone
  const warnDone = warning.filter(m => checklist[m.id]?.completed).length
  const warnPending = warning.length - warnDone

  const filteredCritical = useMemo(() => {
    switch (filter) {
      case 'pending':  return critical.filter(m => !checklist[m.id]?.completed)
      case 'done':     return critical.filter(m => checklist[m.id]?.completed)
      case 'warning':  return []
      default:         return critical
    }
  }, [critical, checklist, filter])

  const filteredWarning = useMemo(() => {
    switch (filter) {
      case 'pending':  return warning.filter(m => !checklist[m.id]?.completed)
      case 'done':     return warning.filter(m => checklist[m.id]?.completed)
      case 'critical': return []
      default:         return warning
    }
  }, [warning, checklist, filter])

  const showingCritical = filteredCritical.length > 0
  const showingWarning = filteredWarning.length > 0
  const nothingToShow = !showingCritical && !showingWarning

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white">
      {/* Header sticky con progreso + filtros */}
      <div className="sticky top-0 z-10 rounded-t-2xl border-b border-[var(--border-default)] bg-white/95 px-6 py-4 backdrop-blur">
        <div className="mb-2 flex items-center justify-between gap-3">
          <h2 className="text-lg font-bold text-[var(--text-primary)]">Plan de acción</h2>
          <div className="flex items-center gap-2 text-sm">
            <span className="font-semibold text-[var(--text-primary)] tabular-nums">
              {doneCount}<span className="text-[var(--text-tertiary)]">/{total}</span>
            </span>
            <span className="text-xs text-[var(--text-tertiary)]">
              ({Math.round((doneCount / total) * 100)}%)
            </span>
            {doneCount === total && <Badge variant="success" className="text-[10px]">Completo</Badge>}
          </div>
        </div>

        <div className="mb-3 h-2 w-full overflow-hidden rounded-full bg-[var(--border-default)]">
          <div
            className="h-full rounded-full bg-emerald-500 transition-all duration-500 ease-out"
            style={{ width: `${(doneCount / total) * 100}%` }}
          />
        </div>

        <div className="flex flex-wrap items-center gap-1.5">
          <Filter className="h-3.5 w-3.5 text-[var(--text-tertiary)]" strokeWidth={1.5} />
          <FilterChip active={filter === 'all'}      onClick={() => setFilter('all')}      label="Todos"        count={total} />
          <FilterChip active={filter === 'pending'}  onClick={() => setFilter('pending')}  label="Pendientes"   count={pendingCount} tone="accent" />
          <FilterChip active={filter === 'done'}     onClick={() => setFilter('done')}     label="Completados"  count={doneCount}    tone="emerald" />
          <span className="mx-1 text-[var(--text-tertiary)]">·</span>
          <FilterChip active={filter === 'critical'} onClick={() => setFilter('critical')} label="Críticos"     count={filter === 'done' ? critDone : filter === 'pending' ? critPending : critical.length} tone="red" />
          <FilterChip active={filter === 'warning'}  onClick={() => setFilter('warning')}  label="Importantes"  count={filter === 'done' ? warnDone : filter === 'pending' ? warnPending : warning.length} tone="amber" />
        </div>
      </div>

      {/* Lista */}
      <div className="space-y-4 p-6">
        {nothingToShow && (
          <p className="py-10 text-center text-sm text-[var(--text-tertiary)]">
            No hay items que coincidan con el filtro.
          </p>
        )}

        {showingCritical && (
          <div>
            <h3 className="mb-2 flex items-center gap-2 text-sm font-bold text-red-600">
              <span className="h-2 w-2 rounded-full bg-red-500" />
              Prioridad Alta — {filteredCritical.length} {filteredCritical.length === 1 ? 'problema crítico' : 'problemas críticos'}
            </h3>
            <div className="space-y-2">
              {filteredCritical.map((m, i) => (
                <ActionItem key={m.id} index={i + 1} metric={m} checked={checklist[m.id]?.completed ?? false} onToggle={() => onToggle(m.id)} />
              ))}
            </div>
          </div>
        )}

        {showingWarning && (
          <div>
            <h3 className="mb-2 flex items-center gap-2 text-sm font-bold text-amber-600">
              <span className="h-2 w-2 rounded-full bg-amber-500" />
              Prioridad Media — {filteredWarning.length} {filteredWarning.length === 1 ? 'mejora' : 'mejoras'}
            </h3>
            <div className="space-y-2">
              {filteredWarning.map((m, i) => (
                <ActionItem key={m.id} index={filteredCritical.length + i + 1} metric={m} checked={checklist[m.id]?.completed ?? false} onToggle={() => onToggle(m.id)} />
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

function FilterChip({
  label, count, active, onClick, tone = 'neutral',
}: {
  label: string
  count: number
  active: boolean
  onClick: () => void
  tone?: 'neutral' | 'accent' | 'emerald' | 'red' | 'amber'
}) {
  const activeTones: Record<string, string> = {
    neutral: 'bg-[var(--text-primary)] text-white',
    accent:  'bg-[var(--accent-primary)] text-white',
    emerald: 'bg-emerald-600 text-white',
    red:     'bg-red-600 text-white',
    amber:   'bg-amber-600 text-white',
  }
  const inactiveTones: Record<string, string> = {
    neutral: 'bg-[var(--bg-secondary)] text-[var(--text-secondary)] hover:bg-[var(--border-default)]',
    accent:  'bg-[var(--accent-primary)]/10 text-[var(--accent-primary)] hover:bg-[var(--accent-primary)]/20',
    emerald: 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
    red:     'bg-red-50 text-red-700 hover:bg-red-100',
    amber:   'bg-amber-50 text-amber-700 hover:bg-amber-100',
  }
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-medium transition-colors ${active ? activeTones[tone] : inactiveTones[tone]}`}
    >
      {label}
      <span className={`inline-flex h-4 min-w-[16px] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums ${active ? 'bg-white/20' : 'bg-white/60'}`}>
        {count}
      </span>
    </button>
  )
}

function ActionItem({
  index,
  metric,
  checked,
  onToggle,
}: {
  index: number
  metric: MetricWithModule
  checked: boolean
  onToggle: () => void
}) {
  return (
    <div className={`rounded-xl border p-3 transition-all ${checked ? 'border-emerald-200 bg-emerald-50/50 opacity-70' : levelBg(metric.level)}`}>
      <div className="flex items-start gap-2">
        <button onClick={onToggle} className="mt-0.5 shrink-0 cursor-pointer">
          <div className={`flex h-5 w-5 items-center justify-center rounded-md border-2 transition-colors ${
            checked ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-gray-300 hover:border-gray-400'
          }`}>
            {checked && <Check className="h-3 w-3" strokeWidth={3} />}
          </div>
        </button>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className={`text-sm font-semibold ${checked ? 'text-[var(--text-tertiary)] line-through' : 'text-[var(--text-primary)]'}`}>
              {metric.name}
            </span>
            <Badge variant="secondary" className="text-[10px]">{metric.moduleName}</Badge>
            <span className="text-[10px] text-[var(--text-tertiary)]">#{index}</span>
          </div>
          {!checked && (
            <>
              <p className="mt-1 text-sm text-[var(--text-secondary)]">{metric.description}</p>
              {metric.recommendation && (
                <p className="mt-2 text-sm font-medium text-[var(--text-primary)]">
                  → {metric.recommendation}
                </p>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
