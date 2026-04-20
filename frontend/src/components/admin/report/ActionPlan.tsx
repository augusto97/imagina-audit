import { Badge } from '@/components/ui/badge'
import { levelBg, type ChecklistState, type MetricWithModule } from './helpers'

/**
 * Plan de acción con los problemas críticos + importantes del audit.
 * Cada item es checkable; el estado vive en el orquestador padre.
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
  const allItems = [...critical, ...warning]
  const total = allItems.length
  const doneCount = allItems.filter(m => checklist[m.id]?.completed).length

  if (total === 0) {
    return (
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <h2 className="text-lg font-bold text-emerald-700">Sin problemas detectados</h2>
        <p className="text-sm text-emerald-600 mt-1">El sitio está en buen estado. No se requieren acciones correctivas urgentes.</p>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-[var(--border-default)] bg-white p-6">
      <div className="flex items-center justify-between mb-1">
        <h2 className="text-lg font-bold text-[var(--text-primary)]">Plan de Acción</h2>
        <span className="text-xs font-medium text-[var(--text-tertiary)]">
          {doneCount}/{total} completados
          {doneCount > 0 && <span className="ml-2 text-emerald-500">({Math.round(doneCount / total * 100)}%)</span>}
        </span>
      </div>
      {doneCount < total && (
        <div className="w-full bg-gray-100 rounded-full h-1.5 mb-4">
          <div className="bg-emerald-500 h-1.5 rounded-full transition-all" style={{ width: `${(doneCount / total) * 100}%` }} />
        </div>
      )}
      {doneCount === total && <p className="text-sm text-emerald-600 mb-4 font-medium">Todas las correcciones completadas.</p>}

      {critical.length > 0 && (
        <div className="mb-4">
          <h3 className="text-sm font-bold text-red-600 mb-2 flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-red-500" /> Prioridad Alta — {critical.length} problemas críticos
          </h3>
          <div className="space-y-2">
            {critical.map((m, i) => (
              <ActionItem key={m.id} index={i + 1} metric={m} checked={checklist[m.id]?.completed ?? false} onToggle={() => onToggle(m.id)} />
            ))}
          </div>
        </div>
      )}

      {warning.length > 0 && (
        <div>
          <h3 className="text-sm font-bold text-amber-600 mb-2 flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-amber-500" /> Prioridad Media — {warning.length} mejoras importantes
          </h3>
          <div className="space-y-2">
            {warning.map((m, i) => (
              <ActionItem key={m.id} index={critical.length + i + 1} metric={m} checked={checklist[m.id]?.completed ?? false} onToggle={() => onToggle(m.id)} />
            ))}
          </div>
        </div>
      )}
    </div>
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
    <div className={`rounded-xl border p-3 ${checked ? 'bg-emerald-50/50 border-emerald-200 opacity-70' : levelBg(metric.level)} transition-all`}>
      <div className="flex items-start gap-2">
        <button onClick={onToggle} className="mt-0.5 shrink-0 cursor-pointer">
          <div className={`h-5 w-5 rounded-md border-2 flex items-center justify-center transition-colors ${checked ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 hover:border-gray-400'}`}>
            {checked && <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>}
          </div>
        </button>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <span className={`text-sm font-semibold ${checked ? 'line-through text-[var(--text-tertiary)]' : 'text-[var(--text-primary)]'}`}>{metric.name}</span>
            <Badge variant="secondary" className="text-[10px]">{metric.moduleName}</Badge>
            <span className="text-[10px] text-[var(--text-tertiary)]">#{index}</span>
          </div>
          {!checked && (
            <>
              <p className="text-sm text-[var(--text-secondary)] mt-1">{metric.description}</p>
              {metric.recommendation && (
                <p className="text-sm font-medium text-[var(--text-primary)] mt-2">
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
